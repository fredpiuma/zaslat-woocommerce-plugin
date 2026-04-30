<?php

/**
 * Standalone test runner — works without PHPUnit or XML extensions.
 * Usage: php tests/run.php
 */

require_once __DIR__ . '/bootstrap.php';

use ZaslatShipping\Zaslat_API;

$api_key = getenv('ZASLAT_API_KEY') ?: 'xxxxxxxxx';
$api     = new Zaslat_API($api_key);

$package = [['weight' => 27, 'length' => 160, 'width' => 70, 'height' => 40]];

$ts          = strtotime('+1 day');
$dow         = (int) date('N', $ts);
if ($dow === 6) {
	$ts = strtotime('+2 days', $ts);
}
if ($dow === 7) {
	$ts = strtotime('+1 day', $ts);
}
$pickup_date = date('Y-m-d', $ts);

$from_cz = ['country' => 'CZ', 'zip' => '12000', 'city' => 'Prague', 'street' => 'Polská 1361/38'];
$to_cz   = ['country' => 'CZ', 'zip' => '12202', 'city' => 'Praha 2', 'street' => 'Husova 165/5'];

// Cross-border destinations: direct border + 1 country away
$destinations = [
	// Direct border
	'SK' => ['label' => 'Slovakia (Bratislava) — direct border',       'zip' => '81101', 'city' => 'Bratislava'],
	'DE' => ['label' => 'Germany (Berlin) — direct border',            'zip' => '10115', 'city' => 'Berlin'],
	'AT' => ['label' => 'Austria (Vienna) — direct border',            'zip' => '1010',  'city' => 'Vienna'],
	'PL' => ['label' => 'Poland (Warsaw) — direct border',             'zip' => '00-001', 'city' => 'Warsaw'],
	// 1 country away
	'FR' => ['label' => 'France (Paris) — 1 away via DE/AT',           'zip' => '75001', 'city' => 'Paris'],
	'CH' => ['label' => 'Switzerland (Zurich) — 1 away via DE/AT',     'zip' => '8001',  'city' => 'Zurich'],
	'LI' => ['label' => 'Liechtenstein (Vaduz) — 1 away via AT',       'zip' => '9490',  'city' => 'Vaduz'],
	'IT' => ['label' => 'Italy (Milan) — 1 away via AT',               'zip' => '20121', 'city' => 'Milan'],
	'SI' => ['label' => 'Slovenia (Ljubljana) — 1 away via AT',        'zip' => '1000',  'city' => 'Ljubljana'],
	'HU' => ['label' => 'Hungary (Budapest) — 1 away via AT/SK',       'zip' => '1051',  'city' => 'Budapest'],
	'UA' => ['label' => 'Ukraine (Kyiv) — 1 away via SK/PL',           'zip' => '01001', 'city' => 'Kyiv'],
	'BY' => ['label' => 'Belarus (Minsk) — 1 away via PL',             'zip' => '220000', 'city' => 'Minsk'],
	'LT' => ['label' => 'Lithuania (Vilnius) — 1 away via PL',         'zip' => '01001', 'city' => 'Vilnius'],
	'RU' => ['label' => 'Russia/Kaliningrad — 1 away via PL',          'zip' => '236000', 'city' => 'Kaliningrad'],
	'DK' => ['label' => 'Denmark (Copenhagen) — 1 away via DE',        'zip' => '1050',  'city' => 'Copenhagen'],
	'LU' => ['label' => 'Luxembourg (Luxembourg City) — 1 away via DE', 'zip' => '1009',  'city' => 'Luxembourg'],
	'BE' => ['label' => 'Belgium (Brussels) — 1 away via DE',          'zip' => '1000',  'city' => 'Brussels'],
	'NL' => ['label' => 'Netherlands (Amsterdam) — 1 away via DE',     'zip' => '1011',  'city' => 'Amsterdam'],
	'HR' => ['label' => 'Croatia (Zagreb) — 1 away via AT/SI',         'zip' => '10000', 'city' => 'Zagreb'],
	'RS' => ['label' => 'Serbia (Belgrade) — 1 away via HU',           'zip' => '11000', 'city' => 'Belgrade'],
	'RO' => ['label' => 'Romania (Bucharest) — 1 away via HU',         'zip' => '010011', 'city' => 'Bucharest'],
];

$passed = 0;
$failed = 0;

function assert_not_empty($value, $message)
{
	global $passed, $failed;
	if (! empty($value)) {
		echo "\033[32m  ✓ $message\033[0m\n";
		$passed++;
	} else {
		echo "\033[31m  ✗ $message\033[0m\n";
		$failed++;
	}
}

function assert_greater_than($expected, $actual, $message)
{
	global $passed, $failed;
	if ($actual > $expected) {
		echo "\033[32m  ✓ $message\033[0m\n";
		$passed++;
	} else {
		echo "\033[31m  ✗ $message (got $actual)\033[0m\n";
		$failed++;
	}
}

// -----------------------------------------------------------------------
// Test 1: CZ → CZ (domestic)
// -----------------------------------------------------------------------
echo "\nTest 1: Domestic CZ → CZ\n";
try {
	$rates = $api->get_rates($from_cz, $to_cz, $package, 'CZK', $pickup_date);
	assert_not_empty($rates, 'Returned at least one selectable rate');

	foreach ($rates as $rate) {
		$carrier = $rate['carrier'] . ' / ' . $rate['service'];
		assert_greater_than(0, $rate['price']['value'], "$carrier price > 0 ({$rate['price']['value']} {$rate['price']['currency']})");
	}
} catch (\RuntimeException $e) {
	echo "\033[31m  ✗ API call failed: " . $e->getMessage() . "\033[0m\n";
	$failed++;
}

// -----------------------------------------------------------------------
// Tests 2–N: Cross-border routes
// -----------------------------------------------------------------------
$test_num = 2;
foreach ($destinations as $code => $dest) {
	$to = ['country' => $code, 'zip' => $dest['zip'], 'city' => $dest['city']];

	echo "\nTest $test_num: CZ → $code — {$dest['label']}\n";
	try {
		$rates = $api->get_rates($from_cz, $to, $package, 'CZK', $pickup_date);
		assert_not_empty($rates, 'Returned at least one selectable rate');

		foreach ($rates as $rate) {
			$carrier = $rate['carrier'] . ' / ' . $rate['service'];
			assert_greater_than(0, $rate['price']['value'], "$carrier price > 0 ({$rate['price']['value']} {$rate['price']['currency']})");

			if (! empty($rate['delivery_date'])) {
				$is_future = strtotime($rate['delivery_date']) > strtotime('yesterday');
				if ($is_future) {
					echo "\033[32m  ✓ $carrier delivery date not in the past ({$rate['delivery_date']})\033[0m\n";
					$passed++;
				} else {
					echo "\033[31m  ✗ $carrier delivery date is in the past ({$rate['delivery_date']})\033[0m\n";
					$failed++;
				}
			}
		}
	} catch (\RuntimeException $e) {
		echo "\033[31m  ✗ API call failed: " . $e->getMessage() . "\033[0m\n";
		$failed++;
	}

	$test_num++;
}

// -----------------------------------------------------------------------
// Final test: non-selectable carriers are excluded
// -----------------------------------------------------------------------
echo "\nTest $test_num: Non-selectable carriers are excluded from results\n";
try {
	$rates = $api->get_rates($from_cz, $to_cz, $package, 'CZK', $pickup_date);

	$all_selectable = true;
	foreach ($rates as $rate) {
		if (empty($rate['selectable'])) {
			echo "\033[31m  ✗ {$rate['carrier']} ({$rate['service']}) is not selectable but appeared in results\033[0m\n";
			$failed++;
			$all_selectable = false;
		}
	}

	if ($all_selectable) {
		echo "\033[32m  ✓ All " . count($rates) . " result(s) are selectable\033[0m\n";
		$passed++;
	}
} catch (\RuntimeException $e) {
	echo "\033[31m  ✗ Unexpected exception: " . $e->getMessage() . "\033[0m\n";
	$failed++;
}

// -----------------------------------------------------------------------
echo "\n" . str_repeat('-', 40) . "\n";
echo "\033[32mPassed: $passed\033[0m  \033[31mFailed: $failed\033[0m\n\n";
exit($failed > 0 ? 1 : 0);
