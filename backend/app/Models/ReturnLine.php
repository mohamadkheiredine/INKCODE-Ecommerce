<?php

namespace App\Models;

use App\Enums\RestockDecision;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Some units of one Order line sent back in a Return, with their Restock decision once
 * the Return is Received.
 *
 * @property int $id
 * @property int $return_id
 * @property int $order_line_id
 * @property int $quantity
 * @property RestockDecision|null $restock_decision
 * @property Carbon|null $decided_at
 * @property-read OrderReturn $orderReturn
 * @property-read OrderLine $orderLine
 */
#[Fillable(['order_line_id', 'quantity', 'restock_decision', 'decided_at'])]
class ReturnLine extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'restock_decision' => RestockDecision::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OrderReturn, $this>
     */
    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class, 'return_id');
    }

    /**
     * @return BelongsTo<OrderLine, $this>
     */
    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(OrderLine::class);
    }

    /**
     * What the Customer paid for these units: the line total × returned ÷ ordered, rounded
     * half up to the cent (the whole line returns exactly its total).
     */
    public function paidAmount(): string
    {
        $line = $this->orderLine;
        if ($this->quantity === $line->quantity) {
            return $line->line_total;
        }

        $share = bcdiv(bcmul($line->line_total, (string) $this->quantity, 10), (string) $line->quantity, 10);

        return bcadd($share, '0.005', 2);
    }
}
