<?php
/**
 * Plugin utilities
 *
 * @package Bulk_Actions_Cron_Offload
 */

namespace Automattic\WP\Bulk_Actions_Cron_Offload;

/**
 * Free memory
 */
function stop_the_insanity() {
	global $wpdb, $wp_object_cache;

	$wpdb->queries = array();

	if ( ! is_object( $wp_object_cache ) ) {
		return;
	}

	$wp_object_cache->group_ops      = array();
	$wp_object_cache->stats          = array();
	$wp_object_cache->memcache_debug = array();
	$wp_object_cache->cache          = array();

	if ( is_callable( $wp_object_cache, '__remoteset' ) ) {
		$wp_object_cache->__remoteset(); // important!
	}
}
