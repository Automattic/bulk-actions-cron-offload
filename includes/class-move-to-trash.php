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
			do_action( 'bulk_actions_cron_offload_move_to_trash_request_completed', $results, $vars );
		} else {
			do_action( 'bulk_actions_cron_offload_move_to_trash_request_no_posts', $vars->posts, $vars );
		}
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
				$message = __( 'Success! The selected posts will be moved to the trash shortly.', 'bulk-actions-cron-offload' );
			} else {
				$type    = 'error';
				$message = __( 'The selected posts are already scheduled to be moved to the trash.', 'bulk-actions-cron-offload' );
			}
		} elseif ( 'edit' === $screen->base ) {
			if ( isset( $_REQUEST['post_status'] ) && 'trash' === $_REQUEST['post_status'] ) {
				return;
			}

			$status  = isset( $_REQUEST['post_status'] ) ? $_REQUEST['post_status'] : 'all';
			$pending = Main::get_post_ids_for_pending_events( self::ACTION, $screen->post_type, $status );

			if ( ! empty( $pending ) ) {
				$type    = 'warning';
				$message = __( 'Some items that would normally be shown here are waiting to be moved to the trash. These items are hidden until they are moved.', 'bulk-actions-cron-offload' );
			}
		}

		Main::render_admin_notice( $type, $message );
	}

	/**
	 * When a move is pending for a given post type, hide those posts in the admin
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

Move_To_Trash::register_hooks();
