<?php
/**
 * Plugin Name: JASR Webstats
 * Description: Loads your self-hosted Webstats tracker on public WordPress pages.
 * Version: 1.0.0
 */
declare(strict_types=1);
if (!defined('ABSPATH')) exit;
// Define JASR_WEBSTATS_TRACKER_URL in wp-config.php. No hard-coded host in reusable code.
add_action('wp_enqueue_scripts', static function (): void {
    if (!defined('JASR_WEBSTATS_TRACKER_URL') || current_user_can('manage_options')) return;
    wp_enqueue_script('jasr-webstats', JASR_WEBSTATS_TRACKER_URL, [], '1.0.0', true);
});
