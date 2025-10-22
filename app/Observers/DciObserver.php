<?php

namespace App\Observers;

use App\Models\Pharmacie\Dci;
use App\Models\Pharmacie\PharmaArticle;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class DciObserver
{
    /**
     * Quand on crée une DCI, on crée aussi un article "placeholder"
     * avec un code auto-généré unique.
     */
    public function created(Dci $dci): void
    {
        // Génère un code propre et unique à partir du nom de la DCI
        $code = $this->makeUniqueArticleCode($dci->name);

        PharmaArticle::create([
            'dci_id'     => $dci->id,
            'name'       => $dci->name,   // on met le nom de la DCI par défaut
            'code'       => $code,        // OBLIGATOIRE: non nul + unique
            // ces champs peuvent rester vides si les colonnes sont NULLables
            'form'       => null,
            'dosage'     => null,
            'unit'       => null,
            'pack_size'  => 1,
            'is_active'  => true,
            'min_stock'  => 0,
            'max_stock'  => 0,
            'buy_price'  => 0,
            'sell_price' => 0,
            'tax_rate'   => 0,
        ]);
    }

    /**
     * Si le nom de la DCI change, on ne touche qu'aux articles
     * qui n'ont PAS de name explicite (name NULL ou vide).
     */
    public function updated(Dci $dci): void
    {
        if ($dci->wasChanged('name')) {
            DB::table('pharma_articles')
                ->where('dci_id', $dci->id)
                ->where(function ($q) {
                    $q->whereNull('name')->orWhere('name', '');
                })
                ->update([
                    'name'       => $dci->name,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Génère un code unique de type "PARACETAMOL" ou "PARACETAMOL_2" si collision.
     */
    private function makeUniqueArticleCode(string $name): string
    {
        // base : UPPER + slug sans séparateurs spéciaux
        $base = Str::upper(Str::slug($name, '_'));
        $code = $base !== '' ? $base : 'ARTICLE';

        // garantit l'unicité
        $suffix = 1;
        while (
            PharmaArticle::where('code', $code)->exists()
        ) {
            $suffix++;
            $code = $base . '_' . $suffix;
        }

        return $code;
    }
}
