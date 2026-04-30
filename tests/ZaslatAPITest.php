<?php

namespace ZaslatShipping\Tests;

use PHPUnit\Framework\TestCase;
use ZaslatShipping\Zaslat_API;

class ZaslatAPITest extends TestCase {

	private Zaslat_API $api;

	private static function standard_package() {
		return [ [
			'weight' => 27,
			'length' => 160,
			'width'  => 70,
			'height' => 40,
		] ];
	}

	private static function get_from_cz() {
		return [ 'country' => 'CZ', 'zip' => '12000', 'city' => 'Prague', 'street' => 'Polská 1361/38' ];
	}

	private static function get_to_cz() {
		return [ 'country' => 'CZ', 'zip' => '12202', 'city' => 'Praha 2', 'street' => 'Husova 165/5' ];
	}

	private static function next_working_day() {
		$ts  = strtotime( '+1 day' );
		$dow = (int) date( 'N', $ts );
		if ( $dow === 6 ) { $ts = strtotime( '+2 days', $ts ); }
		if ( $dow === 7 ) { $ts = strtotime( '+1 day', $ts ); }
		return date( 'Y-m-d', $ts );
	}

	protected function setUp(): void {
		$api_key = getenv( 'ZASLAT_API_KEY' );

		if ( empty( $api_key ) ) {
			$this->markTestSkipped( 'ZASLAT_API_KEY env variable not set.' );
		}

		$this->api = new Zaslat_API( $api_key );
	}

	// -----------------------------------------------------------------------

	/**
	 * @testdox Domestic shipment (CZ → CZ) returns selectable rates with valid prices
	 */
	public function test_domestic_czech_rates() {
		$rates = $this->api->get_rates(
			self::get_from_cz(),
			self::get_to_cz(),
			self::standard_package(),
			'CZK',
			self::next_working_day()
		);

		$this->assertNotEmpty( $rates, 'Expected at least one selectable rate for CZ → CZ.' );

		foreach ( $rates as $rate ) {
			$this->assertArrayHasKey( 'carrier', $rate );
			$this->assertArrayHasKey( 'service', $rate );
			$this->assertArrayHasKey( 'price', $rate );
			$this->assertGreaterThan( 0, $rate['price']['value'],
				"Price for {$rate['carrier']} ({$rate['service']}) must be positive."
			);
			$this->assertNotEmpty( $rate['price']['currency'],
				"Currency must be set for {$rate['carrier']}."
			);
		}
	}

	/**
	 * @testdox Non-selectable carriers are filtered out of results
	 */
	public function test_non_selectable_carriers_are_excluded() {
		$rates = $this->api->get_rates(
			self::get_from_cz(),
			self::get_to_cz(),
			self::standard_package(),
			'CZK',
			self::next_working_day()
		);

		$this->assertNotEmpty( $rates );

		foreach ( $rates as $rate ) {
			$this->assertTrue(
				$rate['selectable'],
				"Carrier {$rate['carrier']} ({$rate['service']}) should not appear — it is not selectable."
			);
		}
	}

	// -----------------------------------------------------------------------
	// Cross-border routes — direct border + 1 country away
	// -----------------------------------------------------------------------

	public static function cross_border_destinations(): array {
		return [
			// Direct border
			'SK — Slovakia (Bratislava)'         => [ 'SK', '81101',  'Bratislava' ],
			'DE — Germany (Berlin)'              => [ 'DE', '10115',  'Berlin' ],
			'AT — Austria (Vienna)'              => [ 'AT', '1010',   'Vienna' ],
			'PL — Poland (Warsaw)'               => [ 'PL', '00-001', 'Warsaw' ],
			// 1 country away
			'FR — France (Paris)'                => [ 'FR', '75001',  'Paris' ],
			'CH — Switzerland (Zurich)'          => [ 'CH', '8001',   'Zurich' ],
			'LI — Liechtenstein (Vaduz)'         => [ 'LI', '9490',   'Vaduz' ],
			'IT — Italy (Milan)'                 => [ 'IT', '20121',  'Milan' ],
			'SI — Slovenia (Ljubljana)'          => [ 'SI', '1000',   'Ljubljana' ],
			'HU — Hungary (Budapest)'            => [ 'HU', '1051',   'Budapest' ],
			'UA — Ukraine (Kyiv)'                => [ 'UA', '01001',  'Kyiv' ],
			'BY — Belarus (Minsk)'               => [ 'BY', '220000', 'Minsk' ],
			'LT — Lithuania (Vilnius)'           => [ 'LT', '01001',  'Vilnius' ],
			'RU — Russia/Kaliningrad'            => [ 'RU', '236000', 'Kaliningrad' ],
			'DK — Denmark (Copenhagen)'          => [ 'DK', '1050',   'Copenhagen' ],
			'LU — Luxembourg (Luxembourg City)'  => [ 'LU', '1009',   'Luxembourg' ],
			'BE — Belgium (Brussels)'            => [ 'BE', '1000',   'Brussels' ],
			'NL — Netherlands (Amsterdam)'       => [ 'NL', '1011',   'Amsterdam' ],
			'HR — Croatia (Zagreb)'              => [ 'HR', '10000',  'Zagreb' ],
			'RS — Serbia (Belgrade)'             => [ 'RS', '11000',  'Belgrade' ],
			'RO — Romania (Bucharest)'           => [ 'RO', '010011', 'Bucharest' ],
		];
	}

	/**
	 * @dataProvider cross_border_destinations
	 * @testdox Cross-border CZ → $country ($city) returns selectable rates with valid prices
	 */
	public function test_cross_border_rates( string $country, string $zip, string $city ) {
		$to = [ 'country' => $country, 'zip' => $zip, 'city' => $city ];

		$rates = $this->api->get_rates(
			self::get_from_cz(),
			$to,
			self::standard_package(),
			'CZK',
			self::next_working_day()
		);

		$this->assertNotEmpty( $rates,
			"Expected at least one selectable rate for CZ → $country ($city)."
		);

		foreach ( $rates as $rate ) {
			$this->assertGreaterThan( 0, $rate['price']['value'],
				"Price for {$rate['carrier']} ({$rate['service']}) must be positive."
			);

			if ( ! empty( $rate['delivery_date'] ) ) {
				$this->assertGreaterThan( strtotime( 'yesterday' ), strtotime( $rate['delivery_date'] ),
					"Delivery date for {$rate['carrier']} should not be in the past."
				);
			}
		}
	}
}
