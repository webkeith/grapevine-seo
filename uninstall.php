<?php
/**
 * Uninstall — Grapevine SEO
 * Removes all plugin data on deletion (not deactivation).
 * @package GrapevineSEO
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// 1. Plugin options.
$gvseo_option_keys = [
    'gvseo_global_settings',
    'gvseo_version',
    'gvseo_db_version',
    'gvseo_upgrade_history',
    'gvseo_seo_summary',
    'gvseo_flush_rewrite_rules',
];
foreach ( $gvseo_option_keys as $gvseo_opt ) {
    delete_option( $gvseo_opt );
}

// 2. All _gvseo_ post meta.
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_gvseo_%'"
);

// 3. Cached page head transients.
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_gvseo_head_%' OR option_name LIKE '_transient_timeout_gvseo_head_%'"
);

// 4. Scheduled cron hooks.
wp_clear_scheduled_hook( 'gvseo_scheduled_analysis' );
