<?php

namespace App\Http\Controllers;

use App\Events\CategoryCorrected;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'apply_to_all' => ['sometimes', 'boolean'],
        ]);

        $oldCategoryId = $transaction->category_id;
        $applyToAll = $validated['apply_to_all'] ?? false;
        $bulkCount = 0;

        DB::transaction(function () use ($transaction, $validated, $oldCategoryId, $applyToAll, &$bulkCount) {
            $transaction->update([
                'category_id' => $validated['category_id'],
            ]);

            if ($oldCategoryId !== (int) $validated['category_id']) {
                CategoryCorrected::dispatch($transaction, $oldCategoryId, (int) $validated['category_id']);
            }

            if ($applyToAll) {
                $bulkCount = Transaction::whereRaw('LOWER(description) = ?', [mb_strtolower(trim($transaction->description))])
                    ->where('id', '!=', $transaction->id)
                    ->update(['category_id' => $validated['category_id']]);
            }
        });

        if ($applyToAll) {
            session()->flash('updated_count', $bulkCount + 1);
        }

        return back();
    }

    public function matchCount(Request $request): JsonResponse
    {
        $request->validate([
            'description' => ['required', 'string', 'min:1'],
        ]);

        $count = Transaction::whereRaw('LOWER(description) = ?', [mb_strtolower(trim($request->input('description')))])
            ->count();

        return response()->json(['count' => $count]);
    }
}
