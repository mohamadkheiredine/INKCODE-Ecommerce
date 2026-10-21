<?php

namespace App\Console\Commands;

use App\Actions\Orders\ConfirmOrder;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Order;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Hidden;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * One worker of {@see DemoRaceCommand}: waits for the shared start time, then confirms one Order
 * exactly as the API does. Prints `confirmed` or the refusal's error code.
 */
#[Signature('demo:race-worker {order} {startAt} {user=0}')]
#[Description('Confirm one Order at a given moment (used by demo:race)')]
#[Hidden]
class DemoRaceWorkerCommand extends Command
{
    public function handle(ConfirmOrder $confirmOrder, AuthFactory $auth): int
    {
        $user = User::query()->find((int) $this->argument('user'));
        if ($user !== null) {
            $auth->guard()->setUser($user);
        }
        $order = Order::query()->findOrFail((int) $this->argument('order'));

        time_sleep_until((float) $this->argument('startAt'));

        try {
            $confirmOrder->handle($order);
            $this->output->write('confirmed');
        } catch (BusinessRuleViolation $refusal) {
            $this->output->write($refusal->errorCode);
        }

        return self::SUCCESS;
    }
}
