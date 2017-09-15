<?php
/**
 * Offload "Empty Trash"
 *
 * @package Bulk_Actions_Cron_Offload
 */

namespace Automattic\WP\Bulk_Actions_Cron_Offload;

/**
 * Class Delete_All
 */
class Delete_All {
	/**
	 * Class constants
	 */
	const ACTION = 'delete_all';

	const ADMIN_NOTICE_KEY = 'bulk_actions_cron_offload_deleted_all';

	/**
	 * Register this bulk process' hooks
	 */
	public static function register_hooks() {
		add_action( Main::build_hook( self::ACTION ), array( __CLASS__, 'process' ) );
		add_action( Main::build_cron_hook( self::ACTION ), array( __CLASS__, 'process_via_cron' ) );

		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
		add_filter( 'posts_where', array( __CLASS__, 'hide_posts' ), 999, 2 );

		// Limit when caps are intercepted, given frequent execution of the `map_meta_cap` filter.
		add_action( 'load-edit.php', function() {
			add_filter( 'map_meta_cap', array( __CLASS__, 'hide_empty_trash_pending_delete' ), 10, 2 );
		} );
	}

	/**
	 * Handle a request to delete all trashed items for a given post type
	 *
	 * @param object $vars Bulk-request variables.
	 */
	public static function process( $vars ) {
		// Special keys are used to trigger this request, and we need to remove them on redirect.
		$extra_keys = array( self::ACTION, self::ACTION . '2' );

		$action_scheduled = Main::get_action_next_scheduled( self::ACTION, $vars->post_type );

		if ( empty( $action_scheduled ) ) {
			Main::schedule_processing( $vars );
			Main::do_admin_redirect( self::ADMIN_NOTICE_KEY, true, $extra_keys );
		} else {
			Main::do_admin_redirect( self::ADMIN_NOTICE_KEY, false, $extra_keys );
		}
	}

	/**
	 * Cron callback to delete trashed items in a given post type
	 *
	 * @param object $vars Bulk-request variables.
	 */
	public static function process_via_cron( $vars ) {
		global $wpdb;

		$post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_status = %s", $vars->post_type, $vars->post_status ) );

		$count = 0;

		if ( is_array( $post_ids ) && ! empty( $post_ids ) ) {
			require_once ABSPATH . '/wp-admin/includes/post.php';

			$deleted    = array();
			$locked     = array();
			$auth_error = array();
			$error      = array();

			foreach ( $post_ids as $post_id ) {
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
			do_action( 'bulk_actions_cron_offload_delete_all_request_completed', $results, $vars );
		} else {
			do_action( 'bulk_actions_cron_offload_delete_all_request_no_posts', $post_ids, $vars );
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
				$message = __( 'Success! The trash will be emptied shortly.', 'bulk-actions-cron-offload' );
			} else {
				$type    = 'error';
				$message = __( 'A request to empty the trash is already pending for this post type.', 'bulk-actions-cron-offload' );
			}
		} elseif ( 'edit' === $screen->base && isset( $_REQUEST['post_status'] ) && 'trash' === $_REQUEST['post_status'] ) {
			if ( Main::get_action_next_scheduled( self::ACTION, $screen->post_type ) ) {
				$type    = 'warning';
				$message = __( 'A pending request to empty the trash will be processed soon.', 'bulk-actions-cron-offload' );
			}
		}

		Main::render_admin_notice( $type, $message );
	}

	/**
	 * When a delete is pending for a given post type, hide those posts in the admin
	 *
	 * @param string $where Posts' WHERE clause.
	 * @param object $q WP_Query object.
	 * @return string
	 */
	public static function hide_posts( $where, $q ) {
		if ( ! is_admin() || ! $q->is_main_query() ) {
			return $where;
		}

		if ( 'edit' !== get_current_screen()->base ) {
			return $where;
		}

		if ( 'trash' !== $q->get( 'post_status' ) ) {
			return $where;
		}

		if ( Main::get_action_next_scheduled( self::ACTION, $q->get( 'post_type' ) ) ) {
			$where .= ' AND 0=1';
		}

		return $where;
	}

	/**
	 * Suppress "Empty Trash" button when purge is pending
	 *
	 * Core doesn't provide a filter specifically for this, but permissions are checked before showing the button
	 *
	 * @param  array  $caps User's capabilities.
	 * @param  string $cap  Cap currently being checked.
	 * @return array
	 */
	public static function hide_empty_trash_pending_delete( $caps, $cap ) {
		// Button we're blocking only shows for the "trash" status, understandably.
		if ( ! isset( $_REQUEST['post_status'] ) || 'trash' !== $_REQUEST['post_status'] ) {
			return $caps;
		}

		// Get post type as Core envisions.
		$screen = get_current_screen();

		// Cap used to display button, per WP_Posts_List_Table::extra_tablenav().
		$cap_to_block = get_post_type_object( $screen->post_type )->cap->edit_others_posts;

		// The current cap isn't the one we're looking for.
		if ( $cap !== $cap_to_block ) {
			return $caps;
		}

		// There isn't a pending purge, so one should be permitted.
		if ( ! Main::get_action_next_scheduled( self::ACTION, $screen->post_type ) ) {
			return $caps;
		}

		// Block the edit button by disallowing its cap.
		$caps[] = 'do_not_allow';

		return $caps;
	}
}

Delete_All::register_hooks();
