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
	 * Common hooks and such
	 */
	use Bulk_Actions;

	/**
	 * Class constants
	 */
	const ACTION = 'edit';

	const ADMIN_NOTICE_KEY = 'bulk_actions_cron_offload_edit';

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

		// `bulk_edit_posts()` takes an array, normally `$_REQUEST`, so we convert back.
		$request_array = get_object_vars( $vars );
		unset( $request_array['action'] );
		unset( $request_array['user_id'] );

		// Modify some keys to match `bulk_edit_post()`'s expectations.
		$request_array['post'] = $request_array['posts'];
		unset( $request_array['posts'] );

		if ( ! is_null( $request_array['post_sticky'] ) ) {
			$request_array['sticky'] = $request_array['post_sticky'];
		}
		unset( $request_array['post_sticky'] );

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
	 * Provide post-redirect success message
	 *
	 * @retun string
	 */
	public static function admin_notice_success_message() {
		return __( 'Success! The selected posts will be edited shortly.', 'bulk-actions-cron-offload' );
	}

	/**
	 * Provide post-redirect error message
	 *
	 * @retun string
	 */
	public static function admin_notice_error_message() {
		return __( 'The requested edits are already pending for the chosen posts.', 'bulk-actions-cron-offload' );
	}

	/**
	 * Provide notice when posts are hidden pending edits
	 *
	 * @return string
	 */
	public static function admin_notice_hidden_pending_processing() {
		return __( 'Some items that would normally be shown here are waiting to be edited. These items are hidden until they are processed.', 'bulk-actions-cron-offload' );
	}
}

Edit::register_hooks();
