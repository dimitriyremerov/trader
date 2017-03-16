#!/usr/bin/env php
<?php
require dirname(__DIR__) . '/vendor/autoload.php';

$base = $argv[1] ?? 'eur';
$percents = isset($argv[2]) ? [$argv[2]] : [2, 1];

$allPairs = [
    'btc_usd',
    'btc_rur',
    'btc_eur',
    'ltc_btc',
    'ltc_usd',
    'ltc_rur',
    'ltc_eur',
    'nmc_btc',
    'nmc_usd',
    'nvc_btc',
    'nvc_usd',
    'usd_rur',
    'eur_usd',
    'eur_rur',
    'ppc_btc',
    'ppc_usd',
    'dsh_btc',
    'dsh_rur',
    'dsh_eur',
    'dsh_ltc',
    'dsh_eth',
    'eth_btc',
    'eth_usd',
    'eth_eur',
    'eth_ltc',
    'eth_rur',
];

$pairs = array_filter($allPairs, function ($arg) use ($base) {
    return strpos($arg, $base) !== false;
});

if (!$pairs) {
    die('incorrect base');
}

$client = new \GuzzleHttp\Client(['base_uri' => 'https://btc-e.com/api/3/']);

$data = [];
foreach ($pairs as $pair) {
    $response = $client->get(sprintf('depth/%s', $pair));
    $data += json_decode($response->getBody()->getContents(), true);
}

$baseNeeded = [];
$baseNeededCur = [];
$log = [];
foreach (['up' => true, 'down' => false] as $upLabel => $up) {
    $baseNeeded[$upLabel] = [];
    foreach ($data as $pair => $pairData) {
        $currencies = explode('_', $pair);

        $dir = $currencies[0] === $base;
        $currency = $currencies[(int) $dir];

        $pairDataDir = $pairData[($up xor $dir) ? 'bids' : 'asks'];
        $currentPrice = null;
        foreach ($pairDataDir as list($price, $value)) {
            $currentPrice || $currentPrice = $price;
            $baseVal = round($dir ? $value : $price * $value, 2);
            foreach ($percents as $change) {
                if (!isset($baseNeeded[$upLabel][$change])) {
                    $baseNeeded[$upLabel][$change] = 0;
                }
                if (!isset($baseNeededCur[$upLabel][$change][$currency])) {
                    $baseNeededCur[$upLabel][$change][$currency] = 0;
                }
                if (max($price, $currentPrice) / min($price, $currentPrice) < 1 + $change / 100) {
                    $baseNeeded[$upLabel][$change] += $baseVal;
                    $baseNeededCur[$upLabel][$change][$currency] += $baseVal;
                    $log[$upLabel][$change][] = sprintf(
                        'You will need to %s %s for %u %s to push %s the %s value for %u%% (%s offer of %f for %f, %s val %f)',
                        $up ? 'sell' : 'buy',
                        $currency,
                        $baseVal,
                        $base,
                        $upLabel,
                        $base,
                        $change,
                        $pair,
                        $value,
                        $price,
                        $base,
                        $baseVal
                    );
                } else {
                    break;
                }
            }
        }
    }
}

echo yaml_emit($baseNeeded);
echo yaml_emit($baseNeededCur);

//echo implode(PHP_EOL, array_pop($log['up'])) . PHP_EOL;
