<?php

/*
|--------------------------------------------------------------------------
| One worker of StockConcurrencyTest
|--------------------------------------------------------------------------
|
| Boots the application in its own PHP process (so its own database
| connection), waits for the start time every worker shares, then records one
| Receipt exactly as the API does. Prints "ok" or the refusal's error code.
|
| php receive-worker.php <start at, unix time> <warehouse id> <variant id> <quantity> <unit cost>
|
*/

use App\Actions\Inventory\CreateReceipt;
use App\Exceptions\BusinessRuleViolation;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $startAt, $warehouseId, $variantId, $quantity, $unitCost] = $argv;

time_sleep_until((float) $startAt);

try {
    $app->make(CreateReceipt::class)->handle([
        'warehouse_id' => (int) $warehouseId,
        'supplier_reference' => 'CONCURRENT',
        'notes' => null,
        'lines' => [['variant_id' => (int) $variantId, 'quantity' => (int) $quantity, 'unit_cost' => $unitCost]],
    ]);
    echo 'ok';
} catch (BusinessRuleViolation $refusal) {
    echo $refusal->errorCode;
}
