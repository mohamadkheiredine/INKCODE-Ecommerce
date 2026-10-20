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

    /** Before Shipped, and not already Cancelled. */
    public function isCancellable(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed, self::Preparing, self::ReadyForShipping], true);
    }

    /**
     * Whether an Order in this status holds Reserved units: it was Confirmed and has not
     * reached the Deduction stage yet (where its Reserved units leave On-hand).
     */
    public function holdsReservation(DeductionStage $deductionStage): bool
    {
        $flow = self::flow();
        $position = array_search($this, $flow, true);

        return $position !== false
            && $position >= array_search(self::Confirmed, $flow, true)
            && $position < array_search(self::from($deductionStage->value), $flow, true);
    }
}
