{{-- resources/views/caisse/tickets/z-report.blade.php --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Z-Report — {{ $meta['date'] ?? ($meta['date'] ?? '') }}</title>
    <style>
        /* ====== Mise en page ticket (80mm) ====== */
        html, body {
            margin: 0;
            padding: 0;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
            font-size: 12px;
            color: #111;
        }
        .ticket {
            width: 226pt; /* ~80mm */
            padding: 10pt 8pt;
        }
        .center    { text-align: center; }
        .right     { text-align: right; }
        .muted     { color: #555; }
        .bold      { font-weight: 700; }
        .small     { font-size: 11px; }
        .xs        { font-size: 10px; }
        .lg        { font-size: 14px; }
        .mt-2      { margin-top: 8pt; }
        .mt-3      { margin-top: 12pt; }
        .mb-1      { margin-bottom: 4pt; }
        .mb-2      { margin-bottom: 8pt; }
        .divider   { border-top: 1px dashed #999; margin: 8pt 0; }
        .kv        { display: flex; justify-content: space-between; margin: 2pt 0; }
        table      { width: 100%; border-collapse: collapse; }
        th, td     { padding: 3pt 0; vertical-align: top; }
        th         { text-align: left; border-bottom: 1px dashed #999; }
        tfoot td   { border-top: 1px dashed #999; padding-top: 4pt; }
        .mono      { font-variant-numeric: tabular-nums; }
    </style>
</head>
<body>
@php
    // Données attendues depuis CashZReportController::json()
    $facility = config('app.name', 'Clinique');
    $meta   = $meta   ?? [];
    $kpis   = $kpis   ?? ['payments'=>0,'total_amount'=>0,'avg_ticket'=>0];
    $rows   = $data   ?? [];

    // Agrégations locales (par mode / par caissier)
    $byMode = [];
    $byCashier = [];
    foreach ($rows as $r) {
        $mode = $r['mode'] ?? 'n/a';
        $cashierName = $r['cashier']['name'] ?? 'n/a';
        $amount = (float)($r['montant'] ?? 0);

        if (!isset($byMode[$mode]))      $byMode[$mode]      = ['payments'=>0,'amount'=>0];
        if (!isset($byCashier[$cashierName])) $byCashier[$cashierName] = ['payments'=>0,'amount'=>0];

        $byMode[$mode]['payments']++;
        $byMode[$mode]['amount'] += $amount;

        $byCashier[$cashierName]['payments']++;
        $byCashier[$cashierName]['amount'] += $amount;
    }
@endphp

<div class="ticket">
    {{-- En-tête établissement --}}
    <div class="center">
        <div class="lg bold">{{ $facility }}</div>
        @if(config('app.address')) <div class="small muted">{{ config('app.address') }}</div> @endif
        @if(config('app.phone'))   <div class="small muted">Tél: {{ config('app.phone') }}</div> @endif
    </div>

    <div class="divider"></div>

    {{-- Titre / Métadonnées --}}
    <div class="center bold">Z-REPORT (Clôture journalière)</div>
    <div class="kv small">
        <span>Date</span>
        <span class="mono">{{ $meta['date'] ?? now()->toDateString() }}</span>
    </div>
    @if(!empty($meta['workstation']))
    <div class="kv small">
        <span>Poste</span>
        <span class="mono">{{ $meta['workstation'] }}</span>
    </div>
    @endif

    {{-- KPIs --}}
    <div class="divider"></div>
    <div class="kv">
        <span>Nb paiements</span>
        <span class="mono bold">{{ number_format((int)($kpis['payments'] ?? 0), 0, ',', ' ') }}</span>
    </div>
    <div class="kv">
        <span>Total encaissé</span>
        <span class="mono bold">{{ number_format((float)($kpis['total_amount'] ?? 0), 2, ',', ' ') }}</span>
    </div>
    <div class="kv">
        <span>Ticket moyen</span>
        <span class="mono">{{ number_format((float)($kpis['avg_ticket'] ?? 0), 2, ',', ' ') }}</span>
    </div>

    {{-- Détail des paiements --}}
    <div class="divider"></div>
    <div class="bold mb-1">Détail paiements</div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Heure</th>
                <th>Mode</th>
                <th class="right">Montant</th>
            </tr>
        </thead>
        <tbody>
        @forelse($rows as $i => $r)
            <tr>
                <td class="mono xs">{{ $i + 1 }}</td>
                <td class="mono xs">
                    @php
                        $dt = $r['created_at'] ?? null;
                        $time = $dt ? \Illuminate\Support\Carbon::parse($dt)->format('H:i') : '--:--';
                    @endphp
                    {{ $time }}
                </td>
                <td class="xs">{{ $r['mode'] ?? 'n/a' }}</td>
                <td class="right mono xs">{{ number_format((float)($r['montant'] ?? 0), 2, ',', ' ') }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="center muted small">Aucun paiement</td></tr>
        @endforelse
        </tbody>
        @if(($kpis['payments'] ?? 0) > 0)
        <tfoot>
            <tr>
                <td colspan="3" class="right bold">TOTAL</td>
                <td class="right mono bold">{{ number_format((float)($kpis['total_amount'] ?? 0), 2, ',', ' ') }}</td>
            </tr>
        </tfoot>
        @endif
    </table>

    {{-- Regroupements --}}
    @if(count($byMode))
        <div class="divider"></div>
        <div class="bold mb-1">Par mode</div>
        @foreach($byMode as $mode => $g)
            <div class="kv small">
                <span>{{ $mode }} ({{ $g['payments'] }})</span>
                <span class="mono">{{ number_format($g['amount'], 2, ',', ' ') }}</span>
            </div>
        @endforeach
    @endif

    @if(count($byCashier))
        <div class="divider"></div>
        <div class="bold mb-1">Par caissier</div>
        @foreach($byCashier as $name => $g)
            <div class="kv small">
                <span>{{ $name }} ({{ $g['payments'] }})</span>
                <span class="mono">{{ number_format($g['amount'], 2, ',', ' ') }}</span>
            </div>
        @endforeach
    @endif

    <div class="divider"></div>
    <div class="center xs muted">
        Imprimé le {{ now()->format('d/m/Y H:i') }}
    </div>
</div>
</body>
</html>
