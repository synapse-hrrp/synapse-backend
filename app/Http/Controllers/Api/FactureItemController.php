<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FactureItemStoreRequest;
use App\Http\Requests\FactureItemUpdateRequest;
use App\Models\{FactureItem, Facture, Tarif};
use Illuminate\Support\Facades\Auth;

class FactureItemController extends Controller
{
    // POST /api/v1/facture-items
    public function store(FactureItemStoreRequest $request)
    {
        $data = $request->validated();
        $data['created_by_user_id'] = Auth::id();

        // 1) Résoudre tarif si fourni
        $tarif = null;
        if (!empty($data['tarif_id'])) {
            $tarif = Tarif::find($data['tarif_id']);
        } elseif (!empty($data['tarif_code'])) {
            $tarif = Tarif::query()->actifs()->byCode($data['tarif_code'])->latest('created_at')->first();
        }

        // 2) Construire libellé/prix si tarif
        if ($tarif) {
            $data['tarif_id']      = $tarif->id;
            $data['tarif_code']    = $tarif->code;
            $data['designation']   = $data['designation'] ?? ($tarif->libelle ?? $tarif->code); // <-- libelle
            $data['prix_unitaire'] = $data['prix_unitaire'] ?? (float) $tarif->montant;
            $data['devise']        = $data['devise'] ?? ($tarif->devise ?? 'XAF');
            $data['type_origine']  = 'tarif';
        } else {
            // Saisie manuelle
            $data['type_origine']  = 'manuel';
            $data['devise']        = $data['devise'] ?? 'XAF';
        }

        $data['quantite'] = (int) ($data['quantite'] ?? 1);

        // 3) Assurer la facture (nouvelle facture caisse centrale)
        if (!empty($data['facture_id'])) {
            $facture = Facture::findOrFail($data['facture_id']);
        } else {
            $facture = \App\Services\InvoiceService::openNewForPatientStatic( // <-- utiliser la version statique
                $data['patient_id'],
                $data['devise']
            );
            $data['facture_id'] = $facture->id;
        }

        // 4) Créer l’item
        $item = FactureItem::create($data);

        // 5) Répercuter dans les lignes de facture
        $facture->ajouterLigne([
            'designation'   => $item->designation,
            'quantite'      => $item->quantite,
            'prix_unitaire' => (float) $item->prix_unitaire,
            'tarif_id'      => $item->tarif_id,
        ]);

        // 6) Recalc
        $facture->recalc();

        return response()->json([
            'item'    => $item,
            'facture' => $facture->fresh(['lignes','reglements']),
        ], 201);
    }

    public function update(FactureItemUpdateRequest $request, FactureItem $facture_item)
    {
        $facture_item->update($request->validated());
        $facture = $facture_item->facture;
        if ($facture) $facture->recalc();

        return response()->json([
            'item'    => $facture_item,
            'facture' => $facture?->fresh(['lignes','reglements']),
        ]);
    }

    public function destroy(FactureItem $facture_item)
    {
        $facture = $facture_item->facture;
        $facture_item->delete();
        if ($facture) $facture->recalc();

        return response()->noContent();
    }
}
