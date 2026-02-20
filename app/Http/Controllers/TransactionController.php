<?php

namespace App\Http\Controllers;

use App\Events\CategoryCorrected;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
        ]);

        $oldCategoryId = $transaction->category_id;

        DB::transaction(function () use ($transaction, $validated, $oldCategoryId) {
            $transaction->update([
                'category_id' => $validated['category_id'],
            ]);

            if ($oldCategoryId !== (int) $validated['category_id']) {
                CategoryCorrected::dispatch($transaction, $oldCategoryId, (int) $validated['category_id']);
            }
        });

        return back();
    }
}
