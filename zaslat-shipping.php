<?php

/**
 * Plugin Name: Zaslat Shipping
 * Description: Real-time shipping quotes from Zaslat API at WooCommerce checkout.
 * Version: 1.2.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * License: GPLv2 or later
 * Author: Frederico de Castro
 * Author URI: https://github.com/fredpiuma
 */

if (! defined('ABSPATH')) {
	exit;
}

error_log( 'Zaslat Shipping: Plugin entry point loaded.' );

define('ZASLAT_SHIPPING_DEFAULT_DIMS', [
	'length' => 160,
	'width'  => 60,
	'height' => 40,
	'weight' => 27,
]);

spl_autoload_register(function (string $class) {
	if (strpos($class, 'ZaslatShipping\\') !== 0) {
		return;
	}
	$relative = substr($class, strlen('ZaslatShipping\\'));
	$file     = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
	if (file_exists($file)) {
		require $file;
	}
});

add_filter('woocommerce_shipping_methods', function (array $methods): array {
	error_log( 'Zaslat Shipping: Registering method into WooCommerce.' );
	$methods['zaslat_shipping'] = \ZaslatShipping\Shipping_Method::class;
	return $methods;
});

add_filter('woocommerce_get_settings_pages', function (array $pages): array {
	$pages[] = new \ZaslatShipping\Global_Settings();
	return $pages;
});
