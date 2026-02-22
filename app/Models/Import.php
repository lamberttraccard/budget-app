<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    use HasFactory;

    protected $fillable = ['file_name', 'bank', 'status', 'batch_id', 'transaction_count', 'date_from', 'date_to', 'imported_at'];

    protected function casts(): array
    {
        return [
            'imported_at' => 'datetime',
            'transaction_count' => 'integer',
            'date_from' => 'date',
            'date_to' => 'date',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function isCategorizing(): bool
    {
        return $this->status === 'categorizing';
    }

    public function isCategorized(): bool
    {
        return $this->status === 'categorized';
    }

    public function hasUncategorizedTransactions(): bool
    {
        return $this->transactions()->whereNull('category_id')->exists();
    }
}
