<?php
/**
 * Methods shared across requests
 *
 * @package Bulk_Actions_Cron_Offload
 */

namespace Automattic\WP\Bulk_Actions_Cron_Offload;

/**
 * Trait Bulk_Actions
 */
trait Bulk_Actions {
	/**
	 * Register this bulk process' hooks
	 */
	public static function register_hooks() {
		add_action( Main::build_hook( self::ACTION ), array( __CLASS__, 'process' ) );
		add_action( Main::build_cron_hook( self::ACTION ), array( __CLASS__, 'process_via_cron' ) );

		add_action( 'admin_notices', array( __CLASS__, 'render_admin_notices' ) );
		add_filter( 'posts_where', array( __CLASS__, 'hide_posts' ), 999, 2 );

		add_filter( 'removable_query_args', array( __CLASS__, 'remove_notice_arg' ) );

		self::register_extra_hooks();
	}

	/**
	 * Some methods may need extra hooks
	 */
	public static function register_extra_hooks() {}

	/**
	 * Process request
	 *
	 * @param object $vars Bulk-request variables.
	 */
	public static function process( $vars ) {
		$action_scheduled = Main::next_scheduled( $vars );

		if ( empty( $action_scheduled ) ) {
			Main::schedule_processing( $vars );
			Main::do_admin_redirect( self::ADMIN_NOTICE_KEY, true );
		} else {
			Main::do_admin_redirect( self::ADMIN_NOTICE_KEY, false );
		}
	}

	/**
	 * Render the post-redirect notice, or hand off to class for other notices
	 */
	public static function render_admin_notices() {
		if ( isset( $_REQUEST[ self::ADMIN_NOTICE_KEY ] ) ) {
			if ( 1 === (int) $_REQUEST[ self::ADMIN_NOTICE_KEY ] ) {
				$type    = 'success';
				$message = self::admin_notice_success_message();
			} else {
				$type    = 'error';
				$message = self::admin_notice_error_message();
			}

			Main::render_admin_notice( $type, $message );
			return;
		}

		self::admin_notices();
	}

	/**
	 * Provide translated success message for bulk action
	 *
	 * @return string
	 */
	public static function admin_notice_success_message() {
		return '';
	}

	/**
	 * Provide translated error message for bulk action
	 *
	 * @return string
	 */
	public static function admin_notice_error_message() {
		return '';
	}

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
