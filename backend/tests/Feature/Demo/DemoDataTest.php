<?php

use Illuminate\Support\Facades\DB;

/**
 * The "must be zero" queries of docs/demo-data-checks.sql, keyed by the column each one
 * returns. The reviewer runs the same file against a seeded database, so the test and the
 * manual check never drift apart.
 *
 * @return array<string, string>
 */
function demoDataChecks(): array
{
    $sql = (string) file_get_contents(dirname(__DIR__, 4).'/docs/demo-data-checks.sql');
    $mustBeZero = substr($sql, (int) strpos($sql, '-- Must be zero'));
    $withoutComments = (string) preg_replace('/^\s*--.*$/m', '', $mustBeZero);

    $checks = [];
    foreach (array_filter(array_map('trim', explode(';', $withoutComments))) as $query) {
        preg_match('/COUNT\(\*\) AS (\w+)/', $query, $name);
        $checks[$name[1]] = $query;
    }

    return $checks;
}

it('reads every consistency check from the shared checks file', function () {
    expect(demoDataChecks())->toHaveCount(13)
        ->toHaveKeys(['balances_not_matching_movements', 'orders_not_matching_ledger', 'orders_before_customer_joined']);
});

it('seeds a demo store that passes every consistency check', function () {
    seedStore(demoMode: true);

    $failed = [];
    foreach (demoDataChecks() as $name => $query) {
        $count = (int) array_values((array) DB::selectOne($query))[0];
        if ($count !== 0) {
            $failed[$name] = $count;
        }
    }

    expect($failed)->toBe([]);
});
