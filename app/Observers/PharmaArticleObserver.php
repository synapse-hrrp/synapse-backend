<?php

namespace App\Observers;

use App\Models\Pharmacie\PharmaArticle;

class PharmaArticleObserver
{
    public function creating(PharmaArticle $article): void
    {
        // Si aucun nom fourni mais DCI présente -> générer un nom “propre”
        if (blank($article->name) && $article->dci_id) {
            $article->loadMissing('dci');
            if ($article->dci) {
                $article->name = self::makeNameFromDCI($article);
            }
        }
    }

    public function updating(PharmaArticle $article): void
    {
        // Si quelqu’un efface le name par erreur, on regénère (tant qu’il y a une DCI)
        if (blank($article->name) && $article->dci_id) {
            $article->loadMissing('dci');
            if ($article->dci) {
                $article->name = self::makeNameFromDCI($article);
            }
        }
    }

    private static function makeNameFromDCI(PharmaArticle $a): string
    {
        // Construit un label pro : "DCI dosage unit (form)"
        $parts = [];
        if ($a->dci?->name) $parts[] = $a->dci->name;
        if ($a->dosage)     $parts[] = trim($a->dosage . ' ' . ($a->unit ?? ''));
        $label = trim(implode(' ', array_filter($parts)));

        if ($a->form) {
            $label .= ' (' . $a->form . ')';
        }

        // Fallback si tout est vide (très rare)
        return $label !== '' ? $label : 'Article ' . ($a->code ?? '');
    }
}
