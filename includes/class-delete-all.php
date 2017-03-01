<?php

namespace Automattic\WP\Bulk_Edit_Cron_Offload;

class Delete_All {
	/**
	 *
	 */
	const CRON_EVENT = 'a8c_bulk_edit_delete_all';

	/**
	 *
	 */
	public static function register_hooks() {
		add_action( self::CRON_EVENT, array( __CLASS__, 'process_via_cron' ) );
	}

	/**
	 *
	 */
	public static function process( $vars ) {
		// Queue job
		// Filter redirect
		// Register admin notices
		// Add hook to hide posts when their deletion is pending

		// TODO: Insufficient, need to check regardless of args :(
		$existing_event_ts = wp_next_scheduled( self::CRON_EVENT, array( $vars ) );

		if ( $existing_event_ts ) {
			// TODO: Notice that event already scheduled
			self::redirect_error();
		} else {
			wp_schedule_single_event( time(), self::CRON_EVENT, array( $vars ) );

			// TODO: Notice that event scheduled
			self::redirect_success();
		}
	}

	/**
	 *
	 */
	public static function process_via_cron( $vars ) {
		// Get posts by type and status
		// Loop, check perms, delete
		// What to do about those that fail?

		global $wpdb;

		$post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_status = %s", $vars->post_type, $vars->post_status ) );

		if ( is_array( $post_ids ) && ! empty( $post_ids ) ) {
			require_once ABSPATH . '/wp-admin/includes/post.php';

			$deleted = $locked = $auth_error = $error = array();

			foreach ( $post_ids as $post_id ) {
				// Can the user delete this post

				if ( ! user_can( $vars->user_id, 'delete_post', $post_id ) ) {
					$auth_error[] = $post_id;
					continue;
				}

				// Post is locked by someone, so leave it alone
				if ( false !== wp_check_post_lock( $post_id ) ) {
					$locked[] = $post_id;
					continue;
				}

				//
				$post_deleted = wp_delete_post( $post_id );
				if ( $post_deleted ) {
					$deleted[] = $post_id;
				} else {
					$error[] = $post_id;
				}

				// TODO: stop_the_insanity()
			}

			// TODO: something meaningful with this data
			$results = compact( 'deleted', 'locked', 'auth_error', 'error' );
			return $results;
		} else {
			// TODO: What to do here?
			return false;
		}
	}

	/**
	 *
	 */
	public static function redirect_error() {
		// TODO: implement
		self::redirect_success();
	}

	/**
	 *
	 */
	public static function redirect_success() {
		wp_redirect( remove_query_arg( array( '_wp_http_referer', '_wpnonce', 'delete_all', 'delete_all2', ), wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
		exit;
	}
}

Delete_All::register_hooks();
