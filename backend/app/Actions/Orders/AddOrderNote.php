<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\OrderNote;
use App\Models\User;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\DB;

/**
 * Adds a note to an Order's timeline, signed by the User who wrote it. Locked orders
 * still take notes.
 */
final class AddOrderNote
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function handle(Order $order, User $author, string $body): OrderNote
    {
        return DB::transaction(function () use ($order, $author, $body): OrderNote {
            $note = new OrderNote(['body' => $body, 'user_id' => $author->id]);
            $note->order()->associate($order);
            $note->save();

            $this->auditTrail->created($note);

            return $note;
        });
    }
}
