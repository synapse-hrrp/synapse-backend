@php
    // Petit helper format XAF
    function money_xaf($v){ return number_format((float)$v, 0, ',', ' ') . ' XAF'; }

    $patient = optional($facture->patient);
    $patientName = trim(($patient->nom ?? '') . ' ' . ($patient->prenom ?? ''));
@endphp
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Ticket règlement</title>
<style>
    *{ box-sizing:border-box; }
    body{ font-family: DejaVu Sans, Arial, sans-serif; font-size:12px; margin:0; padding:8px; }
    .center{ text-align:center; }
    .bold{ font-weight:700; }
    .mt{ margin-top:6px; }
    .mb{ margin-bottom:6px; }
    .hr{ border-top:1px dashed #333; margin:6px 0; }
    .row{ display:flex; justify-content:space-between; }
    .small{ font-size:11px; color:#555; }
</style>
</head>
<body>

<div class="center bold">{{ $meta['facility_name'] }}</div>
@if(!empty($meta['facility_address']))<div class="center small">{{ $meta['facility_address'] }}</div>@endif
@if(!empty($meta['facility_phone']))  <div class="center small">Tél: {{ $meta['facility_phone'] }}</div>@endif
<div class="center small">Poste: {{ $meta['workstation'] ?? 'N/A' }}</div>

<div class="hr"></div>

<div class="center bold">RÉGLEMENT</div>
<div class="center small">Ticket: {{ $reglement->id }}</div>
<div class="center small">Facture: {{ $facture->numero ?? $facture->id }}</div>
<div class="center small">Date: {{ optional($reglement->created_at)->format('d/m/Y H:i') }}</div>

<div class="hr"></div>

@if($patientName || $patient->telephone)
    <div class="bold">Patient</div>
    @if($patientName)<div>{{ $patientName }}</div>@endif
    @if($patient->telephone)<div class="small">Tél: {{ $patient->telephone }}</div>@endif
    <div class="hr"></div>
@endif

<div class="row"><div>Montant réglé</div><div class="bold">{{ money_xaf($reglement->montant) }}</div></div>
<div class="row"><div>Mode</div><div>{{ strtoupper($reglement->mode) }}</div></div>
@if($reglement->reference)<div class="row"><div>Référence</div><div>{{ $reglement->reference }}</div></div>@endif

<div class="hr"></div>

<div class="row"><div>Total facture</div><div>{{ money_xaf($total) }}</div></div>
<div class="row"><div>Déjà payé</div><div>{{ money_xaf($paid) }}</div></div>
<div class="row"><div>Reste à payer</div><div class="bold">{{ money_xaf($due) }}</div></div>

<div class="hr"></div>

<div class="small">
    Caissier: {{ optional($cashier)->name ?? '—' }}<br>
    Session: {{ optional($session)->id ?? '—' }} / Poste: {{ optional($session)->workstation ?? '—' }}<br>
    Imprimé par: {{ $meta['printed_by'] ?? '—' }} le {{ $meta['printed_at']->format('d/m/Y H:i') }}
</div>

<div class="center small mt">Merci de votre visite</div>

</body>
</html>
