<?php

define( 'ABSPATH', __DIR__ . '/' );

define( 'ZASLAT_SHIPPING_DEFAULT_DIMS', [
	'length' => 160,
	'width'  => 70,
	'height' => 40,
	'weight' => 27,
] );

require_once __DIR__ . '/../vendor/autoload.php';

// ---------------------------------------------------------------------------
// Minimal WordPress function stubs — only what Zaslat_API needs
// ---------------------------------------------------------------------------

class WP_Error {
	private $code;
	private $message;

	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_message() {
		return $this->message;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}

function wp_json_encode( $data ) {
	return json_encode( $data );
}

function wp_remote_retrieve_body( $response ) {
	return $response['body'] ?? '';
}

function wp_remote_post( $url, $args = [] ) {
	$headers = [];
	foreach ( $args['headers'] ?? [] as $key => $value ) {
		$headers[] = "$key: $value";
	}

	$ch = curl_init();
	curl_setopt_array( $ch, [
		CURLOPT_URL            => $url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POST           => true,
		CURLOPT_POSTFIELDS     => $args['body'] ?? '',
		CURLOPT_HTTPHEADER     => $headers,
		CURLOPT_TIMEOUT        => $args['timeout'] ?? 30,
	] );

	$body  = curl_exec( $ch );
	$error = curl_error( $ch );
	curl_close( $ch );

	if ( $error ) {
		return new WP_Error( 'curl_error', $error );
	}

	return [ 'body' => $body ];
}
