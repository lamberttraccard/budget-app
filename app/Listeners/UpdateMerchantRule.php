<?php

namespace App\Listeners;

use App\Events\CategoryCorrected;
use App\Models\MerchantRule;

class UpdateMerchantRule
{
    public function handle(CategoryCorrected $event): void
    {
        MerchantRule::updateOrCreate(
            ['merchant_pattern' => mb_strtolower(trim($event->transaction->description))],
            ['category_id' => $event->newCategoryId],
        );
    }
}
