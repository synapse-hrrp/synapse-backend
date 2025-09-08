<?php

namespace App\Http\Controllers\Api\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvoiceStoreRequest;
use App\Http\Requests\InvoiceUpdateRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Finance\Invoice;
use App\Models\Finance\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $patientId = (string) $request->query('patient_id', '');
        $statut    = (string) $request->query('statut_paiement', '');
        $q         = (string) $request->query('q', ''); // recherche sur numero

        $query = Invoice::query()
            ->with(['lignes','paiements','creePar:id,name,email'])
            ->when($patientId !== '', fn($q2) => $q2->where('patient_id', $patientId))
            ->when($statut !== '',    fn($q2) => $q2->where('statut_paiement', $statut))
            ->when($q !== '',         fn($q2) => $q2->where('numero','like',"%{$q}%"))
            ->orderByDesc('created_at');

        $perPage = min(max((int)$request->query('limit', 20), 1), 200);
        $items   = $query->paginate($perPage);

        return InvoiceResource::collection($items)->additional([
            'page'  => $items->currentPage(),
            'limit' => $items->perPage(),
            'total' => $items->total(),
        ]);
    }

    public function store(InvoiceStoreRequest $request)
    {
        $data = $request->validated();

        return DB::transaction(function () use ($request, $data) {
            $user = $request->user();

            $invoice = new Invoice();
            $invoice->patient_id      = $data['patient_id'];
            $invoice->visite_id       = $data['visite_id'] ?? null;
            $invoice->devise          = $data['devise']    ?? 'XOF';
            $invoice->remise          = (float) ($data['remise'] ?? 0);
            $invoice->cree_par        = $user?->id;

            $invoice->montant_total   = 0;
            $invoice->montant_paye    = 0;
            $invoice->statut_paiement = 'unpaid';
            $invoice->save(); // numero auto via boot()

            // Lignes
            foreach ($data['lignes'] as $it) {
                $item = new InvoiceItem([
                    'invoice_id'    => $invoice->id,
                    'service_slug'  => $it['service_slug'],
                    'reference_id'  => $it['reference_id'] ?? null,
                    'libelle'       => $it['libelle'],
                    'quantite'      => $it['quantite'] ?? 1,
                    'prix_unitaire' => $it['prix_unitaire'],
                    'total_ligne'   => ($it['quantite'] ?? 1) * $it['prix_unitaire'],
                ]);
                $item->save();
            }

            // Recalcule totaux
            $invoice->recomputeTotals();

            $invoice->load(['lignes','paiements','creePar:id,name,email']);
            return (new InvoiceResource($invoice))
                ->response()
                ->setStatusCode(201);
        });
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['lignes','paiements.recuPar:id,name,email','creePar:id,name,email']);
        return new InvoiceResource($invoice);
    }

    public function update(InvoiceUpdateRequest $request, Invoice $invoice)
    {
        $data = $request->validated();

        // Si déjà canceled → on empêche autres modifs sauf “note/remise” (à toi de décider)
        if (($data['statut_paiement'] ?? null) === 'canceled') {
            $invoice->statut_paiement = 'canceled';
            $invoice->save();
        } else {
            $invoice->fill($data)->save();
        }

        // Recalcul si remise ou devise a changé
        if (array_key_exists('remise', $data)) {
            $invoice->recomputeTotals();
        }

        $invoice->load(['lignes','paiements.recuPar:id,name,email','creePar:id,name,email']);
        return new InvoiceResource($invoice);
    }

    public function destroy(Invoice $invoice)
    {
        // On autorise la suppression seulement si pas de paiement
        if ($invoice->paiements()->exists()) {
            return response()->json(['message' => "Impossible de supprimer : des paiements existent."], 422);
        }
        $invoice->delete();
        return response()->noContent();
    }
}
