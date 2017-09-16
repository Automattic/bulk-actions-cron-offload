<?php
/**
 * Offload "Restore from Trash"
 *
 * @package Bulk_Actions_Cron_Offload
 */

namespace Automattic\WP\Bulk_Actions_Cron_Offload;

/**
 * Class Restore_From_Trash
 */
class Restore_From_Trash {
	/**
	 * Common hooks and such
	 */
	use Bulk_Actions, In_Trash {
		In_Trash::admin_notices insteadof Bulk_Actions;
		In_Trash::hide_posts insteadof Bulk_Actions;
	}

	/**
	 * Class constants
	 */
	const ACTION = 'untrash';

	const ADMIN_NOTICE_KEY = 'bulk_actions_cron_offload_restore_from_trash';

	/**
	 * Cron callback to restore requested items from trash
	 *
	 * @param object $vars Bulk-request variables.
	 */
	public static function process_via_cron( $vars ) {
		$count = 0;

		if ( is_array( $vars->posts ) && ! empty( $vars->posts ) ) {
			$restored   = array();
			$locked     = array();
			$auth_error = array();
			$error      = array();

			foreach ( $vars->posts as $post_id ) {
				// Can the user restore this post?
				if ( ! user_can( $vars->user_id, 'delete_post', $post_id ) ) {
					$auth_error[] = $post_id;
					continue;
				}

				// Post is locked by someone, so leave it alone.
				if ( false !== wp_check_post_lock( $post_id ) ) {
					$locked[] = $post_id;
					continue;
				}

				// Try restoring.
				$post_restored = wp_untrash_post( $post_id );
				if ( $post_restored ) {
					$restored[] = $post_id;
				} else {
					$error[] = $post_id;
				}

				// Take a break periodically.
				if ( 0 === $count++ % 50 ) {
					stop_the_insanity();
					sleep( 3 );
				}
			}

			$results = compact( 'restored', 'locked', 'auth_error', 'error' );
			do_action( 'bulk_actions_cron_offload_restore_from_trash_request_completed', $results, $vars );
		} else {
			do_action( 'bulk_actions_cron_offload_restore_from_trash_request_no_posts', $vars->posts, $vars );
		}
	}

	/**
	 * Provide post-redirect success message
	 *
	 * @retun string
	 */
	public static function admin_notice_success_message() {
		return __( 'Success! The selected posts will be restored shortly.', 'bulk-actions-cron-offload' );
	}

	/**
	 * Provide post-redirect error message
	 *
	 * @retun string
	 */
	public static function admin_notice_error_message() {
		return __( 'The selected posts are already scheduled to be restored.', 'bulk-actions-cron-offload' );
	}

	/**
	 * Provide translated message when posts are hidden pending restoration
	 *
	 * @return string
	 */
	public static function admin_notice_hidden_pending_processing() {
		return __( 'Some items that would normally be shown here are waiting to be restored from the trash. These items are hidden until they are restored.', 'bulk-actions-cron-offload' );
	}
}

Restore_From_Trash::register_hooks();
