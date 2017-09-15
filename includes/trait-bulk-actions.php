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

		add_filter( 'removable_query_args', array( __CLASS__, 'remove_notice_arg' ) );

		self::register_extra_hooks();
	}

	/**
	 * Some methods may need extra hooks
	 */
	public static function register_extra_hooks() {}

	/**
	 * When an edit is pending for a given post type, hide those posts in the admin
	 *
	 * @param string $where Posts' WHERE clause.
	 * @param object $q WP_Query object.
	 * @return string
	 */
	public static function hide_posts( $where, $q ) {
		if ( ! is_admin() || ! $q->is_main_query() ) {
			return $where;
		}

		if ( 'edit' !== get_current_screen()->base ) {
			return $where;
		}

		return parent::hide_posts( $where, $q );
	}

	/**
	 * Strip the custom notice key, otherwise it turns up in pagination and other unwanted places.
	 *
	 * @param array $args Array of one-time query args.
	 * @return array
	 */
	public static function remove_notice_arg( $args ) {
		$args[] = self::ADMIN_NOTICE_KEY;

		return $args;
	}
}
