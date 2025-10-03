<?php

namespace App\Services;

use App\Models\{Facture, Visite, Tarif};
use Illuminate\Support\Facades\DB;

class VisitInvoiceService
{
    /**
     * Crée (ou retrouve) la facture de la visite et y ajoute une ligne.
     * Idempotent: si une facture existe déjà pour la visite, on ne recrée pas.
     */
    public function createForVisite(Visite $visite): Facture
    {
        return DB::transaction(function () use ($visite) {

            // 1) Si une facture est déjà liée à la visite, on la retourne
            $existing = $visite->relationLoaded('facture') ? $visite->facture : $visite->facture()->first();
            if ($existing) {
                return $existing->fresh(['lignes','reglements']);
            }

            // 2) Créer une facture liée à la visite
            $facture = Facture::create([
                'patient_id'    => $visite->patient_id,
                'visite_id'     => $visite->getKey(),
                'devise'        => $visite->devise ?? 'XAF',
                'montant_total' => 0,
                'montant_du'    => 0,
                // numero / statut sont gérés par Facture::booted()
            ]);

            // 3) Déterminer libellé + prix pour la ligne
            $designation = $visite->service?->name
                ? ('Visite - ' . $visite->service->name)
                : 'Visite';

            // Si un Tarif existe, on le récupère (pour tracer tarif_id sur la ligne)
            $tarifId = $visite->tarif_id ?: null;
            if (!$tarifId && $visite->service?->slug) {
                $tarifId = Tarif::query()
                    ->where('service_slug', $visite->service->slug)
                    ->where('is_active', true)
                    ->latest('created_at')
                    ->value('id');
            }

            $prix = (float) ($visite->montant_prevu ?? 0);

            // 4) Ajouter la ligne puis recalculer (ton helper le fait déjà)
            $facture->ajouterLigne([
                'designation'   => $designation,
                'quantite'      => 1,
                'prix_unitaire' => $prix,
                'tarif_id'      => $tarifId,
            ]);

            // 5) Aligner le "dû" de la visite avec la facture si besoin (optionnel)
            if ($visite->montant_du != $prix) {
                $visite->forceFill(['montant_du' => $prix])->saveQuietly();
            }

            return $facture->fresh(['lignes','reglements']);
        });
    }
}
