<?php
/**
 * Offload "Move to Trash"
 *
 * @package Bulk_Edit_Cron_Offload
 */

namespace Automattic\WP\Bulk_Edit_Cron_Offload;

/**
 * Class Move_To_Trash
 */
class Move_To_Trash {
	/**
	 * Class constants
	 */
	const CRON_EVENT = 'bulk_edit_cron_offload_move_to_trash';

	/**
	 * Register this bulk process' hooks
	 */
	public static function register_hooks() {
		add_action( Main::build_hook( 'trash' ), array( __CLASS__, 'process' ) );
		add_action( self::CRON_EVENT, array( __CLASS__, 'process_via_cron' ) );
	}

	/**
	 * Handle a request to move some posts to the trash
	 *
	 * @param object $vars Bulk-request variables.
	 */
	public static function process( $vars ) {
		wp_schedule_single_event( time(), self::CRON_EVENT, array( $vars ) );

		Main::do_admin_redirect( self::ADMIN_NOTICE_KEY, true );
	}

	/**
	 * Cron callback to move requested items to trash
	 *
	 * @param object $vars Bulk-request variables.
	 */
	public static function process_via_cron( $vars ) {
		$count = 0;

		if ( is_array( $vars->posts ) && ! empty( $vars->posts ) ) {
			require_once ABSPATH . '/wp-admin/includes/post.php';

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
			do_action( 'bulk_edit_cron_offload_move_to_trash_request_completed', $results, $vars );
		} else {
			do_action( 'bulk_edit_cron_offload_move_to_trash_request_no_posts', $vars->posts, $vars );
		}
	}
}

Move_To_Trash::register_hooks();
