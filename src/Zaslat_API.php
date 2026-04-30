<?php

namespace ZaslatShipping;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Zaslat_API {

	const ENDPOINT = 'https://www.zaslat.cz/api/v1/rates/get';

	private string $api_key;

	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Fetch selectable rates from the Zaslat API.
	 *
	 * @throws \RuntimeException on HTTP error or non-200 API status.
	 */
	public function get_rates( array $from, array $to, array $packages, string $currency = 'CZK', string $pickup_date = '' ): array {
		if ( empty( $pickup_date ) ) {
			$pickup_date = gmdate( 'Y-m-d', strtotime( '+1 day' ) );
		}

		$body = wp_json_encode( [
			'currency'    => $currency,
			'pickup_date' => $pickup_date,
			'from'        => $from,
			'to'          => $to,
			'packages'    => $packages,
		] );

		error_log( 'Zaslat Shipping API Request: ' . $body );

		$response = wp_remote_post( self::ENDPOINT, [
			'headers' => [
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
				'X-Apikey'     => $this->api_key,
			],
			'body'    => $body,
			'timeout' => 45,
		] );

		if ( is_wp_error( $response ) ) {
			error_log( 'Zaslat Shipping API HTTP Error: ' . $response->get_error_message() );
			throw new \RuntimeException( $response->get_error_message() );
		}

		$response_body = wp_remote_retrieve_body( $response );
		error_log( 'Zaslat Shipping API Response: ' . $response_body );

		$data = json_decode( $response_body, true );

		if ( empty( $data ) || ( $data['status'] ?? 0 ) !== 200 ) {
			throw new \RuntimeException( $data['message'] ?? 'Zaslat API error' );
		}

		return array_values( array_filter(
			$data['rates'] ?? [],
			function ( array $rate ) {
				return ! empty( $rate['selectable'] );
			}
		) );
	}
}
