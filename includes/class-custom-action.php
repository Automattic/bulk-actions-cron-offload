<?php
/**
 * Offload custom actions
 *
 * @package Bulk_Actions_Cron_Offload
 */

namespace Automattic\WP\Bulk_Actions_Cron_Offload;

/**
 * Class Custom_Action
 */
class Custom_Action {
	/**
	 * Common hooks and such
	 */
	use Bulk_Actions;

	/**
	 * Class constants
	 */
	const ACTION = 'custom';

	const ADMIN_NOTICE_KEY = 'bulk_actions_cron_offload_custom';

	/**
	 * Cron callback to run a custom bulk action
	 *
	 * Because bulk actions work off of a redirect by default, custom actions are
	 * processed in the filter for the redirect destination, normally allowing
	 * for customized messaging.
	 *
	 * @param object $vars Bulk-request variables.
	 */
	public static function process_via_cron( $vars ) {
		// Normally processed in the admin context.
		require_once( ABSPATH . 'wp-admin/includes/admin.php' );

		// Provide for capabilities checks.
		wp_set_current_user( $vars->user_id );

		// TODO: capture and repopulate $_REQUEST?
		// Rebuild something akin to the URL this would normally be filtering.
		$return_url = sprintf( '/wp-admin/%1$s.php', $vars->current_screen->base );
		$return_url = add_query_arg( array(
			'post_type'   => $vars->post_type,
			'post_status' => $vars->post_status,
		), $return_url );

		// Run the custom action as Core does. See note above.
		$return_url = apply_filters( 'handle_bulk_actions-' . $vars->current_screen->id, $return_url, $vars->action, $vars->posts );

		//
		$results = compact( 'return_url', 'vars' );
		do_action( 'bulk_actions_cron_offload_custom_request_completed', $results, $vars );
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

		if ( 'edit' === $screen->base ) {
			if ( isset( $_REQUEST['post_status'] ) && 'trash' === $_REQUEST['post_status'] ) {
				return;
			}

			$status  = isset( $_REQUEST['post_status'] ) ? $_REQUEST['post_status'] : 'all';
			$pending = Main::get_post_ids_for_pending_events( self::ACTION, $screen->post_type, $status );

			if ( ! empty( $pending ) ) {
				$type    = 'warning';
				$message = __( 'Some items that would normally be shown here are waiting to be processed. These items are hidden until processing completes.', 'bulk-actions-cron-offload' );
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
		return __( 'Success! The selected posts will be processed shortly.', 'bulk-actions-cron-offload' );
	}

	/**
	 * Provide post-redirect error message
	 *
	 * @retun string
	 */
	public static function admin_notice_error_message() {
		return __( 'The requested processing is already pending for the chosen posts.', 'bulk-actions-cron-offload' );
	}

	/**
	 * When an edit is pending for a given post type, hide those posts in the admin
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

Custom_Action::register_hooks();
