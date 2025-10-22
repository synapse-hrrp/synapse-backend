<?php

namespace App\Services\Finance;

use App\Models\Pharmacie\PharmaCart;
// ⚠️ Adapte ces imports si tes modèles facture s'appellent autrement
use App\Models\Finance\Invoice;
use App\Models\Finance\InvoiceLine;

class InvoiceGateway
{
    public function createFromCart(PharmaCart $cart): Invoice
    {
        // 1) Crée la facture (mappe les infos utiles du panier)
        $invoice = Invoice::create([
            'number'        => Invoice::nextNumber(),     // ta logique existante
            'customer_name' => $cart->customer_name ?? 'Client comptoir',
            'total_ht'      => $cart->total_ht,
            'total_ttc'     => $cart->total_ttc,
            'status'        => 'issued',
            // ... ajoute les colonnes nécessaires de ton module finance
        ]);

        // 2) Lignes
        foreach ($cart->lines as $line) {
            InvoiceLine::create([
                'invoice_id' => $invoice->id,
                'article_id' => $line->article_id,
                'description'=> $line->article->name,
                'qty'        => $line->quantity,
                'unit_price' => $line->unit_price,
                'tax_rate'   => $line->tax_rate ?? 0,
                'total'      => $line->total, // ou qty * unit_price * (1+tax)
            ]);
        }

        return $invoice->fresh('lines');
    }
}
