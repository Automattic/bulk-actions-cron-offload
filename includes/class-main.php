<?php
/**
 * Plugin's main class, dispatcher for specific bulk-action requests
 *
 * @package Bulk_Actions_Cron_Offload
 */

namespace Automattic\WP\Bulk_Actions_Cron_Offload;

/**
 * Class Main
 */
class Main {
	/**
	 * Prefix for bulk-process hook invoked by request-specific classes
	 */
	const ACTION = 'bulk_actions_cron_offload_';

	/**
	 * Generic admin notices
	 */
	const ADMIN_NOTICE_KEY = 'bulk_actions_cron_offload_general';

	/**
	 * Common cron action
	 */
	const CRON_EVENT = 'bulk_actions_cron_offload';

	/**
	 * Register actions
	 */
	public static function load() {
		add_action( self::CRON_EVENT, array( __CLASS__, 'do_cron' ) );

		// TODO: add for upload.php and edit-comments.php. Anything else?
		add_action( 'load-edit.php', array( __CLASS__, 'intercept' ) );

		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
	}

	/**
	 * Run appropriate cron callback
	 *
	 * @param object $vars Bulk-request variables.
	 */
	public static function do_cron( $vars ) {
		do_action( self::build_cron_hook( $vars->action ), $vars );
	}

	/**
	 * Call appropriate handler
	 */
	public static function intercept() {
		// Nothing to do.
		if ( ! self::should_intercept_request() ) {
			return;
		}

		// Validate request.
		check_admin_referer( 'bulk-posts' );

		// Parse request to determine what to do.
		$vars   = self::capture_vars();
		$action = self::build_hook( $vars->action );

		// What kind of action is this?
		if ( self::is_core_action( $vars->action ) ) {
			// Nothing to do, unless we're emptying the trash.
			if ( empty( $vars->posts ) && 'delete_all' !== $vars->action ) {
				self::do_admin_redirect( self::ADMIN_NOTICE_KEY, false );
			}
		}

		// Pass request to a class to handle offloading to cron, UX, etc.
		do_action( $action, $vars );

		// Only skip Core's default handling when action is offloaded.
		if ( has_action( $action ) ) {
			self::skip_core_processing();
		}
	}

	/**
	 * Determine if current request is a bulk action
	 */
	private static function should_intercept_request() {
		if ( isset( $_REQUEST['delete_all'] ) || isset( $_REQUEST['delete_all2'] ) ) {
			return true;
		} elseif ( isset( $_REQUEST['action'] ) && '-1' !== $_REQUEST['action'] ) {
			return true;
		} elseif ( isset( $_REQUEST['action2'] ) && '-1' !== $_REQUEST['action2'] ) {
			return true;
		}

		return false;
	}

	/**
	 * Capture relevant variables
	 */
	private static function capture_vars() {
		$vars = array( 'action', 'custom_action', 'user_id', 'current_screen' ); // Extra data that normally would be available from the context.
		$vars = array_merge( $vars, self::get_supported_vars() );
		$vars = (object) array_fill_keys( $vars, null );

		// All permissions checks must be re-implemented!
		$vars->user_id = get_current_user_id();

		// Some dynamic hooks need screen data, but we don't need help and other private data.
		// Fortunately, Core's private convention is used in the \WP_Screen class.
		$screen = get_current_screen();
		$screen = get_object_vars( $screen );
		$screen = array_filter( $screen, function( $key ) {
			return 0 !== strpos( $key, '_' );
		}, ARRAY_FILTER_USE_KEY );
		$vars->current_screen = (object) $screen;
		unset( $screen );

		// Remainder of data comes from $_REQUEST
		if ( isset( $_REQUEST['delete_all'] ) || isset( $_REQUEST['delete_all2'] ) ) {
			$vars->action = 'delete_all';
		} elseif ( isset( $_REQUEST['action'] ) && '-1' !== $_REQUEST['action'] ) {
			$vars->action = $_REQUEST['action'];
		} elseif ( isset( $_REQUEST['action2'] ) && '-1' !== $_REQUEST['action2'] ) {
			$vars->action = $_REQUEST['action2'];
		}

		if ( isset( $_REQUEST['post_type'] ) && ! empty( $_REQUEST['post_type'] ) ) {
			$vars->post_type = $_REQUEST['post_type'];
		}

		if ( isset( $_REQUEST['post'] ) && is_array( $_REQUEST['post'] ) ) {
			$vars->posts = array_map( 'absint', $_REQUEST['post'] );
		}

		if ( isset( $_REQUEST['tax_input'] ) && is_array( $_REQUEST['tax_input'] ) ) {
			$vars->tax_input = $_REQUEST['tax_input'];
		}

		if ( isset( $_REQUEST['post_category'] ) && is_array( $_REQUEST['post_category'] ) ) {
			$vars->post_category = $_REQUEST['post_category'];
		}

		if ( isset( $_REQUEST['post_author'] ) && -1 !== (int) $_REQUEST['post_author'] ) {
			$vars->post_author = (int) $_REQUEST['post_author'];
		}

		if ( isset( $_REQUEST['comment_status'] ) && ! empty( $_REQUEST['comment_status'] ) ) {
			$vars->comment_status = $_REQUEST['comment_status'];
		}

		if ( isset( $_REQUEST['ping_status'] ) && ! empty( $_REQUEST['ping_status'] ) ) {
			$vars->ping_status = $_REQUEST['ping_status'];
		}

		if ( isset( $_REQUEST['_status'] ) && '-1' !== $_REQUEST['_status'] ) {
			$vars->post_status = $_REQUEST['_status'];
		}

		if ( isset( $_REQUEST['sticky'] ) && '-1' !== $_REQUEST['sticky'] ) {
			$vars->post_sticky = $_REQUEST['sticky'];
		}

		if ( isset( $_REQUEST['post_format'] ) && '-1' !== $_REQUEST['post_format'] ) {
			$vars->post_format = $_REQUEST['post_format'];
		}

		if ( isset( $_REQUEST['post_parent'] ) && '-1' !== $_REQUEST['post_parent'] ) {
			$vars->post_parent = (int) $_REQUEST['post_parent'];
		}

		if ( isset( $_REQUEST['page_template'] ) && '-1' !== $_REQUEST['page_template'] ) {
			$vars->page_template = $_REQUEST['page_template'];
		}

		if ( isset( $_REQUEST['post_password'] ) && ! empty( $_REQUEST['post_password'] ) ) {
			$vars->post_password = $_REQUEST['post_password'];
		}

		// Post status is special.
		if ( is_null( $vars->post_status ) && isset( $_REQUEST['post_status'] ) && ! empty( $_REQUEST['post_status'] ) ) {
			$vars->post_status = $_REQUEST['post_status'];
		}

		// Another special case, dependent on post status.
		if ( isset( $_REQUEST['keep_private'] ) && 'private' === $vars->post_status ) {
			$vars->keep_private = true;
		}

		// Standardize custom actions.
		if ( ! self::is_core_action( $vars->action ) ) {
			$vars->custom_action = $vars->action;
			$vars->action        = 'custom';
		}

		return $vars;
	}

	/**
	 * List allowed $_REQUEST variables
	 *
	 * @return array
	 */
	private static function get_supported_vars() {
		return array(
			'comment_status',
			'keep_private',
			'page_template',
			'ping_status',
			'post_author',
			'post_category',
			'post_format',
			'post_parent',
			'post_password',
			'post_status',
			'post_sticky',
			'post_type',
			'posts',
			'tax_input',
		);
	}

	/**
	 * Is this one of Core's default actions, or a custom action
	 *
	 * @param  string $action Action parsed from request vars.
	 * @return bool
	 */
	public static function is_core_action( $action ) {
		$core_actions = array(
			'delete', // class Delete_Permanently.
			'delete_all', // class Delete_All.
			'edit', // class Edit.
			'trash', // class Move_To_Trash.
			'untrash', // class Restore_From_Trash.
		);

		return in_array( $action, $core_actions, true );
	}

	/**
	 * Let the user know what's going on
	 */
	public static function admin_notices() {
		if ( isset( $_REQUEST[ self::ADMIN_NOTICE_KEY ] ) && '-1' === $_REQUEST[ self::ADMIN_NOTICE_KEY ] ) {
			self::render_admin_notice( 'error', __( 'The requested bulk action was not processed because no posts were selected.', 'bulk-actions-cron-offload' ) );
		}
	}

	/**
	 * Build a WP hook specific to a bulk request
	 *
	 * @param  string $action Bulk action to offload.
	 * @return string
	 */
	public static function build_hook( $action ) {
		if ( ! self::is_core_action( $action ) ) {
			$action = 'custom';
		}

		return self::ACTION . $action;
	}

	/**
	 * Build a cron hook specific to a bulk request
	 *
	 * @param  string $action Bulk action to register cron callback for.
	 * @return string
	 */
	public static function build_cron_hook( $action ) {
		return self::ACTION . $action . '_callback';
	}

	/**
	 * Unset flags Core uses to trigger bulk processing
	 */
	private static function skip_core_processing() {
		unset( $_REQUEST['action'] );
		unset( $_REQUEST['action2'] );
		unset( $_REQUEST['delete_all'] );
		unset( $_REQUEST['delete_all2'] );
	}

	/**
	 * Redirect, including a flag to indicate if the bulk process was scheduled successfully
	 *
	 * @param string $return_key  Key to include in redirect URL to flag request's origin, use for admin feedback, etc.
	 * @param bool   $succeeded   Whether or not the bulk-delete was scheduled.
	 * @param array  $extra_keys  Optional. Array of additional action keys to remove from redirect URL.
	 */
	public static function do_admin_redirect( $return_key, $succeeded = false, $extra_keys = array() ) {
		$redirect = wp_get_referer();

		if ( ! $redirect ) {
			$redirect = wp_unslash( $_SERVER['REQUEST_URI'] );
			$redirect = remove_query_arg( array( '_wp_http_referer', '_wpnonce' ), $redirect );
		}

		// Remove arguments that could re-trigger this bulk action.
		// Taken from wp-admin/edit.php.
		$action_keys = array( 'action', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status', 'post', 'bulk_edit', 'post_view' );
		$action_keys = array_merge( $action_keys, $extra_keys );
		$redirect    = remove_query_arg( $action_keys, $redirect );

		// Add a flag for the admin notice.
		$redirect = add_query_arg( $return_key, $succeeded ? 1 : -1, $redirect );

		$redirect = esc_url_raw( $redirect );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Render an admin message of a given type
	 *
	 * @param string $type Message type.
	 * @param string $message Message to output.
	 * @return void
	 */
	public static function render_admin_notice( $type, $message ) {
		// Lacking what's required.
		if ( empty( $type ) || empty( $message ) ) {
			return;
		}

		?>
		<div class="notice <?php echo esc_attr( 'notice-' . $type ); ?>">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
	}

	/**
	 * Create cron event
	 *
	 * @param object $vars Bulk-request variables.
	 * @return bool
	 */
	public static function schedule_processing( $vars ) {
		return false !== wp_schedule_single_event( time(), self::CRON_EVENT, array( $vars ) );
	}

	/**
	 * Retrieve timestamp for next scheduled event with given vars
	 *
	 * @param object $vars Bulk-request variables.
	 * @return int
	 */
	public static function next_scheduled( $vars ) {
		return (int) wp_next_scheduled( self::CRON_EVENT, array( $vars ) );
	}

	/**
	 * Find the next scheduled instance of a given action, regardless of arguments
	 *
	 * @param string $bulk_action Bulk action to filter by.
	 * @param  string $post_type Post type hook is scheduled for.
	 * @return array
	 */
	public static function get_action_next_scheduled( $bulk_action, $post_type ) {
		$events = get_option( 'cron' );

		if ( ! is_array( $events ) ) {
			return array();
		}

		foreach ( $events as $timestamp => $timestamp_events ) {
			// Skip non-event data that Core includes in the option.
			if ( ! is_numeric( $timestamp ) ) {
				continue;
			}

			foreach ( $timestamp_events as $action => $action_instances ) {
				if ( self::CRON_EVENT !== $action ) {
					continue;
				}

				foreach ( $action_instances as $instance => $instance_args ) {
					$vars = array_shift( $instance_args['args'] );

					if ( $bulk_action === $vars->action && $post_type === $vars->post_type ) {
						return array(
							'timestamp' => $timestamp,
							'args'      => $vars,
						);
					}
				}
			}
		}

		// No matching event found.
		return array();
	}

	/**
	 * Gather pending events for given conditions
	 *
	 * @param string $bulk_action Bulk action to filter by.
	 * @param string $post_type Post type needing exclusion.
	 * @param string $post_status Post status to filter by.
	 * @return array
	 */
	public static function get_all_pending_events_for_action( $bulk_action, $post_type, $post_status ) {
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
				if ( self::CRON_EVENT !== $action ) {
					continue;
				}

				foreach ( $action_instances as $instance => $instance_args ) {
					$vars = array_shift( $instance_args['args'] );

					if ( $bulk_action === $vars->action && $post_type === $vars->post_type ) {
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
	 * Gather IDs of objects for given conditions
	 *
	 * @param string $bulk_action Bulk action to filter by.
	 * @param string $post_type Post type needing exclusion.
	 * @param string $post_status Post status to filter by.
	 * @return array
	 */
	public static function get_post_ids_for_pending_events( $bulk_action, $post_type, $post_status ) {
		$events = wp_list_pluck( self::get_all_pending_events_for_action( $bulk_action, $post_type, $post_status ), 'args' );
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

Main::load();
