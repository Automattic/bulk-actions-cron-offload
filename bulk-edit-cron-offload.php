<?php
/**
 * Plugin Name:     Bulk Edit Cron Offload
 * Plugin URI:      https://vip.wordpress.com/
 * Description:     Process Bulk Edit requests using Cron
 * Author:          Erick Hitter, Automattic
 * Author URI:      https://automattic.com/
 * Text Domain:     automattic-bulk-edit-cron-offload
 * Domain Path:     /languages
 * Version:         1.0
 *
 * @package         Bulk_Edit_Cron_Offload
 */

namespace Automattic\WP\Bulk_Edit_Cron_Offload;

// Plugin dependencies.
require __DIR__ . '/includes/class-singleton.php';

// Plugin functionality.
require __DIR__ . '/includes/class-main.php';
