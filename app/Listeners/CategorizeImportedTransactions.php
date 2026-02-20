<?php

namespace App\Listeners;

use App\Events\ImportCompleted;
use App\Services\CategorizationService;

class CategorizeImportedTransactions
{
    public function __construct(private CategorizationService $categorizationService) {}

    public function handle(ImportCompleted $event): void
    {
        $this->categorizationService->categorize($event->import);
    }
}
