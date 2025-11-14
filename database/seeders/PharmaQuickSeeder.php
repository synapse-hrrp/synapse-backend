<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Pharmacie\Dci;
use App\Models\Pharmacie\PharmaArticle;
use App\Models\Pharmacie\PharmaLot;
use Illuminate\Support\Facades\Storage;

class PharmaQuickSeeder extends Seeder
{
    public function run(): void
    {
        // Assure que le dossier public/pharma/articles existe
        Storage::disk('public')->makeDirectory('pharma/articles');

        // ---- DCIs de base ----
        $amox = Dci::firstOrCreate(['name' => 'Amoxicilline']);
        $para = Dci::firstOrCreate(['name' => 'Paracétamol']);

        // ---- Articles ----
        $a1 = PharmaArticle::updateOrCreate(
            ['name' => 'Amoxicilline 500'],
            [
                'dci_id'     => $amox->id,
                'form'       => 'gélule',
                'dosage'     => '500',
                'unit'       => 'mg',
                'sell_price' => 1000,
                'tax_rate'   => 0,
                'is_active'  => true,
            ]
        );

        $a2 = PharmaArticle::updateOrCreate(
            ['name' => 'Paracétamol 500'],
            [
                'dci_id'     => $para->id,
                'form'       => 'comprimé',
                'dosage'     => '500',
                'unit'       => 'mg',
                'sell_price' => 500,
                'tax_rate'   => 0,
                'is_active'  => true,
            ]
        );

        // ---- Lots de test ----
        PharmaLot::updateOrCreate(
            ['article_id' => $a1->id, 'lot_number' => 'L2025-00001'],
            [
                'quantity'   => 50,
                'sell_price' => 1000,
            ]
        );

        PharmaLot::updateOrCreate(
            ['article_id' => $a2->id, 'lot_number' => 'L2025-00002'],
            [
                'quantity'   => 120,
                'sell_price' => 500,
            ]
        );

        // ---- Feedback console ----
        $this->command->info("✅ PharmaQuickSeeder exécuté avec succès !");
        $this->command->info(" - Articles créés : {$a1->name}, {$a2->name}");
        $this->command->info(" - Dossiers de stockage : storage/app/public/pharma/articles/");
    }
}
