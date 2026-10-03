<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    protected $fillable = [
        'invoice_no',
        'user_id',
        'subtotal',
        'discount_type',
        'discount_value',
        'discount_amount',
        'total',
        'payment_method',
        'amount_paid',
        'change_amount',
        'status',
        'voided_by',
        'void_reason',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'discount_value' => 'integer',
            'discount_amount' => 'integer',
            'total' => 'integer',
            'amount_paid' => 'integer',
            'change_amount' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}