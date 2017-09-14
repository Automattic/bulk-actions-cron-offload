<?php
/**
 * Plugin Name:     Bulk Actions Cron Offload
 * Plugin URI:      https://vip.wordpress.com/
 * Description:     Process Bulk Action requests using Cron
 * Author:          Erick Hitter, Automattic
 * Author URI:      https://automattic.com/
 * Text Domain:     bulk-actions-cron-offload
 * Domain Path:     /languages
 * Version:         1.0
 *
 * @package         Bulk_Actions_Cron_Offload
 */

namespace Automattic\WP\Bulk_Actions_Cron_Offload;

// Plugin dependencies.
require __DIR__ . '/includes/utils.php';

// Plugin functionality.
require __DIR__ . '/includes/class-main.php';
require __DIR__ . '/includes/class-delete-all.php';
require __DIR__ . '/includes/class-delete-permanently.php';
require __DIR__ . '/includes/class-edit.php';
require __DIR__ . '/includes/class-move-to-trash.php';
require __DIR__ . '/includes/class-restore-from-trash.php';
