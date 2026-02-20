<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fallback Category
    |--------------------------------------------------------------------------
    |
    | The slug of the category to assign when AI categorization has low
    | confidence or fails entirely. This ensures transactions are never
    | left without a category after processing.
    |
    */

    'fallback_category_slug' => 'other',

    /*
    |--------------------------------------------------------------------------
    | Confidence Threshold
    |--------------------------------------------------------------------------
    |
    | Minimum confidence score (0.0 to 1.0) required to accept an AI
    | categorization suggestion. Below this threshold, the transaction
    | will be assigned to the fallback category instead.
    |
    */

    'confidence_threshold' => 0.5,

    /*
    |--------------------------------------------------------------------------
    | Batch Size
    |--------------------------------------------------------------------------
    |
    | Number of transactions to include in each AI categorization request.
    | Larger batches reduce API calls but increase prompt size and latency.
    |
    */

    'batch_size' => 50,

];
