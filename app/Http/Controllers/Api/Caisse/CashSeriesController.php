// app/Http/Controllers/Api/Caisse/CashSeriesController.php
namespace App\Http\Controllers\Api\Caisse;

use App\Http\Controllers\Controller;
use App\Models\Reglement;
use App\Support\ServiceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class CashSeriesController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'period' => ['nullable', Rule::in(['day','week','month','year','range'])],
            'on'     => ['nullable','date'],
            'date_from' => ['nullable','date'],
            'date_to'   => ['nullable','date'],
            'group'  => ['nullable', Rule::in(['service','cashier','mode'])],
        ]);

        // fenêtre
        $period = $validated['period'] ?? 'day';
        $on = $validated['on'] ? Carbon::parse($validated['on']) : Carbon::today();
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $from = $request->filled('date_from') ? Carbon::parse($request->input('date_from'))->startOfDay() : (clone $on)->startOfDay();
            $to   = $request->filled('date_to')   ? Carbon::parse($request->input('date_to'))->endOfDay()     : (clone $on)->endOfDay();
        } else {
            [$from,$to] = match($period) {
                'week'  => [(clone $on)->startOfWeek(),  (clone $on)->endOfWeek()],
                'month' => [(clone $on)->startOfMonth(), (clone $on)->endOfMonth()],
                'year'  => [(clone $on)->startOfYear(),  (clone $on)->endOfYear()],
                default => [(clone $on)->startOfDay(),   (clone $on)->endOfDay()],
            };
        }

        $bucket = match($period) {
            'day'   => '%Y-%m-%d %H:00:00',
            'range' => ($from->diffInDays($to) <= 2 ? '%Y-%m-%d %H:00:00' : '%Y-%m-%d'),
            default => '%Y-%m-%d',
        };

        $q = Reglement::query()->whereBetween('created_at', [$from,$to]);

        /** @var ServiceAccess $access */
        $access  = app(ServiceAccess::class);
        $allowed = $access->allowedServiceIds($request->user());
        if (!$access->isGlobal($request->user())) {
            $q->whereIn('service_id', $allowed ?: [-1]);
        }

        $group = $validated['group'] ?? null;
        if ($group === 'service') {
            $rows = (clone $q)
                ->selectRaw("DATE_FORMAT(created_at, ?) as bucket, service_id as id, COUNT(*) payments, COALESCE(SUM(montant),0) total_amount", [$bucket])
                ->groupBy('bucket','service_id')
                ->orderBy('bucket')
                ->get();
        } elseif ($group === 'cashier') {
            $rows = (clone $q)
                ->selectRaw("DATE_FORMAT(created_at, ?) as bucket, cashier_id as id, COUNT(*) payments, COALESCE(SUM(montant),0) total_amount", [$bucket])
                ->groupBy('bucket','cashier_id')
                ->orderBy('bucket')
                ->get();
        } elseif ($group === 'mode') {
            $rows = (clone $q)
                ->selectRaw("DATE_FORMAT(created_at, ?) as bucket, mode as id, COUNT(*) payments, COALESCE(SUM(montant),0) total_amount", [$bucket])
                ->groupBy('bucket','id')
                ->orderBy('bucket')
                ->get();
        } else {
            $rows = (clone $q)
                ->selectRaw("DATE_FORMAT(created_at, ?) as bucket, COUNT(*) payments, COALESCE(SUM(montant),0) total_amount", [$bucket])
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->get();
        }

        return response()->json([
            'meta' => [
                'period'    => $period,
                'date_from' => $from->toDateString(),
                'date_to'   => $to->toDateString(),
                'bucket'    => $bucket,
                'group'     => $group,
            ],
            'series' => $rows,
        ], 200);
    }
}
