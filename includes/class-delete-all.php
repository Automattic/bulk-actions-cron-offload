<?php

namespace Automattic\WP\Bulk_Edit_Cron_Offload;

class Delete_All {
	/**
	 * Class constants
	 */
	const CRON_EVENT = 'a8c_bulk_edit_delete_all';

	const ADMIN_NOTICE_KEY = 'a8c_bulk_edit_deleted_all';

	/**
	 * Register this bulk process' hooks
	 */
	public static function register_hooks() {
		add_action( self::CRON_EVENT, array( __CLASS__, 'process_via_cron' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
	}

	/**
	 *
	 */
	public static function process( $vars ) {
		// TODO: Add hook to hide posts when their deletion is pending

		// TODO: Insufficient, need to check regardless of args :(
		$existing_event_ts = wp_next_scheduled( self::CRON_EVENT, array( $vars ) );

		if ( $existing_event_ts ) {
			self::redirect( false );
		} else {
			wp_schedule_single_event( time(), self::CRON_EVENT, array( $vars ) );

			self::redirect( true );
		}
	}

	/**
	 *
	 */
	public static function process_via_cron( $vars ) {
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
	 * Redirect, including a flag to indicate if the bulk process was scheduled successfully
	 *
	 * @param bool $succeed Whether or not the bulk-delete was scheduled
	 */
	public static function redirect( $succeed = false ) {
		$redirect = wp_unslash( $_SERVER['REQUEST_URI'] );

		// Remove arguments that could re-trigger this bulk-edit
		$redirect = remove_query_arg( array( '_wp_http_referer', '_wpnonce', 'delete_all', 'delete_all2', ), $redirect );

		// Add a flag for the admin notice
		$redirect = add_query_arg( self::ADMIN_NOTICE_KEY, $succeed ? 1 : -1, $redirect );

		wp_redirect( $redirect );
		exit;
	}

	/**
	 * Let the user know what's going on
	 */
	public function admin_notices() {
		if ( ! isset( $_REQUEST[ self::ADMIN_NOTICE_KEY ] ) ) {
			return;
		}

		if ( 1 === (int) $_REQUEST[ self::ADMIN_NOTICE_KEY ] ) {
			$class   = 'notice-success';
			$message = __( 'Success! The trash will be emptied soon.', 'automattic-bulk-edit-cron-offload' );
		} else {
			$class   = 'notice-error';
			$message = __( 'An error occurred while emptying the trash. Please try again.', 'automattic-bulk-edit-cron-offload' );
		}

		?>
		<div class="notice <?php echo esc_attr( $class ); ?>">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}
}

Delete_All::register_hooks();
