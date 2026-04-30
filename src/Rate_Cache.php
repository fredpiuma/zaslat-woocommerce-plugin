<?php

namespace ZaslatShipping;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rate_Cache {

	private const FALLBACK_PREFIX = 'zaslat_fb_';

	private int $ttl;

	public function __construct( int $ttl = 259200 ) { // 3 days
		$this->ttl = $ttl;
	}

	public function get( string $key ): ?array {
		$data = get_transient( $key );
		return $data !== false ? $data : null;
	}

	/**
	 * Returns last known good rates regardless of TTL.
	 * Used as fallback when the API is unreachable.
	 */
	public function get_fallback( string $key ): ?array {
		$data = get_option( self::FALLBACK_PREFIX . $key );
		return is_array( $data ) && ! empty( $data ) ? $data : null;
	}

	public function set( string $key, array $data ): void {
		if ( empty( $data ) ) {
			return; // Do not cache empty rates
		}
		set_transient( $key, $data, $this->ttl );
		update_option( self::FALLBACK_PREFIX . $key, $data, false );
	}

	public function build_key( array $from, array $to, array $packages ): string {
		if ( ! empty( $to['zip'] ) ) {
			$to['zip'] = $this->zone_prefix( $to['zip'] );
		}
		$payload = [
			'v3'       => true, // Bump when key derivation changes
			'from'     => $from,
			'to'       => $to,
			'packages' => $packages,
		];
		return 'zaslat_rates_' . md5( (string) wp_json_encode( $payload ) );
	}

	/**
	 * Truncates a zip code to its postal zone prefix for cache grouping.
	 * 4-digit zips → first 2 digits; 5+ digit zips → first 3 digits.
	 */
	private function zone_prefix( string $zip ): string {
		$digits = preg_replace( '/\D/', '', $zip );
		return strlen( $digits ) <= 4
			? substr( $digits, 0, 2 )
			: substr( $digits, 0, 3 );
	}
}
