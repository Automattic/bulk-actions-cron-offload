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
		add_action( Main::build_hook( 'delete_all' ), array( __CLASS__, 'process' ) );
		add_action( self::CRON_EVENT, array( __CLASS__, 'process_via_cron' ) );

		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
		add_filter( 'posts_where', array( __CLASS__, 'hide_posts_pending_delete' ), 999, 2 );

		// Limit when caps are intercepted, given frequent execution of the `map_meta_cap` filter
		add_action( 'load-edit.php', function() {
			add_filter( 'map_meta_cap', array( __CLASS__, 'hide_empty_trash_pending_delete' ), 10, 2 );
		} );
	}

	/**
	 * Handle a request to delete all trashed items for a given post type
	 */
	public static function process( $vars ) {
		$action_scheduled = self::action_next_scheduled( self::CRON_EVENT, $vars->post_type );

		if ( empty( $action_scheduled ) ) {
			wp_schedule_single_event( time(), self::CRON_EVENT, array( $vars ) );

			self::redirect( true );
		} else {
			self::redirect( false );
		}
	}

	/**
	 * Cron callback to delete trashed items in a given post type
	 */
	public static function process_via_cron( $vars ) {
		global $wpdb;

		$post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_status = %s", $vars->post_type, $vars->post_status ) );

		$count = 0;

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

				// Try deleting
				$post_deleted = wp_delete_post( $post_id );
				if ( $post_deleted ) {
					$deleted[] = $post_id;
				} else {
					$error[] = $post_id;
				}

				// Take a break periodically
				if ( 0 === $count++ % 50 ) {
					stop_the_insanity();
				}
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
	 * @param bool $succeeded Whether or not the bulk-delete was scheduled
	 */
	public static function redirect( $succeeded = false ) {
		$redirect = wp_unslash( $_SERVER['REQUEST_URI'] );

		// Remove arguments that could re-trigger this bulk-edit
		$redirect = remove_query_arg( array( '_wp_http_referer', '_wpnonce', 'delete_all', 'delete_all2', ), $redirect );

		// Add a flag for the admin notice
		$redirect = add_query_arg( self::ADMIN_NOTICE_KEY, $succeeded ? 1 : -1, $redirect );

		$redirect = esc_url_raw( $redirect );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Let the user know what's going on
	 */
	public static function admin_notices() {
		$screen = get_current_screen();

		if ( isset( $_REQUEST[ self::ADMIN_NOTICE_KEY ] ) ) {
			if ( 1 === (int) $_REQUEST[self::ADMIN_NOTICE_KEY] ) {
				$class = 'notice-success';
				$message = __( 'Success! The trash will be emptied soon.', 'automattic-bulk-edit-cron-offload' );
			} else {
				$class = 'notice-error';
				$message = __( 'A request to empty the trash is already pending for this post type.', 'automattic-bulk-edit-cron-offload' );
			}
		} elseif ( 'edit' === $screen->base && isset( $_REQUEST['post_status'] ) && 'trash' === $_REQUEST['post_status'] ) {
			if ( self::action_next_scheduled( self::CRON_EVENT, $screen->post_type ) ) {
				$class   = 'notice-warning';
				$message = __( 'A pending request to empty the trash will be processed soon.', 'automattic-bulk-edit-cron-offload' );
			}
		}

		// Nothing to display
		if ( ! isset( $class ) || ! isset( $message ) ) {
			return;
		}

		?>
		<div class="notice <?php echo esc_attr( $class ); ?>">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}

	/**
	 * When a delete is pending for a given post type, hide those posts in the admin
	 */
	public static function hide_posts_pending_delete( $where, $q ) {
		if ( ! is_admin() || ! $q->is_main_query() ) {
			return $where;
		}

		if ( 'edit' !== get_current_screen()->base ) {
			return $where;
		}

		if ( 'trash' !== $q->get( 'post_status' ) ) {
			return $where;
		}

		if ( self::action_next_scheduled( self::CRON_EVENT, $q->get( 'post_type' ) ) ) {
			$where .= ' AND 0=1';
		}

		return $where;
	}

	/**
	 * Suppress "Empty Trash" button when purge is pending
	 *
	 * Core doesn't provide a filter specifically for this, but permissions are checked before showing the button
	 *
	 * @param  array  $caps User's capabilities
	 * @param  string $cap  Cap currently being checked
	 * @return array
	 */
	public static function hide_empty_trash_pending_delete( $caps, $cap ) {
		// Button we're blocking only shows for the "trash" status, understandably
		if ( ! isset( $_REQUEST['post_status'] ) || 'trash' !== $_REQUEST['post_status'] ) {
			return $caps;
		}

		// Get post type as Core envisions
		$screen = get_current_screen();

		// Cap used to display button, per WP_Posts_List_Table::extra_tablenav()
		$cap_to_block = get_post_type_object( $screen->post_type )->cap->edit_others_posts;

		// The current cap isn't the one we're looking for
		if ( $cap !== $cap_to_block ) {
			return $caps;
		}

		// There isn't a pending purge, so one should be permitted
		if ( ! self::action_next_scheduled( self::CRON_EVENT, $screen->post_type ) ) {
			return $caps;
		}

		// Block the edit button by disallowing its cap
		$caps[] = 'do_not_allow';

		return $caps;
	}

	/**
	 * Find the next scheduled instance of a given action, regardless of arguments
	 *
	 * @param  string $action_to_check Hook to search for
	 * @param  string $post_type       Post type hook is scheduled for
	 * @return array
	 */
	private static function action_next_scheduled( $action_to_check, $post_type ) {
		$events = get_option( 'cron' );

		if ( ! is_array( $events ) ) {
			return array();
		}

		foreach ( $events as $timestamp => $timestamp_events ) {
			// Skip non-event data that Core includes in the option
			if ( ! is_numeric( $timestamp ) ) {
				continue;
			}

			foreach ( $timestamp_events as $action => $action_instances ) {
				if ( $action !== $action_to_check ) {
					continue;
				}

				foreach ( $action_instances as $instance => $instance_args ) {
					$vars = array_shift( $instance_args['args'] );

					if ( $post_type === $vars->post_type ) {
						return array( 'timestamp' => $timestamp, 'args' => $vars, );
					}
				}
			}
		}

		// No matching event found
		return array();
	}
}

Delete_All::register_hooks();
