<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $facture->numero }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        .mb-2 { margin-bottom: 8px; }
        .mb-4 { margin-bottom: 16px; }
        table { width:100%; border-collapse: collapse; }
        th, td { border:1px solid #ddd; padding:6px; text-align:left; }
        th { background:#f3f3f3; }
        .right { text-align:right; }
    </style>
</head>
<body>

    <h2 class="mb-2">Facture {{ $facture->numero }}</h2>

    <div class="mb-4">
        <div><strong>Date :</strong> {{ $facture->created_at?->format('d/m/Y H:i') }}</div>
        <div><strong>Statut :</strong> {{ $facture->statut }}</div>
        <div><strong>Devise :</strong> {{ $facture->devise }}</div>
    </div>

    <div class="mb-4">
        <h3>Patient</h3>
        @if($patient)
            <div><strong>ID :</strong> {{ $patient->id }}</div>
            @if(property_exists($patient, 'full_name') || isset($patient->full_name))
                <div><strong>Nom :</strong> {{ $patient->full_name }}</div>
            @else
                <div><strong>Nom :</strong> {{ ($patient->nom ?? '') . ' ' . ($patient->postnom ?? '') . ' ' . ($patient->prenom ?? '') }}</div>
            @endif
        @else
            <em>Aucun patient associé</em>
        @endif
    </div>

    <h3>Lignes</h3>
    <table class="mb-4">
        <thead>
            <tr>
                <th>Désignation</th>
                <th class="right">Qté</th>
                <th class="right">PU</th>
                <th class="right">Montant</th>
            </tr>
        </thead>
        <tbody>
        @forelse($facture->lignes as $l)
            <tr>
                <td>{{ $l->designation }}</td>
                <td class="right">{{ number_format((float)$l->quantite, 2, '.', ' ') }}</td>
                <td class="right">{{ number_format((float)$l->prix_unitaire, 2, '.', ' ') }}</td>
                <td class="right">{{ number_format((float)$l->montant, 2, '.', ' ') }}</td>
            </tr>
        @empty
            <tr><td colspan="4"><em>Aucune ligne</em></td></tr>
        @endforelse
        </tbody>
    </table>

    <table>
        <tr>
            <th class="right" style="width:75%">Total</th>
            <td class="right" style="width:25%">{{ number_format((float)$facture->montant_total, 2, '.', ' ') }} {{ $facture->devise }}</td>
        </tr>
        <tr>
            <th class="right">Payé</th>
            <td class="right">{{ number_format((float)$facture->montant_paye, 2, '.', ' ') }} {{ $facture->devise }}</td>
        </tr>
        <tr>
            <th class="right">Reste dû</th>
            <td class="right">{{ number_format((float)$facture->montant_du, 2, '.', ' ') }} {{ $facture->devise }}</td>
        </tr>
    </table>

</body>
</html>
