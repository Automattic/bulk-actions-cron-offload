<?php
/**
 * Plugin's main class, dispatcher for specific bulk-edit requests
 *
 * @package Bulk_Edit_Cron_Offload
 */

namespace Automattic\WP\Bulk_Edit_Cron_Offload;

/**
 * Class Main
 */
class Main {
	/**
	 * Prefix for bulk-process hook invoked by request-specific classes
	 */
	const ACTION = 'a8c_bulk_edit_cron_';

	/**
	 * Common cron action
	 */
	const CRON_EVENT = 'bulk_edit_cron_offload';

	/**
	 * Register actions
	 */
	public static function load() {
		add_action( self::CRON_EVENT, array( __CLASS__, 'do_cron' ) );

		add_action( 'load-edit.php', array( __CLASS__, 'intercept' ) );
	}

	/**
	 * Run appropriate cron callback
	 *
	 * @param object $vars Bulk-request variables.
	 */
	public static function do_cron( $vars ) {
		do_action( self::build_cron_hook( $vars->action ), $vars );
	}

	/**
	 * Call appropriate handler
	 */
	public static function intercept() {
		// Nothing to do.
		if ( ! self::should_intercept_request() ) {
			return;
		}

		// Validate request.
		check_admin_referer( 'bulk-posts' );

		// Parse request to determine what to do.
		$vars   = self::capture_vars();
		$action = self::build_hook( $vars->action );

		if ( ! self::bulk_action_allowed( $vars->action ) ) {
			return;
		}

		// Pass request to a class to handle offloading to cron, UX, etc.
		do_action( $action, $vars );

		// Only skip Core's default handling when action is offloaded.
		if ( has_action( $action ) ) {
			self::skip_core_processing();
		}
	}

	/**
	 * Determine if current request is a bulk edit
	 */
	private static function should_intercept_request() {
		if ( isset( $_REQUEST['delete_all'] ) || isset( $_REQUEST['delete_all2'] ) ) {
			return true;
		} elseif ( isset( $_REQUEST['action'] ) && '-1' !== $_REQUEST['action'] ) {
			return true;
		} elseif ( isset( $_REQUEST['action2'] ) && '-1' !== $_REQUEST['action2'] ) {
			return true;
		}

		return false;
	}

	/**
	 * Capture relevant variables
	 */
	private static function capture_vars() {
		$vars = (object) array_fill_keys( array( 'user_id', 'action', 'post_type', 'posts', 'tax_input', 'post_author', 'comment_status', 'ping_status', 'post_status', 'post_sticky', 'post_format' ), null );

		$vars->user_id = get_current_user_id();

		if ( isset( $_REQUEST['delete_all'] ) || isset( $_REQUEST['delete_all2'] ) ) {
			$vars->action = 'delete_all';

			$vars->post_status = $_REQUEST['post_status'];
		} elseif ( isset( $_REQUEST['action'] ) && '-1' !== $_REQUEST['action'] ) {
			$vars->action = $_REQUEST['action'];
		} elseif ( isset( $_REQUEST['action2'] ) && '-1' !== $_REQUEST['action2'] ) {
			$vars->action = $_REQUEST['action2'];
		}

		if ( isset( $_REQUEST['post_type'] ) && ! empty( $_REQUEST['post_type'] ) ) {
			$vars->post_type = $_REQUEST['post_type'];
		}

		if ( isset( $_REQUEST['post'] ) && is_array( $_REQUEST['post'] ) ) {
			$vars->posts = array_map( 'absint', $_REQUEST['post'] );
		}

		if ( isset( $_REQUEST['tax_input'] ) && is_array( $_REQUEST['tax_input'] ) ) {
			$vars->tax_input = $_REQUEST['tax_input'];
		}

		if ( isset( $_REQUEST['post_author'] ) && -1 !== (int) $_REQUEST['post_author'] ) {
			$vars->post_author = (int) $_REQUEST['post_author'];
		}

		if ( isset( $_REQUEST['comment_status'] ) && ! empty( $_REQUEST['comment_status'] ) ) {
			$vars->comment_status = $_REQUEST['comment_status'];
		}

		if ( isset( $_REQUEST['ping_status'] ) && ! empty( $_REQUEST['ping_status'] ) ) {
			$vars->ping_status = $_REQUEST['ping_status'];
		}

		if ( isset( $_REQUEST['_status'] ) && '-1' !== $_REQUEST['_status'] ) {
			$vars->post_status = $_REQUEST['_status'];
		}

		if ( isset( $_REQUEST['sticky'] ) && '-1' !== $_REQUEST['sticky'] ) {
			$vars->post_sticky = $_REQUEST['sticky'];
		}

		if ( isset( $_REQUEST['post_format'] ) && '-1' !== $_REQUEST['post_format'] ) {
			$vars->post_format = $_REQUEST['post_format'];
		}

		return $vars;
	}

	/**
	 * Validate action
	 *
	 * @param  string $action Action parsed from request vars.
	 * @return bool
	 */
	public static function bulk_action_allowed( $action ) {
		$allowed_actions = array(
			'delete',
			'delete_all',
			'edit',
			'trash',
			'untrash',
		);

		return in_array( $action, $allowed_actions, true );
	}

	/**
	 * Build a WP hook specific to a bulk request
	 *
	 * @param  string $action Bulk action to offload.
	 * @return string
	 */
	public static function build_hook( $action ) {
		return self::ACTION . $action;
	}

	/**
	 * Build a cron hook specific to a bulk request
	 *
	 * @param  string $action Bulk action to register cron callback for.
	 * @return string
	 */
	public static function build_cron_hook( $action ) {
		return self::ACTION . $action . '_callback';
	}

	/**
	 * Unset flags Core uses to trigger bulk processing
	 */
	private static function skip_core_processing() {
		unset( $_REQUEST['action'] );
		unset( $_REQUEST['action2'] );
		unset( $_REQUEST['delete_all'] );
		unset( $_REQUEST['delete_all2'] );
	}

	/**
	 * Redirect, including a flag to indicate if the bulk process was scheduled successfully
	 *
	 * @param string $return_key  Key to include in redirect URL to flag request's origin, use for admin feedback, etc.
	 * @param bool   $succeeded   Whether or not the bulk-delete was scheduled.
	 * @param array  $extra_keys  Optional. Array of additional action keys to remove from redirect URL.
	 */
	public static function do_admin_redirect( $return_key, $succeeded = false, $extra_keys = array() ) {
		$redirect = wp_unslash( $_SERVER['REQUEST_URI'] );

		// Remove arguments that could re-trigger this bulk-edit.
		$action_keys = array( '_wp_http_referer', '_wpnonce', 'action', 'action2' );
		$action_keys = array_merge( $action_keys, $extra_keys );
		$redirect    = remove_query_arg( $action_keys, $redirect );

		// Add a flag for the admin notice.
		$redirect = add_query_arg( $return_key, $succeeded ? 1 : -1, $redirect );

		$redirect = esc_url_raw( $redirect );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Create cron event
	 *
	 * @param object $vars Bulk-request variables.
	 * @return bool
	 */
	public static function schedule_processing( $vars ) {
		return false !== wp_schedule_single_event( time(), self::CRON_EVENT, array( $vars ) );
	}

	/**
	 *
	 * @param object $vars Bulk-request variables.
	 * @return int
	 */
	public static function next_scheduled( $vars ) {
		return (int) wp_next_scheduled( self::CRON_EVENT, array( $vars ) );
	}
}

Main::load();
