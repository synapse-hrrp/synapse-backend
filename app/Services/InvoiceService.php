<?php

namespace App\Services;

use App\Models\{
    Facture,
    FactureLigne,
    Examen,
    Echographie,
    Hospitalisation,
    DeclarationNaissance,
    BilletSortie,
    Tarif,
    Accouchement            // ← AJOUT
};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Schema;       // ← important pour Schema::hasColumn


class InvoiceService
{
    /* =======================================================================
     |                              TARIFS
     |=======================================================================*/

    public function findActiveTarif(?string $serviceSlug, string $code): ?Tarif
    {
        $code = strtoupper(trim($code));

        $q = Tarif::query()->actifs();

        if ($serviceSlug) {
            $t = (clone $q)->forService($serviceSlug)->byCode($code)->latest('created_at')->first();
            if ($t) return $t;
        }

        return $q->byCode($code)->latest('created_at')->first();
    }

    /* =======================================================================
     |                         FACTURES – OUVERTURE
     |=======================================================================*/

    public function getOrCreateOpenInvoice(string $patientId, ?string $devise = 'XAF'): Facture
    {
        $devise = strtoupper(trim((string)($devise ?: 'XAF')));

        $open = Facture::query()
            ->where('patient_id', $patientId)
            ->whereIn('statut', ['IMPAYEE','DRAFT'])
            ->where('devise', $devise)
            ->latest()
            ->first();

        if ($open) return $open;

        return Facture::create([
            'patient_id'    => $patientId,
            'montant_total' => 0,
            'montant_du'    => 0,
            'devise'        => $devise,
            'statut'        => 'IMPAYEE',
        ]);
    }

    public function recalcInvoice(Facture $facture): void
    {
        if (method_exists($facture, 'recalc')) {
            $facture->recalc();
            return;
        }

        $sum = (float) $facture->lignes()->sum('montant');
        $facture->update([
            'montant_total' => $sum,
            'montant_du'    => $sum,
        ]);
    }

    protected function addLine(Facture $facture, array $payload): FactureLigne
    {
        $payload['designation']   = trim((string)($payload['designation'] ?? ''));
        $payload['quantite']      = (int)   ($payload['quantite']      ?? 1);
        $payload['prix_unitaire'] = (float) ($payload['prix_unitaire'] ?? 0);
        $payload['montant']       = array_key_exists('montant', $payload)
            ? (float) $payload['montant']
            : (float) ($payload['quantite'] * $payload['prix_unitaire']);

        if (method_exists($facture, 'ajouterLigne')) {
            $ligne = $facture->ajouterLigne($payload);
            if (method_exists($facture, 'recalc')) {
                $facture->recalc();
            }
            return $ligne;
        }

        /** @var FactureLigne $ligne */
        $ligne = $facture->lignes()->create($payload);
        $this->recalcInvoice($facture);
        return $ligne;
    }

    /* =======================================================================
     |                             ATTACH – EXAMEN
     |=======================================================================*/

    public function attachExam(Examen $examen): Facture
    {
        return DB::transaction(function () use ($examen) {
            $facture = Facture::create([
                'patient_id'    => $examen->patient_id,
                'devise'        => strtoupper(trim($examen->devise ?? 'XAF')),
                'montant_total' => 0,
                'montant_du'    => 0,
                'statut'        => 'IMPAYEE',
            ]);

            $tarifId = null;
            if ($examen->code_examen) {
                $tarifQ = Tarif::query()->actifs()->byCode($examen->code_examen);
                if ($examen->service_slug) $tarifQ->forService($examen->service_slug);
                if ($tarif = $tarifQ->latest('created_at')->first()) {
                    $tarifId = $tarif->id;
                }
            }

            $this->addLine($facture, [
                'designation'   => $examen->nom_examen ?: ($examen->code_examen ?: 'Examen'),
                'quantite'      => 1,
                'prix_unitaire' => (float) ($examen->prix ?? 0),
                'tarif_id'      => $tarifId,
            ]);

            $examen->forceFill(['facture_id' => $facture->getKey()])->saveQuietly();

            return $facture->fresh(['lignes','reglements']);
        });
    }

    /* =======================================================================
     |                          ATTACH – ECHOGRAPHIE
     |=======================================================================*/

    public function attachEchographie(Echographie $echo): Facture
    {
        return DB::transaction(function () use ($echo) {
            $code = $echo->code_echo ? strtoupper(trim($echo->code_echo)) : '';
            if (!$code) {
                throw ValidationException::withMessages(['tarif_code' => 'Code tarif écho manquant.']);
            }

            $tarif = $this->findActiveTarif($echo->service_slug, $code);
            if (!$tarif) {
                throw ValidationException::withMessages(['tarif' => "Tarif introuvable pour code '{$code}'"]);
            }

            $devise  = strtoupper(trim($tarif->devise ?? 'XAF'));
            $facture = $this->getOrCreateOpenInvoice($echo->patient_id, $devise);

            $this->addLine($facture, [
                'tarif_id'      => $tarif->id,
                'designation'   => $echo->nom_echo ?: ($tarif->libelle ?: $tarif->code),
                'quantite'      => 1,
                'prix_unitaire' => (float) $tarif->montant,
            ]);

            $echo->forceFill([
                'prix'       => (float) $tarif->montant,
                'devise'     => $devise,
                'facture_id' => $facture->getKey(),
            ])->saveQuietly();

            return $facture->fresh(['lignes','reglements']);
        });
    }

    /* =======================================================================
     |                       ATTACH – HOSPITALISATION
     |=======================================================================*/

    public function attachHospitalisation(Hospitalisation $hosp): Facture
    {
        return DB::transaction(function () use ($hosp) {
            $code  = 'HOSP_ADM';
            $tarif = $this->findActiveTarif($hosp->service_slug, $code);

            if (!$tarif) {
                throw ValidationException::withMessages([
                    'tarif' => "Tarif introuvable pour hospitalisation (code {$code})."
                ]);
            }

            $devise  = strtoupper(trim($tarif->devise ?? 'XAF'));
            $facture = $this->getOrCreateOpenInvoice($hosp->patient_id, $devise);

            $this->addLine($facture, [
                'tarif_id'      => $tarif->id,
                'designation'   => $tarif->libelle ?: 'Admission / Hospitalisation',
                'quantite'      => 1,
                'prix_unitaire' => (float) $tarif->montant,
            ]);

            $hosp->updateQuietly(['facture_id' => $facture->getKey()]);

            return $facture->fresh(['lignes','reglements']);
        });
    }

    /* =======================================================================
     |                    ATTACH – DÉCLARATION DE NAISSANCE
     |=======================================================================*/

    public function attachDeclaration(DeclarationNaissance $decl): Facture
    {
        return DB::transaction(function () use ($decl) {
            $code  = 'DECL_NAIS';
            $tarif = $this->findActiveTarif($decl->service_slug, $code);

            if (!$tarif) {
                throw ValidationException::withMessages([
                    'tarif' => "Tarif introuvable pour déclaration de naissance (code {$code})."
                ]);
            }

            $devise    = strtoupper(trim($tarif->devise ?? 'XAF'));
            $patientId = $decl->mere_id; // facture pour la mère
            $facture   = $this->getOrCreateOpenInvoice($patientId, $devise);

            $designation = trim("Déclaration de naissance: " .
                trim($decl->bebe_nom . ' ' . ($decl->bebe_prenom ?? ''))
            );

            $this->addLine($facture, [
                'tarif_id'      => $tarif->id,
                'designation'   => $designation ?: ($tarif->libelle ?: 'Déclaration de naissance'),
                'quantite'      => 1,
                'prix_unitaire' => (float) $tarif->montant,
            ]);

            return $facture->fresh(['lignes','reglements']);
        });
    }

    /* =======================================================================
     |                         ATTACH – BILLET DE SORTIE
     |=======================================================================*/

    public function attachBillet(BilletSortie $billet): Facture
    {
        return DB::transaction(function () use ($billet) {
            $code  = 'BIL_SORTIE';
            $tarif = $this->findActiveTarif($billet->service_slug, $code);

            if (!$tarif) {
                throw ValidationException::withMessages([
                    'tarif' => "Tarif introuvable pour billet de sortie (code {$code})."
                ]);
            }

            $devise  = strtoupper(trim($tarif->devise ?? 'XAF'));
            $facture = $this->getOrCreateOpenInvoice($billet->patient_id, $devise);

            $this->addLine($facture, [
                'tarif_id'      => $tarif->id,
                'designation'   => $tarif->libelle ?: 'Billet de sortie',
                'quantite'      => 1,
                'prix_unitaire' => (float) $tarif->montant,
            ]);

            // $billet->updateQuietly(['facture_id' => $facture->getKey()]); // si colonne ajoutée

            return $facture->fresh(['lignes','reglements']);
        });
    }

    /* =======================================================================
     |                           ATTACH – ACCOUCHEMENT
     |=======================================================================*/

    /**
     * Accouchement : facture au nom de la mère (patient_id ou mere_id selon ton modèle),
     * code par défaut depuis la config: billing.codes.accouchement (fallback 'ACCOUCH').
     */
    public function attachAccouchement(Accouchement $acc): Facture
    {
        return DB::transaction(function () use ($acc) {
            // code tarif configurable
            $code  = config('billing.codes.accouchement', 'ACCOUCH');
            $tarif = $this->findActiveTarif($acc->service_slug ?? null, $code);

            if (!$tarif) {
                throw ValidationException::withMessages([
                    'tarif' => "Tarif introuvable pour accouchement (code {$code})."
                ]);
            }

            // identifiant patient à facturer : adapte selon ton schéma
            // ex: $acc->mere_id ou $acc->patient_id
            $patientId = $acc->mere_id ?? $acc->patient_id;

            $devise  = strtoupper(trim($tarif->devise ?? 'XAF'));
            $facture = $this->getOrCreateOpenInvoice($patientId, $devise);

            // désignation lisible
            $designation = $tarif->libelle ?: 'Accouchement';

            $this->addLine($facture, [
                'tarif_id'      => $tarif->id,
                'designation'   => $designation,
                'quantite'      => 1,
                'prix_unitaire' => (float) $tarif->montant,
            ]);

            // si ton modèle Accouchement possède facture_id :
            if (Schema::hasColumn($acc->getTable(), 'facture_id')) {
                $acc->updateQuietly(['facture_id' => $facture->getKey()]);
            }


            return $facture->fresh(['lignes','reglements']);
        });
    }

    /* =======================================================================
     |                       Helpers d'ouverture directe
     |=======================================================================*/

    public function openNewForPatient(string $patientId, string $devise = 'XAF'): Facture
    {
        return Facture::create([
            'patient_id'    => $patientId,
            'devise'        => strtoupper(trim($devise ?: 'XAF')),
            'montant_total' => 0,
            'montant_du'    => 0,
            'statut'        => 'IMPAYEE',
        ]);
    }

    public static function openNewForPatientStatic(string $patientId, string $devise = 'XAF'): Facture
    {
        return app(self::class)->openNewForPatient($patientId, $devise);
    }
}
