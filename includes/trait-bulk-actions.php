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
	 * Strip notice arguments after the initial redirect
	 */
	use Remove_One_Time_Args;

	/**
	 * Register this bulk process' hooks
	 */
	public static function register_hooks() {
		add_action( Main::build_hook( self::ACTION ), array( __CLASS__, 'process' ) );
		add_action( Main::build_cron_hook( self::ACTION ), array( __CLASS__, 'process_via_cron' ) );

		add_action( 'admin_notices', array( __CLASS__, 'render_admin_notices' ) );
		add_filter( 'posts_where', array( __CLASS__, 'hide_posts_common' ), 999, 2 );

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
	 * Prepare environment for individual actions
	 *
	 * @param object $vars Bulk-request variables.
	 */
	public static function process_via_cron( $vars ) {
		// Normally processed in the admin context.
		require_once( ABSPATH . 'wp-admin/includes/admin.php' );

		parent::process_via_cron( $vars );
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
	 * Let the user know what's going on
	 *
	 * Not used for post-request redirect
	 */
	public static function admin_notices() {
		$screen = get_current_screen();

		$type    = '';
		$message = '';

		if ( 'edit' === $screen->base ) {
			if ( isset( $_REQUEST['post_status'] ) && 'trash' === $_REQUEST['post_status'] ) {
				return;
			}

			$status  = isset( $_REQUEST['post_status'] ) ? $_REQUEST['post_status'] : 'all';
			$pending = Main::get_post_ids_for_pending_events( self::ACTION, $screen->post_type, $status );

			if ( ! empty( $pending ) ) {
				$type    = 'warning';
				$message = self::admin_notice_hidden_pending_processing();
			}
		}

		Main::render_admin_notice( $type, $message );
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
	 * Provide translated message when posts are hidden pending processing
	 *
	 * @return string
	 */
	public static function admin_notice_hidden_pending_processing() {
		return '';
	}

	/**
	 * When a process is pending for a given post type, hide those posts in the admin
	 *
	 * @param string $where Posts' WHERE clause.
	 * @param object $q WP_Query object.
	 * @return string
	 */
	public static function hide_posts_common( $where, $q ) {
		if ( ! is_admin() || ! $q->is_main_query() ) {
			return $where;
		}

		if ( 'edit' !== get_current_screen()->base ) {
			return $where;
		}

		return self::hide_posts( $where, $q );
	}

	/**
	 * Hide posts pending processing
	 *
	 * @param string $where Posts' WHERE clause.
	 * @param object $q WP_Query object.
	 * @return string
	 */
	public static function hide_posts( $where, $q ) {
		if ( 'trash' === $q->get( 'post_status' ) ) {
			return $where;
		}

		$post__not_in = Main::get_post_ids_for_pending_events( self::ACTION, $q->get( 'post_type' ), $q->get( 'post_status' ) );

		if ( ! empty( $post__not_in ) ) {
			$post__not_in = implode( ',', $post__not_in );
			$where       .= ' AND ID NOT IN(' . $post__not_in . ')';
		}

		return $where;
	}
}
