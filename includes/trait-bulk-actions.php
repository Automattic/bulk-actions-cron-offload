<?php
/**
 * Methods shared across requests
 *
 * @package Bulk_Actions_Cron_Offload
 */

namespace Automattic\WP\Bulk_Actions_Cron_Offload;

trait Bulk_Actions {
	/**
	 * Register this bulk process' hooks
	 */
	public static function register_hooks() {
		add_action( Main::build_hook( self::ACTION ), array( __CLASS__, 'process' ) );
		add_action( Main::build_cron_hook( self::ACTION ), array( __CLASS__, 'process_via_cron' ) );

		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
		add_filter( 'posts_where', array( __CLASS__, 'hide_posts' ), 999, 2 );

		self::register_extra_hooks();
	}

	/**
	 *
	 */
	public static function register_extra_hooks() {}
}
