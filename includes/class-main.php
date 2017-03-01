<?php

namespace Automattic\WP\Bulk_Edit_Cron_Offload;

class Main {
	/**
	 * Register action
	 */
	public static function load() {
		add_action( 'load-edit.php', array( __CLASS__, 'intercept' ) );
	}

	/**
	 * Call appropriate handler
	 */
	public static function intercept() {
		// Nothing to do
		if ( ! self::should_intercept_request() ) {
			return;
		}

		// Validate request
		check_admin_referer( 'bulk-posts' );

		// Parse request to determine what to do
		$vars = self::capture_vars();

		// Now what?
		switch ( $vars->action ) {
			case 'delete_all' :
				self::skip_core_processing();

				Delete_All::process( $vars );
				break;

			case 'trash' :
				return;
				break;

			case 'untrash' :
				return;
				break;

			case 'delete' :
				return;
				break;

			case 'edit' :
				return;
				break;

			// Should only arrive here if loaded on the wrong admin screen
			default :
				error_log( var_export( get_current_screen(), true ) );
				error_log( var_export( wp_debug_backtrace_summary( __CLASS__, null, false ), true ) );

				return;
				break;
		}
	}

	/**
	 * Determine if current request is a bulk edit
	 */
	private static function should_intercept_request() {
		return isset( $_REQUEST['action'] ) || isset( $_REQUEST['action2'] ) || isset( $_REQUEST['delete_all'] );
	}

	/**
	 * Capture relevant variables
	 */
	private static function capture_vars() {
		$vars = (object) array_fill_keys( array( 'user_id', 'action', 'post_type', 'posts', 'tax_input', 'post_author', 'comment_status', 'ping_status', 'post_status', 'post_sticky', 'post_format', ), null );

		$vars->user_id = get_current_user_id();

		if ( isset( $_REQUEST['delete_all'] ) ) {
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

		// Return captured variables
		return $vars;
	}

	/**
	 * Unset flags Core uses to trigger bulk processing
	 */
	private static function skip_core_processing() {
		unset( $_REQUEST['action'] );
		unset( $_REQUEST['action2'] );
		unset( $_REQUEST['delete_all'] );
	}
}

Main::load();
