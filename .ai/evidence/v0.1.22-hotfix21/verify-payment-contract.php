<?php

declare(strict_types=1);

if (! function_exists('__')) {
    function __(string $message, array $replace = []): string
    {
        return strtr($message, array_combine(array_map(static fn (string $key): string => ':'.$key, array_keys($replace)), array_values($replace)) ?: []);
    }
}

require dirname(__DIR__, 3).'/vendor/autoload.php';

use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Retail\Actions\RetailSaleAction;

$cash = new PaymentMethod(['type' => 'cash', 'code' => 'CASH']);
$card = new PaymentMethod(['type' => 'card', 'code' => 'CARD']);
$wallet = new PaymentMethod(['type' => 'wallet', 'code' => 'WALLET']);
$action = (new ReflectionClass(RetailSaleAction::class))->newInstanceWithoutConstructor();
$method = new ReflectionMethod(RetailSaleAction::class, 'validatedTenderAllocation');

$invoke = static fn (array $parts): array => $method->invoke($action, $parts, '25.00');
$expectFailure = static function (array $parts, string $name) use ($invoke): void {
    try {
        $invoke($parts);
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException("{$name} unexpectedly passed");
};

$expectFailure([['method' => $cash, 'amount' => '0', 'tendered' => '20']], 'cash underpayment');
$exact = $invoke([['method' => $cash, 'amount' => '0', 'tendered' => '25']]);
if ($exact[0]['amount'] !== '25.00' || $exact[0]['tendered'] !== '25.00') throw new RuntimeException('exact cash normalization failed');
$over = $invoke([['method' => $cash, 'amount' => '0', 'tendered' => '1000']]);
if ($over[0]['amount'] !== '25.00' || bcsub($over[0]['tendered'], $over[0]['amount'], 2) !== '975.00') throw new RuntimeException('cash change failed');
$split = $invoke([
    ['method' => $card, 'amount' => '10', 'tendered' => null],
    ['method' => $cash, 'amount' => '0', 'tendered' => '20'],
]);
if ($split[0]['amount'] !== '10.00' || $split[1]['amount'] !== '15.00' || bcsub($split[1]['tendered'], $split[1]['amount'], 2) !== '5.00') throw new RuntimeException('split allocation failed');
$electronic = $invoke([
    ['method' => $card, 'amount' => '10', 'tendered' => null],
    ['method' => $wallet, 'amount' => '15', 'tendered' => null],
]);
if ($electronic[0]['amount'] !== '10.00' || $electronic[1]['amount'] !== '15.00') throw new RuntimeException('electronic split failed');
$expectFailure([['method' => $card, 'amount' => '25.01', 'tendered' => null]], 'overallocation');
$expectFailure([['method' => $card, 'amount' => '0', 'tendered' => null]], 'zero part');
$expectFailure([['method' => $card, 'amount' => '-1', 'tendered' => null]], 'negative part');
$expectFailure([['method' => $card, 'amount' => 'not-money', 'tendered' => null]], 'malformed part');

echo "SERVER_PAYMENT_CONTRACT=PASS\n";
