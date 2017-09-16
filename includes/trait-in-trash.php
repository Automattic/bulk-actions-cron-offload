<?php
/**
 * Overrides for when viewing the trash
 *
 * @package Bulk_Actions_Cron_Offload
 */

namespace Automattic\WP\Bulk_Actions_Cron_Offload;

/**
 * Trait In_Trash
 */
trait In_Trash {
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
	 * When a restore is pending for a given post type, hide those posts in the admin
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
