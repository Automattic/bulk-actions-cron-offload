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
		if ( ! isset( $_REQUEST['action'] ) && ! isset( $_REQUEST['action2'] ) && ! isset( $_REQUEST['delete_all'] ) ) {
			return;
		}

		// Parse request to determine what to do
		$vars = self::capture_vars();

		// Now what?
		switch ( $vars->action ) {
			case 'delete_all' :
				Delete_All::process( $vars );
				break;

			case 'trash' :
				break;

			case 'untrash' :
				break;

			case 'delete' :
				break;

			case 'edit' :
				break;

			// How did you get here?
			default :
				return;
				break;
		}
	}

	/**
	 * Capture relevant variables
	 */
	private static function capture_vars() {
		$vars = (object) array_fill_keys( array( 'user_id', 'action', 'post_type', 'posts', 'tax_input', 'post_author', 'comment_status', 'ping_status', 'post_status', 'post_sticky', 'post_format', ), null );

		// TODO: replace with foreach and switch

		$vars->user_id = get_current_user_id();

		if ( isset( $_REQUEST['delete_all'] ) ) {
			$vars->action = 'delete_all';

			$vars->post_status = $_REQUEST['post_status'];
		} elseif ( isset( $_REQUEST['action'] ) && -1 !== (int) $_REQUEST['action'] ) {
			$vars->action = (int) $_REQUEST['action'];
		} elseif ( isset( $_REQUEST['action2'] ) && -1 !== (int) $_REQUEST['action2'] ) {
			$vars->action = (int) $_REQUEST['action2'];
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
			$vars->post_author = $_REQUEST['post_author'];
		}

		if ( isset( $_REQUEST['comment_status'] ) && ! empty( $_REQUEST['comment_status'] ) ) {
			$vars->comment_status = $_REQUEST['comment_status'];
		}

		if ( isset( $_REQUEST['ping_status'] ) && ! empty( $_REQUEST['ping_status'] ) ) {
			$vars->ping_status = $_REQUEST['ping_status'];
		}

		if ( isset( $_REQUEST['_status'] ) && -1 !== (int) $_REQUEST['_status'] ) {
			$vars->post_status = $_REQUEST['_status'];
		}

		if ( isset( $_REQUEST['sticky'] ) && -1 !== (int) $_REQUEST['sticky'] ) {
			$vars->post_sticky = $_REQUEST['sticky'];
		}

		if ( isset( $_REQUEST['post_format'] ) && -1 !== (int) $_REQUEST['post_format'] ) {
			$vars->post_format = $_REQUEST['post_format'];
		}

		// Stop Core from processing bulk request
		unset( $_REQUEST['action'] );
		unset( $_REQUEST['action2'] );
		unset( $_REQUEST['delete_all'] );

		// Return captured variables
		return $vars;
	}
}

Main::load();
