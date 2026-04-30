<?php

namespace ZaslatShipping;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Global_Settings extends \WC_Settings_Page {

	public function __construct() {
		$this->id    = 'zaslat_shipping';
		$this->label = 'Zaslat Shipping';
		parent::__construct();
	}

	public function get_settings(): array {
		return apply_filters( 'zaslat_shipping_settings', [
			[
				'title' => 'Zaslat API Settings',
				'type'  => 'title',
				'id'    => 'zaslat_shipping_section',
			],
			[
				'title'   => 'API Key',
				'id'      => 'zaslat_shipping_api_key',
				'type'    => 'password',
				'default' => '',
				'desc'    => 'Your Zaslat API key from zaslat.cz.',
			],
			[
				'type' => 'sectionend',
				'id'   => 'zaslat_shipping_section',
			],
		] );
	}
}
