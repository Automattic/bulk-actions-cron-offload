<?php
/**
 * Offload "Move to Trash"
 *
 * @package Bulk_Actions_Cron_Offload
 */

namespace Automattic\WP\Bulk_Actions_Cron_Offload;

/**
 * Class Move_To_Trash
 */
class Move_To_Trash {
	/**
	 * Common hooks and such
	 */
	use Bulk_Actions;

	/**
	 * Class constants
	 */
	const ACTION = 'trash';

	const ADMIN_NOTICE_KEY = 'bulk_actions_cron_offload_move_to_trash';

	/**
	 * Cron callback to move requested items to trash
	 *
	 * @param object $vars Bulk-request variables.
	 */
	public static function process_via_cron( $vars ) {
		$count = 0;

		if ( is_array( $vars->posts ) && ! empty( $vars->posts ) ) {
			$trashed    = array();
			$locked     = array();
			$auth_error = array();
			$error      = array();

			foreach ( $vars->posts as $post_id ) {
				// Can the user trash this post?
				if ( ! user_can( $vars->user_id, 'delete_post', $post_id ) ) {
					$auth_error[] = $post_id;
					continue;
				}

				// Post is locked by someone, so leave it alone.
				if ( false !== wp_check_post_lock( $post_id ) ) {
					$locked[] = $post_id;
					continue;
				}

				// Try trashing.
				$post_trashed = wp_trash_post( $post_id );
				if ( $post_trashed ) {
					$trashed[] = $post_id;
				} else {
					$error[] = $post_id;
				}

				// Take a break periodically.
				if ( 0 === $count++ % 50 ) {
					stop_the_insanity();
					sleep( 3 );
				}
			}

			$results = compact( 'trashed', 'locked', 'auth_error', 'error' );
			do_action( 'bulk_actions_cron_offload_move_to_trash_request_completed', $results, $vars );
		} else {
			do_action( 'bulk_actions_cron_offload_move_to_trash_request_no_posts', $vars->posts, $vars );
		}
	}

	/**
	 * Provide post-redirect success message
	 *
	 * @retun string
	 */
	public static function admin_notice_success_message() {
		return __( 'Success! The selected posts will be moved to the trash shortly.', 'bulk-actions-cron-offload' );
	}

	/**
	 * Provide post-redirect error message
	 *
	 * @return string
	 */
	public static function admin_notice_error_message() {
		return __( 'The selected posts are already scheduled to be moved to the trash.', 'bulk-actions-cron-offload' );
	}

	/**
	 * Provide translated message when posts are hidden pending move
	 *
	 * @return string
	 */
	public static function admin_notice_hidden_pending_processing() {
		return __( 'Some items that would normally be shown here are waiting to be moved to the trash. These items are hidden until they are moved.', 'bulk-actions-cron-offload' );
	}
}

Move_To_Trash::register_hooks();
