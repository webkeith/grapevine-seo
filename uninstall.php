<?php
/**
 * Uninstall — Grapevine SEO
 *
 * Called by WordPress when the plugin is deleted (not just deactivated).
 * Removes all plugin data: options, post meta, scheduled events.
 *
 * @package GrapevineSEO
 */

// Abort if not called from WP uninstall context.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Load just enough to call the upgrader's clean uninstall.
if ( ! defined( 'GVSEO_DIR' ) ) {
    define( 'GVSEO_DIR', plugin_dir_path( __FILE__ ) );
}

// Inline clean-up (avoids loading the full plugin stack).
global $wpdb;

// 1. Plugin options.
$options = [
    'gvseo_global_settings',
    'gvseo_version',
    'gvseo_db_version',
    'gvseo_upgrade_history',
    'gvseo_seo_summary',
];
foreach ( $options as $opt ) {
    delete_option( $opt );
}

// 2. All _gvseo_ post meta (SEO scores, schema settings, SEO fields).
$wpdb->query(
    "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_gvseo_%'"
);

// 3. Clear all cached page head transients (gvseo_head_{post_id}).
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_gvseo_head_%' OR option_name LIKE '_transient_timeout_gvseo_head_%'"
);

// 4. Clear any scheduled cron jobs (none currently, but future-proofed).
wp_clear_scheduled_hook( 'gvseo_scheduled_analysis' );
