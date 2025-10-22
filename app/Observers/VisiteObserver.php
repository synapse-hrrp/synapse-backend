<?php

namespace App\Observers;

use App\Models\Visite;
use Illuminate\Support\Facades\Schema;

class VisiteObserver
{
    public function created(Visite $visite): void
    {
        // 1) Récup config du service
        $service = $visite->service()->first(['id','slug','config']);
        if (!$service) return;

        $cfg = is_array($service->config) ? $service->config : [];

        $modelClass = method_exists($service, 'detailModelClass')
            ? ($service->detailModelClass() ?? ($cfg['detail_model'] ?? null))
            : ($cfg['detail_model'] ?? null);

        $fk = method_exists($service, 'detailFk')
            ? ($service->detailFk() ?? ($cfg['detail_fk'] ?? 'visite_id'))
            : ($cfg['detail_fk'] ?? 'visite_id');

        if (!$modelClass || !class_exists($modelClass)) return;

        // On instancie pour récupérer la table et pouvoir faire des fallbacks
        /** @var \Illuminate\Database\Eloquent\Model $detail */
        $detail = new $modelClass;
        $detailTable = $detail->getTable();

        // 2) Déterminer le champ docteur à setter
        //    - priorité à la config: detail_doctor_field
        //    - sinon fallback auto: si la table a une colonne 'soignant_id', on l'utilise
        $doctorFieldName = $cfg['detail_doctor_field']
            ?? (Schema::hasColumn($detailTable, 'soignant_id') ? 'soignant_id' : null);

        // 3) Exiger un médecin si nécessaire
        //    - priorité à la config: require_doctor_for_detail
        //    - sinon fallback auto: si la table a 'soignant_id', on considère le médecin requis
        $requireDoctor = array_key_exists('require_doctor_for_detail', $cfg)
            ? (bool)$cfg['require_doctor_for_detail']
            : (Schema::hasColumn($detailTable, 'soignant_id'));

        if ($requireDoctor && empty($visite->medecin_id)) {
            // Sécurité: si le médecin est requis mais absent, on ne crée pas le détail
            // (Normalement ton VisiteController bloque déjà la création de la visite sans medecin_id)
            return;
        }

        // 4) Construire le payload
        $payload = [
            'patient_id' => $visite->patient_id ?? null,
            'service_id' => $visite->service_id ?? null,
        ];

        if ($doctorFieldName && !array_key_exists($doctorFieldName, $payload) && !empty($visite->medecin_id)) {
            $payload[$doctorFieldName] = $visite->medecin_id; // ← clé pour ARU
        }

        // Defaults additionnels depuis la config
        if (!empty($cfg['detail_defaults']) && is_array($cfg['detail_defaults'])) {
            foreach ($cfg['detail_defaults'] as $k => $v) {
                if (!array_key_exists($k, $payload)) {
                    $payload[$k] = $v;
                }
            }
        }

        // 5) Création idempotente
        $detail::firstOrCreate([$fk => $visite->id], $payload);

         // Crée automatiquement la facture de la visite
        app(\App\Services\VisitInvoiceService::class)->createForVisite($visite);
    }
}
