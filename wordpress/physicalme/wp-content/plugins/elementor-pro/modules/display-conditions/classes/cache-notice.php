<?php
namespace ElementorPro\Modules\DisplayConditions\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cache_Notice {
	private const OPTION_KEY = 'elementor_pro_display_conditions_cache_notice_dismissed';

	public function should_show_notice(): bool {
		return ! (bool) get_option( self::OPTION_KEY, false );
	}

	public function set_notice_status() {
		update_option( self::OPTION_KEY, 1 );
		wp_send_json_success();
	}
}
