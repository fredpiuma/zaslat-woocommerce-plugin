<?php

namespace ZaslatShipping;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Currency_Converter {

	const TRANSIENT_KEY  = 'zaslat_exchange_czk_usd';
	const FALLBACK_KEY   = 'zaslat_exchange_czk_usd_fallback';
	const CACHE_TTL      = 172800; // 2 days
	const RATE_API       = 'https://api.frankfurter.app/latest?from=CZK&to=USD';
	const HARDCODED_RATE = 0.043; // approximate fallback when all else fails

	public function czk_to_usd( float $czk_amount ): float {
		return round( $czk_amount * $this->get_rate(), 2 );
	}

	private function get_rate(): float {
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( $cached !== false ) {
			return (float) $cached;
		}

		$response = wp_remote_get( self::RATE_API, [ 'timeout' => 5 ] );

		if ( ! is_wp_error( $response ) ) {
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			$rate = (float) ( $data['rates']['USD'] ?? 0 );

			if ( $rate > 0 ) {
				set_transient( self::TRANSIENT_KEY, $rate, self::CACHE_TTL );
				update_option( self::FALLBACK_KEY, $rate, false );
				return $rate;
			}
		}

		$fallback = (float) get_option( self::FALLBACK_KEY, 0 );
		return $fallback > 0 ? $fallback : self::HARDCODED_RATE;
	}
}
