<?php
/**
 * Offload "Edit"
 *
 * @package Bulk_Actions_Cron_Offload
 */

namespace Automattic\WP\Bulk_Actions_Cron_Offload;

/**
 * Class Edit
 */
class Edit {
	/**
	 * Class constants
	 */
	const ACTION = 'edit';

	const ADMIN_NOTICE_KEY = 'bulk_actions_cron_offload_edit';

	/**
	 * Register this bulk process' hooks
	 */
	public static function register_hooks() {
		add_action( Main::build_hook( self::ACTION ), array( __CLASS__, 'process' ) );
		add_action( Main::build_cron_hook( self::ACTION ), array( __CLASS__, 'process_via_cron' ) );

		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
		add_filter( 'posts_where', array( __CLASS__, 'hide_posts_pending_move' ), 999, 2 );
	}

	/**
	 * Handle a request to edit some posts
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
	 * Cron callback to edit requested items
	 *
	 * @param object $vars Bulk-request variables.
	 */
	public static function process_via_cron( $vars ) {
		// Nothing to edit.
		if ( ! is_array( $vars->posts ) || empty( $vars->posts ) ) {
			do_action( 'bulk_actions_cron_offload_edit_request_no_posts', $vars->posts, $vars );
			return;
		}

		// We want to use `bulk_edit_posts()`.
		require_once ABSPATH . '/wp-admin/includes/post.php';

		// `bulk_edit_posts()` takes an array, normally `$_REQUEST`, so we convert back.
		$request_array = get_object_vars( $vars );
		unset( $request_array['action'] );

		// Modify some keys to match `bulk_edit_post()`'s expectations.
		$request_array['post'] = $request_array['posts'];
		unset( $request_array['posts'] );

		if ( ! is_null( $request_array['post_sticky'] ) ) {
			$request_array['sticky'] = $request_array['post_sticky'];
			unset( $request_array['post_sticky'] );
		}

		// Post status uses a special key.
		if ( is_null( $request_array['post_status'] ) || 'all' === $request_array['post_status'] ) {
			$request_array['_status'] = -1;
		} else {
			$request_array['_status'] = $request_array['post_status'];
		}
		unset( $request_array['post_status'] );

		// `bulk_edit_posts()` calls `current_user_can()`, so we make sure it can.
		wp_set_current_user( $vars->user_id );

		// Perform bulk edit.
		$results = bulk_edit_posts( $request_array );
		$edited  = $results['updated'];
		$error   = $results['skipped'];
		$locked  = $results['locked'];

		// `bulk_edit_posts()` mixes these without indicating which it was.
		$auth_error = $error;

		$results = compact( 'edited', 'locked', 'auth_error', 'error' );
		do_action( 'bulk_actions_cron_offload_edit_request_completed', $results, $vars );
	}

	/**
	 * Let the user know what's going on
	 */
	public static function admin_notices() {
		$screen = get_current_screen();

		$type    = '';
		$message = '';

		if ( isset( $_REQUEST[ self::ADMIN_NOTICE_KEY ] ) ) {
			if ( 1 === (int) $_REQUEST[ self::ADMIN_NOTICE_KEY ] ) {
				$type    = 'success';
				$message = __( 'Success! The selected posts will be edited shortly.', 'bulk-actions-cron-offload' );
			} else {
				$type    = 'error';
				$message = __( 'The requested edits are already pending for the chosen posts.', 'bulk-actions-cron-offload' );
			}
		} elseif ( 'edit' === $screen->base ) {
			if ( isset( $_REQUEST['post_status'] ) && 'trash' === $_REQUEST['post_status'] ) {
				return;
			}

			$status  = isset( $_REQUEST['post_status'] ) ? $_REQUEST['post_status'] : 'all';
			$pending = Main::get_post_ids_for_pending_events( self::ACTION, $screen->post_type, $status );

			if ( ! empty( $pending ) ) {
				$type    = 'warning';
				$message = __( 'Some items that would normally be shown here are waiting to be edited. These items are hidden until they are processed.', 'bulk-actions-cron-offload' );
			}
		}

		Main::render_admin_notice( $type, $message );
	}

	/**
	 * When an edit is pending for a given post type, hide those posts in the admin
	 *
	 * @param string $where Posts' WHERE clause.
	 * @param object $q WP_Query object.
	 * @return string
	 */
	public static function hide_posts_pending_move( $where, $q ) {
		if ( ! is_admin() || ! $q->is_main_query() ) {
			return $where;
		}

		if ( 'edit' !== get_current_screen()->base ) {
			return $where;
		}

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

Edit::register_hooks();
