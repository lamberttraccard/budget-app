<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantRule extends Model
{
    /** @use HasFactory<\Database\Factories\MerchantRuleFactory> */
    use HasFactory;

    protected $fillable = ['merchant_pattern', 'category_id'];

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
