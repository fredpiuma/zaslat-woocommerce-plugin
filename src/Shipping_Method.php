<?php

namespace ZaslatShipping;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shipping_Method extends \WC_Shipping_Method {

	public function __construct( int $instance_id = 0 ) {
		$this->id                 = 'zaslat_shipping';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = 'Zaslat';
		$this->method_description = 'Real-time shipping quotes via Zaslat API.';
		$this->supports           = [ 'shipping-zones', 'instance-settings' ];
		$this->tax_status         = 'none';

		error_log( "Zaslat Shipping: Method class instantiated (Instance ID: $instance_id)." );

		$this->init();
	}

	public function init() {
		$this->init_form_fields();
		$this->init_settings();
		$this->title   = $this->get_option( 'title', $this->method_title );
		$this->enabled = $this->get_option( 'enabled', 'yes' );

		error_log( "Zaslat Shipping: Method initialized. Instance ID: {$this->instance_id}, Enabled: {$this->enabled}" );

		add_action(
			'woocommerce_update_options_shipping_' . $this->id,
			[ $this, 'process_admin_options' ]
		);
	}

	public function init_form_fields() {
		$this->instance_form_fields = [
			'title' => [
				'title'   => 'Method title',
				'type'    => 'text',
				'default' => 'Zaslat',
			],
		];
	}

	public function is_available( $package ) {
		error_log( "Zaslat Shipping: Checking is_available for Instance ID: {$this->instance_id}" );
		return true;
	}

	public function calculate_shipping( $package = [] ) {
		error_log( "Zaslat Shipping: calculate_shipping() called for Instance ID: {$this->instance_id}" );
		$api_key = get_option( 'zaslat_shipping_api_key', '' );
		if ( empty( $api_key ) ) {
			error_log( 'Zaslat Shipping: API Key is missing.' );
			return;
		}

		$destination = $package['destination'] ?? [];
		$to_country  = $destination['country'] ?? '';
		if ( empty( $to_country ) ) {
			error_log( 'Zaslat Shipping: Destination country is missing.' );
			return;
		}

		$from = [ 
			'country' => 'CZ',
			'zip'     => '12000', // Zip corrigido para Praga 120 00 (Polská)
			'city'    => 'Prague',
			'street'  => 'Polská 1361/38'
		];
		
		$to   = [ 'country' => $to_country ];
		
		if ( ! empty( $destination['postcode'] ) ) {
			$to['zip'] = $destination['postcode'];
		}
		if ( ! empty( $destination['city'] ) ) {
			$to['city'] = $destination['city'];
		}
		if ( ! empty( $destination['address_1'] ) ) {
			$to['street'] = $destination['address_1'];
		}
		if ( ! empty( $destination['address_2'] ) ) {
			$to['street'] .= ' ' . $destination['address_2'];
		}

		$packages = $this->build_packages( $package['contents'] ?? [] );

		error_log( 'Zaslat Shipping: Calculating for ' . wp_json_encode( $to ) . ' with ' . count( $packages ) . ' package(s).' );

		$cache     = new Rate_Cache();
		$cache_key = $cache->build_key( $from, $to, $packages );
		$rates     = $cache->get( $cache_key );

		if ( $rates === null ) {
			error_log( 'Zaslat Shipping: Cache miss, calling API...' );
			$api   = new Zaslat_API( $api_key );
			$rates = [];

			foreach ( $this->working_days_ahead( 4 ) as $pickup_date ) {
				try {
					$fetched = $api->get_rates( $from, $to, $packages, 'CZK', $pickup_date );
					if ( ! empty( $fetched ) ) {
						$rates = $fetched;
						error_log( 'Zaslat Shipping: API returned ' . count( $rates ) . " rate(s) for $pickup_date." );
						break;
					}
					error_log( "Zaslat Shipping: No rates for $pickup_date, trying next day." );
				} catch ( \RuntimeException $e ) {
					error_log( "Zaslat Shipping: API error for $pickup_date: " . $e->getMessage() );
				}
			}

			if ( ! empty( $rates ) ) {
				$cache->set( $cache_key, $rates );
			} else {
				$rates = $cache->get_fallback( $cache_key ) ?? [];
				if ( ! empty( $rates ) ) {
					error_log( 'Zaslat Shipping: Using fallback rates.' );
				}
			}
		} else {
			error_log( 'Zaslat Shipping: Using cached rates (' . count( $rates ) . ').' );
		}

		if ( empty( $rates ) ) {
			error_log( 'Zaslat Shipping: No rates found to display.' );
			return;
		}

		$converter = new Currency_Converter();

		foreach ( $rates as $rate ) {
			$czk_price = (float) ( $rate['price']['value'] ?? 0 );
			if ( $czk_price <= 0 ) {
				continue;
			}

			$label = sprintf( '%s — %s', $rate['carrier'], $rate['service'] );
			if ( ! empty( $rate['delivery_date'] ) ) {
				$label .= sprintf( ' (%s)', $rate['delivery_date'] );
			}

			$cost = $converter->czk_to_usd( $czk_price );
			
			error_log( "Zaslat Shipping: Adding rate: $label | CZK: $czk_price | USD: $cost" );

			$this->add_rate( [
				'id'        => $this->build_rate_id( (int) $rate['service_id'] ),
				'label'     => $label,
				'cost'      => $cost,
				'meta_data' => [
					'zaslat_service_id'    => $rate['service_id'],
					'zaslat_carrier'       => $rate['carrier'],
					'zaslat_delivery_date' => $rate['delivery_date'] ?? '',
				],
			] );
		}
	}

	private function build_packages( array $contents ): array {
		$defaults = ZASLAT_SHIPPING_DEFAULT_DIMS;
		$packages = [];

		foreach ( $contents as $item ) {
			/** @var \WC_Product|null $product */
			$product  = $item['data'] ?? null;
			$quantity = max( 1, (int) ( $item['quantity'] ?? 1 ) );

			if ( $product instanceof \WC_Product ) {
				$weight    = (float) $product->get_weight();
				$length    = (float) $product->get_length();
				$width     = (float) $product->get_width();
				$height    = (float) $product->get_height();

				error_log( sprintf( 'Zaslat Shipping: Product %d dims: %fx%fx%f, Weight: %f', $product->get_id(), $length, $width, $height, $weight ) );

				$weight_kg = $weight > 0 ? wc_get_weight( $weight, 'kg' ) : $defaults['weight'];
				$length_cm = $length > 0 ? wc_get_dimension( $length, 'cm' ) : $defaults['length'];
				$width_cm  = $width  > 0 ? wc_get_dimension( $width, 'cm' )  : $defaults['width'];
				$height_cm = $height > 0 ? wc_get_dimension( $height, 'cm' ) : $defaults['height'];
			} else {
				$weight_kg = $defaults['weight'];
				$length_cm = $defaults['length'];
				$width_cm  = $defaults['width'];
				$height_cm = $defaults['height'];
			}

			for ( $i = 0; $i < $quantity; $i++ ) {
				$packages[] = [
					'weight' => $weight_kg,
					'length' => $length_cm,
					'width'  => $width_cm,
					'height' => $height_cm,
				];
			}
		}

		return $packages ?: [ [
			'weight' => $defaults['weight'],
			'length' => $defaults['length'],
			'width'  => $defaults['width'],
			'height' => $defaults['height'],
		] ];
	}

	private function build_rate_id( int $service_id ) {
		return sprintf( '%s:%d:%d', $this->id, $this->instance_id, $service_id );
	}

	/**
	 * Returns the next $count working days (Mon–Fri) as Y-m-d strings,
	 * starting from tomorrow.
	 */
	private function working_days_ahead( int $count ): array {
		$dates = [];
		$ts    = strtotime( '+1 day' );
		while ( count( $dates ) < $count ) {
			if ( (int) date( 'N', $ts ) <= 5 ) {
				$dates[] = date( 'Y-m-d', $ts );
			}
			$ts = strtotime( '+1 day', $ts );
		}
		return $dates;
	}
}
