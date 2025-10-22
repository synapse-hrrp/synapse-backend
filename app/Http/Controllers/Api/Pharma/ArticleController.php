<?php

namespace App\Http\Controllers\Api\Pharma;

use App\Http\Controllers\Controller;
use App\Models\Pharmacie\PharmaArticle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ArticleController extends Controller
{
    // GET /pharma/articles?q=&active=1&per_page=
    public function index(Request $request)
    {
        $q = trim((string)$request->query('q',''));
        $active = $request->query('active');
        $per = max(1, min((int)$request->query('per_page',20), 100));

        $query = PharmaArticle::query()->with('dci:id,name');

        if ($q !== '') $query->search($q);
        if (!is_null($active)) {
            $activeBool = filter_var($active, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if (!is_null($activeBool)) $query->where('is_active', $activeBool);
        }

        $query->orderBy('name');
        return response()->json($query->paginate($per));
    }

    public function options(Request $request)
    {
        $items = PharmaArticle::active()->orderBy('name')->get(['id','name','code','sell_price','tax_rate']);
        return response()->json($items);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'dci_id'     => ['nullable','exists:dcis,id'],
            'name'       => ['required','string','max:190'],
            'code'       => ['required','string','max:100','unique:pharma_articles,code'],
            'form'       => ['nullable','string','max:100'],
            'dosage'     => ['nullable','string','max:100'],
            'unit'       => ['nullable','string','max:50'],
            'pack_size'  => ['nullable','integer','min:1'],
            'is_active'  => ['sometimes','boolean'],
            'min_stock'  => ['nullable','integer','min:0'],
            'max_stock'  => ['nullable','integer','min:0'],
            'buy_price'  => ['nullable','numeric','min:0'],
            'sell_price' => ['nullable','numeric','min:0'],
            'tax_rate'   => ['nullable','numeric','min:0'],
        ]);
        $a = PharmaArticle::create($data);
        return response()->json($a->load('dci:id,name'), 201);
    }

    public function show(PharmaArticle $article)
    {
        $article->load(['dci:id,name','lots:id,article_id,lot_number,expires_at,quantity']);
        return response()->json($article);
    }

    public function update(Request $request, PharmaArticle $article)
    {
        $data = $request->validate([
            'dci_id'     => ['nullable','exists:dcis,id'],
            'name'       => ['sometimes','string','max:190'],
            'code'       => ['sometimes','string','max:100', Rule::unique('pharma_articles','code')->ignore($article->id)],
            'form'       => ['nullable','string','max:100'],
            'dosage'     => ['nullable','string','max:100'],
            'unit'       => ['nullable','string','max:50'],
            'pack_size'  => ['nullable','integer','min:1'],
            'is_active'  => ['sometimes','boolean'],
            'min_stock'  => ['nullable','integer','min:0'],
            'max_stock'  => ['nullable','integer','min:0'],
            'buy_price'  => ['nullable','numeric','min:0'],
            'sell_price' => ['nullable','numeric','min:0'],
            'tax_rate'   => ['nullable','numeric','min:0'],
        ]);
        $article->update($data);
        return response()->json($article->load('dci:id,name'));
    }

    public function destroy(PharmaArticle $article)
    {
        $article->delete();
        return response()->json(['message'=>'Article supprimé']);
    }
}
