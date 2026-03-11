<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HandlesBrazilianDateTimes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class TransactionProduct extends Model
{
    use HasFactory;
    use HandlesBrazilianDateTimes;

    protected $table = 'transaction_products';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'transaction_id',
        'product_id',
        'quantity',
        'unit_amount',
        'total_amount',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'transaction_id' => 'integer',
        'product_id' => 'integer',
        'quantity' => 'integer',
        'unit_amount' => 'integer',
        'total_amount' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            if ($model->quantity !== null && $model->unit_amount !== null) {
                $calculatedTotal = $model->quantity * $model->unit_amount;

                if ($calculatedTotal < 1) {
                    throw new InvalidArgumentException('The calculated total_amount must be greater than zero.');
                }

                $model->attributes['total_amount'] = $calculatedTotal;
            }
        });
    }

    /**
     * Returns the transaction associated with this item row.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Returns the product associated with this item row.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Mutator for transaction_id.
     *
     * @throws InvalidArgumentException
     */
    public function setTransactionIdAttribute(mixed $value): void
    {
        $transactionId = $this->normalizeInteger($value, 'transaction_id', 1);

        if ($transactionId === null) {
            throw new InvalidArgumentException('Transaction id is required.');
        }

        $this->attributes['transaction_id'] = $transactionId;
    }

    /**
     * Mutator for product_id.
     *
     * @throws InvalidArgumentException
     */
    public function setProductIdAttribute(mixed $value): void
    {
        $productId = $this->normalizeInteger($value, 'product_id', 1);

        if ($productId === null) {
            throw new InvalidArgumentException('Product id is required.');
        }

        $this->attributes['product_id'] = $productId;
    }

    /**
     * Mutator for quantity.
     *
     * @throws InvalidArgumentException
     */
    public function setQuantityAttribute(mixed $value): void
    {
        $quantity = $this->normalizeInteger($value, 'quantity', 1);

        if ($quantity === null) {
            throw new InvalidArgumentException('Quantity is required.');
        }

        $this->attributes['quantity'] = $quantity;
    }

    /**
     * Mutator for unit_amount.
     *
     * Amount must be stored in minor units.
     *
     * @throws InvalidArgumentException
     */
    public function setUnitAmountAttribute(mixed $value): void
    {
        $unitAmount = $this->normalizeInteger($value, 'unit_amount', 1);

        if ($unitAmount === null) {
            throw new InvalidArgumentException('Unit amount is required.');
        }

        $this->attributes['unit_amount'] = $unitAmount;
    }

    /**
     * Mutator for total_amount.
     *
     * The value is accepted, but it may be recalculated automatically
     * during the saving lifecycle.
     *
     * @throws InvalidArgumentException
     */
    public function setTotalAmountAttribute(mixed $value): void
    {
        $totalAmount = $this->normalizeInteger($value, 'total_amount', 1);

        if ($totalAmount === null) {
            throw new InvalidArgumentException('Total amount is required.');
        }

        $this->attributes['total_amount'] = $totalAmount;
    }

    /**
     * Scope items by transaction id.
     */
    public function scopeByTransaction(Builder $query, int $transactionId): Builder
    {
        return $query->where('transaction_id', $transactionId);
    }

    /**
     * Scope items by product id.
     */
    public function scopeByProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Returns the unit amount in major units.
     *
     * Example:
     * 1099 => 10.99
     */
    public function unitAmountInDecimal(): float
    {
        return $this->unit_amount / 100;
    }

    /**
     * Returns the total amount in major units.
     *
     * Example:
     * 2198 => 21.98
     */
    public function totalAmountInDecimal(): float
    {
        return $this->total_amount / 100;
    }
}