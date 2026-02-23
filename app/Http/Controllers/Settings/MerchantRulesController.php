<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\MerchantRule;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MerchantRulesController extends Controller
{
    public function index(): Response
    {
        $rules = MerchantRule::with('category')
            ->selectRaw('merchant_rules.*, (SELECT COUNT(*) FROM transactions WHERE LOWER(description) = merchant_rules.merchant_pattern) as match_count')
            ->orderBy('merchant_pattern')
            ->get()
            ->map(fn (MerchantRule $rule) => [
                'id' => $rule->id,
                'merchantPattern' => $rule->merchant_pattern,
                'categoryId' => $rule->category_id,
                'categoryName' => $rule->category->name,
                'categoryColor' => $rule->category->color,
                'categoryIcon' => $rule->category->icon,
                'matchCount' => (int) $rule->match_count,
            ]);

        return Inertia::render('settings/merchant-rules', [
            'rules' => $rules,
        ]);
    }

    public function destroy(MerchantRule $merchantRule): RedirectResponse
    {
        $merchantRule->delete();

        return back();
    }

    public function apply(MerchantRule $merchantRule): RedirectResponse
    {
        $count = Transaction::whereRaw('LOWER(description) = ?', [mb_strtolower($merchantRule->merchant_pattern)])
            ->update(['category_id' => $merchantRule->category_id]);

        session()->flash('updated_count', $count);

        return back();
    }
}
