<?php
/*
 Plugin Name: Offload Bulk Edit to Cron
 Plugin URI: https://vip.wordpress.com/
 Description: Process Bulk Edit requests using Cron
 Author: Erick Hitter, Automattic
 Version: 1.0
 Text Domain: automattic-bulk-edit-cron-offload
 */

namespace Automattic\WP\Bulk_Edit_Cron_Offload;

// Plugin functionality
require __DIR__ . '/includes/class-main.php';
require __DIR__ . '/includes/class-delete-all.php';
