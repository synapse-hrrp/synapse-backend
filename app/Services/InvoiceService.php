<?php

namespace App\Services;

use App\Models\{Facture, Examen, Tarif};
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    /**
     * Crée une NOUVELLE facture pour chaque examen, ajoute 1 ligne,
     * lie l'examen à cette facture, puis recalcule les totaux.
     */
    public function attachExam(Examen $examen): Facture
    {
        return DB::transaction(function () use ($examen) {

            // 1) Créer systématiquement une nouvelle facture (pas de réutilisation)
            $facture = Facture::create([
                // id / numero / statut / devise gérés en partie par le modèle (booted)
                'patient_id'    => $examen->patient_id,
                'devise'        => $examen->devise ?? 'XAF',
                'montant_total' => 0,
                'montant_du'    => 0,
                // 'visite_id' => null // tu n'utilises pas la visite ici
            ]);

            // 2) Optionnel : retrouver le tarif utilisé (pour tracer dans la ligne)
            $tarifId = null;
            if ($examen->code_examen) {
                $tarifQ = Tarif::query()->actifs()->byCode($examen->code_examen);
                if ($examen->service_slug) {
                    $tarifQ->forService($examen->service_slug);
                }
                if ($tarif = $tarifQ->latest('created_at')->first()) {
                    $tarifId = $tarif->id;
                }
            }

            // 3) Ajouter la ligne issue de l'examen
            $facture->ajouterLigne([
                'designation'   => $examen->nom_examen ?? $examen->code_examen ?? 'Examen',
                'quantite'      => 1,
                'prix_unitaire' => (float) ($examen->prix ?? 0),
                'tarif_id'      => $tarifId,
            ]);
            // ->ajouterLigne() appelle déjà $facture->recalc()

            // 4) Lier l'examen à la facture créée
            $examen->forceFill(['facture_id' => $facture->getKey()])->saveQuietly();

            // 5) Retourner la facture avec ses lignes/règlements
            return $facture->fresh(['lignes','reglements']);
        });
    }



    public function openNewForPatient(string $patientId, string $devise = 'XAF'): \App\Models\Facture
    {
        return \App\Models\Facture::create([
            'patient_id'    => $patientId,
            'devise'        => $devise,
            'montant_total' => 0,
            'montant_du'    => 0,
            'statut'        => 'IMPAYEE',
        ]);
    }

    public static function openNewForPatientStatic(string $patientId, string $devise = 'XAF'): \App\Models\Facture
    {
        return app(self::class)->openNewForPatient($patientId, $devise);
    }




}
