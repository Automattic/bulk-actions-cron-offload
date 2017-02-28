<?php

namespace Automattic\WP\Bulk_Edit_Cron_Offload;

class Delete_All {
	/**
	 *
	 */
	private static $vars = null;

	/**
	 *
	 */
	public static function process( $vars ) {
		self::$vars = $vars;
	}
}
