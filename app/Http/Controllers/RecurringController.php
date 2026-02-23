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
        $accountIds = $this->parseAccountIds($request);

        return Inertia::render('recurring', [
            'recurringMerchants' => Inertia::defer(fn () => $this->recurringDetectionService->detectRecurring($accountIds)),
            'selectedAccountIds' => $accountIds ?? [],
        ]);
    }
}
