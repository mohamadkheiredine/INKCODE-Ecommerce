<?php

namespace App\Enums;

/**
 * Where an Order is in its life. It moves forward one step at a time
 * (Pending → Confirmed → Preparing → Ready for shipping → Shipped → Delivered) and may be
 * Cancelled any time before it is Shipped. This enum is the one place the allowed moves
 * are defined; actions ask {@see canMoveTo()} before changing a status.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Preparing = 'preparing';
    case ReadyForShipping = 'ready_for_shipping';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /**
     * The forward flow, in order (Cancelled is not part of it).
     *
     * @return list<self>
     */
    public static function flow(): array
    {
        return [self::Pending, self::Confirmed, self::Preparing, self::ReadyForShipping, self::Shipped, self::Delivered];
    }

    /**
     * The one status this may move forward to, or null at the end of the flow.
     */
    public function next(): ?self
    {
        $position = array_search($this, self::flow(), true);

        return $position === false ? null : (self::flow()[$position + 1] ?? null);
    }

    public function canMoveTo(self $status): bool
    {
        return $status === self::Cancelled ? $this->isCancellable() : $this->next() === $status;
    }

    /**
     * Every status this may move to: the next step, then Cancelled while that is allowed.
     *
     * @return list<self>
     */
    public function allowedMoves(): array
    {
        return array_values(array_filter([$this->next(), $this->isCancellable() ? self::Cancelled : null]));
    }

    /**
     * Pending and Confirmed Orders may still change their lines, quantities and addresses.
     * From Preparing onward (and once Cancelled) the Order is a Locked order.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed], true);
    }

    /** Before Shipped, and not already Cancelled. */
    public function isCancellable(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed, self::Preparing, self::ReadyForShipping], true);
    }
}
