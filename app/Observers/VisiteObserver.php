<?php

namespace App\Observers;

use App\Models\Visite;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class VisiteObserver
{
    public function created(Visite $visite): void
    {
        // 1) Récup service + config
        $service = $visite->service()->first(['id','slug','config']);
        if (! $service) return;

        $slug = (string) $service->slug;
        $cfg  = is_array($service->config) ? $service->config : [];

        // 2) Trouver le modèle "détail" à créer:
        //    a) D'abord un modèle dédié par slug: App\Models\{StudlySlug}
        //    b) Sinon, la config 'detail_model' si fournie et existante
        //    c) Sinon fallback sur App\Models\Consultation s'il existe
        $studly = Str::of($slug)->studly()->toString(); // ex. medecine -> Medecine
        $candidates = [
            "\\App\\Models\\{$studly}",
        ];

        // Optionnel: si tu ranges certains modèles ailleurs, ajoute d'autres namespaces ici
        // $candidates[] = "\\App\\Models\\Services\\{$studly}";

        if (!empty($cfg['detail_model']) && is_string($cfg['detail_model'])) {
            $candidates[] = $cfg['detail_model'];
        }

        // fallback générique
        $candidates[] = "\\App\\Models\\Consultation";

        $modelClass = null;
        foreach ($candidates as $cls) {
            if (class_exists($cls)) { $modelClass = $cls; break; }
        }
        if (! $modelClass) return;

        /** @var \Illuminate\Database\Eloquent\Model $detail */
        $detail = new $modelClass;
        $detailTable = $detail->getTable();

        // 3) Déterminer la FK (par défaut 'visite_id' si la colonne existe)
        $fk = $cfg['detail_fk'] ?? 'visite_id';
        if (! Schema::hasColumn($detailTable, $fk)) {
            // Petite sécurité: si la table n'a pas 'visite_id', on ne force pas la création
            return;
        }

        // 4) Déterminer le champ docteur:
        //    - priorité config: detail_doctor_field
        //    - sinon auto: soignant_id puis medecin_id si la colonne existe
        $doctorFieldName = $cfg['detail_doctor_field'] ?? null;
        if (! $doctorFieldName) {
            if (Schema::hasColumn($detailTable, 'soignant_id')) {
                $doctorFieldName = 'soignant_id';
            } elseif (Schema::hasColumn($detailTable, 'medecin_id')) {
                $doctorFieldName = 'medecin_id';
            }
        }

        // 5) Exiger un médecin ? (par défaut false)
        $requireDoctor = (bool)($cfg['require_doctor_for_detail'] ?? false);
        if ($requireDoctor && empty($visite->medecin_id)) {
            // on ne crée pas le détail si explicitement requis et absent
            return;
        }

        // 6) Construire le payload de création
        $payload = [
            'patient_id' => $visite->patient_id ?? null,
            'service_id' => $visite->service_id ?? null,
        ];

        if ($doctorFieldName && Schema::hasColumn($detailTable, $doctorFieldName) && !empty($visite->medecin_id)) {
            $payload[$doctorFieldName] = $visite->medecin_id;
        }

        if (!empty($cfg['detail_defaults']) && is_array($cfg['detail_defaults'])) {
            foreach ($cfg['detail_defaults'] as $k => $v) {
                if (!array_key_exists($k, $payload)) {
                    $payload[$k] = $v;
                }
            }
        }

        // 7) Création idempotente (indexée par visite_id)
        $detail::firstOrCreate([$fk => $visite->id], $payload);

        // 8) Facture auto
        if (class_exists(\App\Services\VisitInvoiceService::class)) {
            app(\App\Services\VisitInvoiceService::class)->createForVisite($visite);
        }
    }
}
