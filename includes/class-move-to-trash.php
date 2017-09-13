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
	const ACTION = 'trash';

	const ADMIN_NOTICE_KEY = 'bulk_edit_cron_offload_move_to_trash';

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
	 * Handle a request to move some posts to the trash
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

	/**
	 * Let the user know what's going on
	 */
	public static function admin_notices() {
		$screen = get_current_screen();

		$type   = '';
		$message = '';

		if ( isset( $_REQUEST[ self::ADMIN_NOTICE_KEY ] ) ) {
			if ( 1 === (int) $_REQUEST[ self::ADMIN_NOTICE_KEY ] ) {
				$type    = 'success';
				$message = __( 'Success! The selected posts will be moved to the trash shortly.', 'bulk-edit-cron-offload' );
			} else {
				$type    = 'error';
				$message = __( 'The selected posts are already scheduled to be moved to the trash.', 'bulk-edit-cron-offload' );
			}
		} elseif ( 'edit' === $screen->base ) {
			if ( isset( $_REQUEST['post_status'] ) && 'trash' === $_REQUEST['post_status'] ) {
				return;
			}

			$status = isset( $_REQUEST['post_status'] ) ? $_REQUEST['post_status'] : 'all';

			if ( self::get_all_pending_actions( $screen->post_type, $status ) ) {
				$type    = 'warning';
				$message = __( 'Some items that would normally be shown here are waiting to be moved to the trash. These items are hidden until they are moved.', 'bulk-edit-cron-offload' );
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

		$post__not_in = self::get_post_ids_pending_move( $q->get( 'post_type' ), $q->get( 'post_status' ) );

		if ( ! empty( $post__not_in ) ) {
			$post__not_in = implode( ',', $post__not_in );
			$where       .= ' AND ID NOT IN(' . $post__not_in . ')';
		}

		return $where;
	}

	/**
	 * Gather all pending events for a given post type
	 *
	 * @param string $post_type Post type needing exclusion.
	 * @param string $post_status Post status to filter by.
	 * @return array
	 */
	private static function get_all_pending_actions( $post_type, $post_status ) {
		$events = get_option( 'cron' );

		if ( ! is_array( $events ) ) {
			return array();
		}

		$ids = array();

		foreach ( $events as $timestamp => $timestamp_events ) {
			// Skip non-event data that Core includes in the option.
			if ( ! is_numeric( $timestamp ) ) {
				continue;
			}

			foreach ( $timestamp_events as $action => $action_instances ) {
				if ( Main::CRON_EVENT !== $action ) {
					continue;
				}

				foreach ( $action_instances as $instance => $instance_args ) {
					$vars = array_shift( $instance_args['args'] );

					if ( self::ACTION === $vars->action && $post_type === $vars->post_type ) {
						if ( $post_status === $vars->post_status || 'all' === $vars->post_status || 'all' === $post_status ) {
							$ids[] = array(
								'timestamp' => $timestamp,
								'args'      => $vars,
							);
						}
					}
				}
			}
		}

		return $ids;
	}

	/**
	 * Gather IDs of objects pending move to trash, with given post type
	 *
	 * @param string $post_type Post type needing exclusion.
	 * @param string $post_status Post status to filter by.
	 * @return array
	 */
	private static function get_post_ids_pending_move( $post_type, $post_status ) {
		$events = wp_list_pluck( self::get_all_pending_actions( $post_type, $post_status ), 'args' );
		$events = wp_list_pluck( $events, 'posts' );

		$ids = array();

		foreach ( $events as $ids_to_merge ) {
			$ids = array_merge( $ids, $ids_to_merge );
		}

		if ( ! empty( $ids ) ) {
			$ids = array_map( 'absint', $ids );
			$ids = array_unique( $ids );
		}

		return $ids;
	}
}

Move_To_Trash::register_hooks();
