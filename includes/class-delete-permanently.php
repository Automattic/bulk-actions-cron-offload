<?php
/**
 * Offload "Delete Permanently"
 *
 * @package Bulk_Actions_Cron_Offload
 */

namespace Automattic\WP\Bulk_Actions_Cron_Offload;

/**
 * Class Delete_Permanently
 */
class Delete_Permanently {
	/**
	 * Common hooks and such
	 */
	use Bulk_Actions;

	/**
	 * Class constants
	 */
	const ACTION = 'delete';

	const ADMIN_NOTICE_KEY = 'bulk_actions_cron_offload_delete_permanently';

	/**
	 * Cron callback to move requested items to trash
	 *
	 * @param object $vars Bulk-request variables.
	 */
	public static function process_via_cron( $vars ) {
		$count = 0;

		if ( is_array( $vars->posts ) && ! empty( $vars->posts ) ) {
			$deleted    = array();
			$locked     = array();
			$auth_error = array();
			$error      = array();

			foreach ( $vars->posts as $post_id ) {
				// Can the user delete this post?
				if ( ! user_can( $vars->user_id, 'delete_post', $post_id ) ) {
					$auth_error[] = $post_id;
					continue;
				}

				// Post is locked by someone, so leave it alone.
				if ( false !== wp_check_post_lock( $post_id ) ) {
					$locked[] = $post_id;
					continue;
				}

				// Try deleting.
				$post_deleted = wp_delete_post( $post_id );
				if ( $post_deleted ) {
					$deleted[] = $post_id;
				} else {
					$error[] = $post_id;
				}

				// Take a break periodically.
				if ( 0 === $count++ % 50 ) {
					stop_the_insanity();
					sleep( 3 );
				}
			}

			$results = compact( 'deleted', 'locked', 'auth_error', 'error' );
			do_action( 'bulk_actions_cron_offload_move_to_trash_request_completed', $results, $vars );
		} else {
			do_action( 'bulk_actions_cron_offload_move_to_trash_request_no_posts', $vars->posts, $vars );
		}
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

		if ( 'edit' === $screen->base && isset( $_REQUEST['post_status'] ) && 'trash' === $_REQUEST['post_status'] ) {
			if ( Main::get_post_ids_for_pending_events( self::ACTION, $screen->post_type, 'trash' ) ) {
				$type    = 'warning';
				$message = self::admin_notice_hidden_pending_processing();
			}
		}

		Main::render_admin_notice( $type, $message );
	}

	/**
	 * Provide post-redirect success message
	 *
	 * @retun string
	 */
	public static function admin_notice_success_message() {
		return __( 'Success! The selected posts will be deleted shortly.', 'bulk-actions-cron-offload' );
	}

	/**
	 * Provide post-redirect error message
	 *
	 * @retun string
	 */
	public static function admin_notice_error_message() {
		return __( 'The selected posts are already scheduled to be deleted.', 'bulk-actions-cron-offload' );
	}

	/**
	 * Provide translated message when posts are hidden pending processing
	 *
	 * @return string
	 */
	public static function admin_notice_hidden_pending_processing() {
		return __( 'Some items that would normally be shown here are waiting to be deleted permanently. These items are hidden until then.', 'bulk-actions-cron-offload' );
	}

	/**
	 * When a delete is pending for a given post type, hide those posts in the admin
	 *
	 * @param string $where Posts' WHERE clause.
	 * @param object $q WP_Query object.
	 * @return string
	 */
	public static function hide_posts( $where, $q ) {
		if ( 'trash' !== $q->get( 'post_status' ) ) {
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

Delete_Permanently::register_hooks();
