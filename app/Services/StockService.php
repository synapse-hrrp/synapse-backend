<?php

namespace App\Services;

use App\Models\Reagent;
use App\Models\ReagentLot;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    /** Réception d’un lot (création lot + mouvement IN) */
    public function receiveLot(Reagent $reagent, array $lotData, float $qty, ?float $unitCost = null): ReagentLot
    {
        return DB::transaction(function () use ($reagent, $lotData, $qty, $unitCost) {
            if ($qty <= 0) $this->fail('quantity', 'La quantité doit être > 0');

            $lot = $reagent->lots()->create(array_merge($lotData, [
                'initial_qty' => $qty,
                'current_qty' => $qty,
            ]));

            $this->logMovement($reagent->id, 'IN', $qty, [
                'reagent_lot_id' => $lot->id,
                'location_id'    => $lot->location_id,
                'unit_cost'      => $unitCost,
                'reference'      => $lotData['reference'] ?? null,
            ]);

            $reagent->increment('current_stock', $qty);
            return $lot;
        });
    }

    /** Consommation FEFO multi-lots */
    public function consumeFEFO(Reagent $reagent, float $qty, array $meta = []): array
    {
        return DB::transaction(function () use ($reagent, $qty, $meta) {
            if ($qty <= 0) $this->fail('quantity','La quantité doit être > 0');

            $reagent->refresh();
            if ($reagent->current_stock < $qty) $this->fail('stock','Stock insuffisant');

            $lots = $reagent->lots()
                ->where('status','ACTIVE')
                ->where(function($q){ $q->whereNull('expiry_date')->orWhere('expiry_date','>=', today()); })
                ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END, expiry_date asc, received_at asc')
                ->lockForUpdate()
                ->get();

            $remaining = $qty; $movements = [];
            foreach ($lots as $lot) {
                if ($remaining <= 0) break;
                $take = min($lot->current_qty, $remaining);
                if ($take <= 0) continue;

                $movements[] = $this->logMovement($reagent->id, 'OUT', $take, [
                    'reagent_lot_id'=>$lot->id,
                    'location_id'=>$lot->location_id,
                    'moved_at'=>$meta['moved_at'] ?? now(),
                    'reference'=>$meta['reference'] ?? null,
                    'unit_cost'=>$meta['unit_cost'] ?? null,
                    'user_id'=>$meta['user_id'] ?? null,
                    'notes'=>$meta['notes'] ?? null,
                ]);

                $lot->decrement('current_qty', $take);
                $reagent->decrement('current_stock', $take);
                $remaining -= $take;
            }

            if ($remaining > 0) $this->fail('stock','Lots disponibles insuffisants (FEFO).');
            return $movements;
        });
    }

    /**
     * Transfert d’un lot entre emplacements.
     * - Si qty == current_qty  => transfert TOTAL du lot (on change juste location_id)
     * - Si 0 < qty < current_qty => transfert PARTIEL => SPLIT : on crée un nouveau lot à destination
     * Le stock total du réactif n’est PAS modifié.
     */
    public function transferLot(ReagentLot $lot, int $toLocationId, float $qty): array
    {
        return DB::transaction(function () use ($lot, $toLocationId, $qty) {
            // Verrouille le lot
            $lot = ReagentLot::lockForUpdate()->findOrFail($lot->id);

            if ($qty <= 0) {
                $this->fail('quantity','La quantité doit être > 0');
            }
            if ($qty > $lot->current_qty) {
                $this->fail('quantity','Quantité supérieure au contenu du lot');
            }

            $fromLocationId = $lot->location_id;
            $eps = 1e-9;

            // (Optionnel) Vérification compatibilité stockage (si infos dispo)
            $reagent = $lot->reagent()->first(['id','storage_temp_min','storage_temp_max']);
            $target  = \App\Models\Location::findOrFail($toLocationId);
            if (!is_null($reagent->storage_temp_min) && !is_null($reagent->storage_temp_max)
                && !is_null($target->temp_range_min) && !is_null($target->temp_range_max)) {
                $ok = !($target->temp_range_min > $reagent->storage_temp_max
                        || $target->temp_range_max < $reagent->storage_temp_min);
                if (!$ok) $this->fail('to_location_id', 'Emplacement incompatible avec la plage de stockage du réactif');
            }

            // TRANSFERT TOTAL
            if (abs($qty - $lot->current_qty) < $eps) {
                $lot->update(['location_id' => $toLocationId]);

                $mov = $this->logMovement($lot->reagent_id, 'TRANSFER', $qty, [
                    'reagent_lot_id' => $lot->id,
                    'location_id'    => $toLocationId,
                    'notes'          => "Transfert total du lot de location {$fromLocationId} vers {$toLocationId}",
                ]);

                return [
                    'movement' => $mov,
                    'from_lot' => $lot->fresh(), // = to_lot
                    'to_lot'   => $lot->fresh(),
                ];
            }

            // TRANSFERT PARTIEL → SPLIT (création d’un nouveau lot à destination)
            $newLotCode = $this->makeSplitLotCode($lot->reagent_id, $lot->lot_code);

            $toLot = ReagentLot::create([
                'reagent_id'   => $lot->reagent_id,
                'lot_code'     => $newLotCode,
                'expiry_date'  => $lot->expiry_date,
                'received_at'  => $lot->received_at,
                'initial_qty'  => $qty,
                'current_qty'  => $qty,
                'location_id'  => $toLocationId,
                'status'       => $lot->status,
                'coa_url'      => $lot->coa_url,
                'barcode'      => $lot->barcode,
            ]);

            // Décrémente le lot d’origine (emplacement source)
            $lot->decrement('current_qty', $qty);

            // Journalise le transfert sur le lot “arrivé”
            $mov = $this->logMovement($lot->reagent_id, 'TRANSFER', $qty, [
                'reagent_lot_id' => $toLot->id,
                'location_id'    => $toLocationId,
                'notes'          => "Transfert partiel: {$qty} depuis lot {$lot->id} (loc {$fromLocationId}) vers lot {$toLot->id} (loc {$toLocationId})",
            ]);

            // NB: on ne touche pas à reagents.current_stock (stock total inchangé)
            return [
                'movement' => $mov,
                'from_lot' => $lot->fresh(),
                'to_lot'   => $toLot->fresh(),
            ];
        });
    }

    /** Ajustement inventaire (autorisé aux rôles qualité/admin) */
    public function adjust(ReagentLot $lot, float $delta, string $reason): StockMovement
    {
        return DB::transaction(function () use ($lot, $delta, $reason) {
            if ($delta == 0.0) $this->fail('quantity','Delta nul');
            $newQty = $lot->current_qty + $delta;
            if ($newQty < 0) $this->fail('stock',"Ajustement mènerait à négatif");

            $mov = $this->logMovement($lot->reagent_id, 'ADJUST', abs($delta), [
                'reagent_lot_id'=>$lot->id,
                'location_id'=>$lot->location_id,
                'notes'=>"Ajustement: $reason (delta=$delta)",
            ]);

            $lot->update(['current_qty' => $newQty]);
            $lot->reagent()->update(['current_stock' => $lot->reagent->computeStock()]);
            return $mov;
        });
    }

    /** Marquer expiré / élimination */
    public function disposeLot(ReagentLot $lot, ?string $reason = null): StockMovement
    {
        return DB::transaction(function () use ($lot, $reason) {
            $qty = $lot->current_qty;
            if ($qty <= 0) { $lot->update(['status' => 'DISPOSED']); return new StockMovement(); }

            $this->logMovement($lot->reagent_id, 'DISPOSAL', $qty, [
                'reagent_lot_id'=>$lot->id,
                'location_id'=>$lot->location_id,
                'notes'=>$reason ?? 'Elimination',
            ]);

            $lot->update(['current_qty'=>0,'status'=>'DISPOSED']);
            $lot->reagent()->decrement('current_stock', $qty);
            return $lot->movements()->latest()->first();
        });
    }

    /** Utilitaire: crée un mouvement */
    private function logMovement(int $reagentId, string $type, float $qty, array $extra = []): StockMovement
    {
        if ($qty <= 0) $this->fail('quantity','La quantité doit être > 0');
        return StockMovement::create(array_merge([
            'reagent_id' => $reagentId,
            'type'       => $type,
            'quantity'   => $qty,
            'moved_at'   => $extra['moved_at'] ?? now(),
        ], $extra));
    }

    /** Génère un lot_code unique pour un split */
    private function makeSplitLotCode(int $reagentId, string $baseLotCode): string
    {
        $suffix = '-T' . now()->format('YmdHis');
        $code = $baseLotCode . $suffix;
        $i = 0;
        while (ReagentLot::where('reagent_id',$reagentId)->where('lot_code',$code)->exists()) {
            $i++;
            $code = $baseLotCode . $suffix . '-' . $i;
        }
        return $code;
    }

    private function fail(string $field, string $msg) {
        throw ValidationException::withMessages([$field => $msg]);
    }
}
