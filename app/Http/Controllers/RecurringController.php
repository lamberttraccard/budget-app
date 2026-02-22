<?php

namespace App\Http\Controllers;

use App\Services\RecurringDetectionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecurringController extends Controller
{
    public function __construct(
        private readonly RecurringDetectionService $recurringDetectionService,
    ) {}

    public function index(Request $request): Response
    {
        $account = $request->string('account')->toString() ?: null;
        if ($account !== null && ! in_array($account, ['personal', 'professional'], true)) {
            $account = null;
        }
        return Inertia::render('recurring', [
            'recurringMerchants' => Inertia::defer(fn () => $this->recurringDetectionService->detectRecurring($account)),
            'currentAccount' => $account,
        ]);
    }
}
