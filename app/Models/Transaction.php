<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount_cents',
        'date',
        'description',
        'dedup_hash',
        'category_id',
        'account_id',
        'import_id',
        'bank_metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'date' => 'date',
            'bank_metadata' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}
