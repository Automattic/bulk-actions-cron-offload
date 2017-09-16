<?php
/**
 * Strip one-time arguments after redirects
 *
 * @package Bulk_Actions_Cron_Offload
 */

namespace Automattic\WP\Bulk_Actions_Cron_Offload;

/**
 * Trait Remove_One_Time_Args
 */
trait Remove_One_Time_Args {
	/**
	 * Strip the custom notice key, otherwise it turns up in pagination and other unwanted places.
	 *
	 * @param array $args Array of one-time query args.
	 * @return array
	 */
	public static function remove_notice_arg( $args ) {
		$args[] = self::ADMIN_NOTICE_KEY;

		return $args;
	}
}
