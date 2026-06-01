<?php
/**
 * Upgrader — Grapevine SEO
 *
 * Handles all version-to-version database migrations, option schema changes,
 * and post-update routines. Runs automatically on plugins_loaded when the
 * stored DB version differs from GVSEO_VERSION.
 *
 * Adding a new migration:
 *   1. Bump GVSEO_VERSION in grapevine-seo.php.
 *   2. Add a private static method named migrate_X_Y_Z() (e.g. migrate_2_1_0).
 *   3. Register it in the $migrations array below with the version string as key.
 *   4. Tag, release — the migration runs once on each site that updates.
 *
 * @package GrapevineSEO
 * @since   2.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class GVSEO_Upgrader {

    /** DB option key that stores the installed schema version. */
    const DB_VERSION_KEY = 'gvseo_db_version';

    /** Option key for the full upgrade history log. */
    const HISTORY_KEY    = 'gvseo_upgrade_history';

    /**
     * Ordered map of  version_string => migration_method.
     * Add new entries at the bottom. Each runs once per site, in order.
     *
     * @var array<string, string>
     */
    private static $migrations = [
        '2.0.0' => 'migrate_2_0_0',
        '2.1.0' => 'migrate_2_1_0',
        '2.2.0' => 'migrate_2_2_0',
        '2.3.0' => 'migrate_2_3_0',
        '2.4.0' => 'migrate_2_4_0',
    ];

    /* ═══════════════════════════════════════════════════════════════
       BOOTSTRAP — called from plugins_loaded
       ═══════════════════════════════════════════════════════════════ */

    /**
     * Check if an upgrade is needed and run pending migrations.
     */
    public static function maybe_upgrade() {
        $installed = get_option( self::DB_VERSION_KEY, '0.0.0' );

        // Nothing to do if already current.
        if ( version_compare( $installed, GVSEO_VERSION, '>=' ) ) {
            return;
        }

        self::run_migrations( $installed );
    }

    /**
     * Run all migrations newer than $from_version, in order.
     *
     * @param string $from_version The currently installed DB version.
     */
    private static function run_migrations( $from_version ) {
        $ran     = [];
        $errors  = [];

        foreach ( self::$migrations as $version => $method ) {
            // Skip migrations for versions already installed.
            if ( version_compare( $version, $from_version, '<=' ) ) {
                continue;
            }

            try {
                if ( method_exists( __CLASS__, $method ) ) {
                    $result = self::$method();
                    $ran[]  = [
                        'version' => $version,
                        'method'  => $method,
                        'result'  => is_string( $result ) ? $result : 'OK',
                        'time'    => time(),
                    ];
                }
            } catch ( Exception $e ) {
                $errors[] = "v$version: " . $e->getMessage();
                error_log( '[Grapevine SEO] Migration error for ' . $version . ': ' . $e->getMessage() );
            }
        }

        // Update stored DB version.
        update_option( self::DB_VERSION_KEY, GVSEO_VERSION, false );

        // Append to upgrade history log.
        if ( ! empty( $ran ) ) {
            $history   = self::get_history();
            $history[] = [
                'from'       => $from_version,
                'to'         => GVSEO_VERSION,
                'migrations' => $ran,
                'errors'     => $errors,
                'timestamp'  => time(),
                'wp_version' => get_bloginfo( 'version' ),
                'php_version'=> PHP_VERSION,
            ];
            update_option( self::HISTORY_KEY, $history, false );
        }
    }

    /* ═══════════════════════════════════════════════════════════════
       MIGRATIONS
       ═══════════════════════════════════════════════════════════════ */

    /**
     * v2.0.0 — Initial install.
     * Sets default global settings and creates the seo_summary option.
     */
    private static function migrate_2_0_0() {
        if ( ! get_option( 'gvseo_global_settings' ) ) {
            update_option( 'gvseo_global_settings', [
                'org_name'     => get_bloginfo( 'name' ),
                'org_url'      => get_bloginfo( 'url' ),
                'org_logo'     => '',
                'org_email'    => get_option( 'admin_email' ),
                'social_fb'    => '', 'social_tw' => '',
                'social_ig'    => '', 'social_li' => '', 'social_yt' => '',
                'breadcrumbs'  => '1',
                'sitelinks'    => '1',
                'organization' => '1',
                'woo_bridge'   => '1',
                'cpt_defaults' => [],
            ], false );
        }

        if ( ! get_option( GVSEO_SEO_Analyzer::OPTION_SUMMARY ) ) {
            update_option( GVSEO_SEO_Analyzer::OPTION_SUMMARY, [
                'total' => 0, 'excellent' => 0, 'good' => 0,
                'needs_work' => 0, 'poor' => 0, 'avg' => 0, 'ts' => 0,
            ], false );
        }

        return 'Default settings initialized.';
    }

    /**
     * v2.1.0 — Added WooCommerce bridge + CPT defaults.
     * Ensures existing installs have the new settings keys.
     */
    private static function migrate_2_1_0() {
        $settings = get_option( 'gvseo_global_settings', [] );
        $changed  = false;

        if ( ! isset( $settings['woo_bridge'] ) ) {
            $settings['woo_bridge'] = '1';
            $changed = true;
        }
        if ( ! isset( $settings['cpt_defaults'] ) ) {
            $settings['cpt_defaults'] = [];
            $changed = true;
        }

        if ( $changed ) {
            update_option( 'gvseo_global_settings', $settings );
        }

        return 'WooCommerce bridge and CPT defaults keys ensured.';
    }

    /**
     * v2.2.0 — Expanded SEO analyzer (60+ checks).
     * Clears all cached SEO scores so pages get re-analyzed with the new checks.
     */
    private static function migrate_2_2_0() {
        global $wpdb;

        // Delete all cached SEO scores and results — they'll be recalculated on demand.
        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->postmeta}
             WHERE meta_key IN ('_gvseo_seo_score', '_gvseo_seo_results', '_gvseo_seo_ts')"
        );

        // Reset site-wide summary.
        update_option( GVSEO_SEO_Analyzer::OPTION_SUMMARY, [
            'total' => 0, 'excellent' => 0, 'good' => 0,
            'needs_work' => 0, 'poor' => 0, 'avg' => 0, 'ts' => 0,
        ], false );

        return "Cleared $deleted cached SEO score rows — re-analysis required.";
    }

    /**
     * v2.3.0 — Address, phone, and exclusions added to org settings.
     * Ensures new keys exist on existing installs.
     */
    private static function migrate_2_3_0() {
        $settings = get_option( 'gvseo_global_settings', [] );
        $changed  = false;
        $new_keys = [
            'org_phone' => '', 'org_street' => '', 'org_city' => '',
            'org_state' => '', 'org_postcode' => '', 'org_country' => '',
            'org_addr2_enabled' => '0', 'org_addr2_name' => '',
            'org_addr2_street' => '', 'org_addr2_city' => '',
            'org_addr2_state' => '', 'org_addr2_postcode' => '',
            'org_addr2_country' => '', 'org_addr2_phone' => '',
            'excluded_types' => [], 'excluded_post_ids' => '',
        ];
        foreach ( $new_keys as $key => $default ) {
            if ( ! array_key_exists( $key, $settings ) ) {
                $settings[ $key ] = $default;
                $changed = true;
            }
        }
        if ( $changed ) { update_option( 'gvseo_global_settings', $settings ); }
        return 'Address, phone, and exclusion fields added to organization settings.';
    }

    /**
     * v2.4.0 — Sitemap, transition words, subheading distribution, secondary keywords.
     * Clears cached SEO results so new checks appear on next analysis.
     * Flushes rewrite rules so sitemap URLs resolve.
     */
    private static function migrate_2_4_0() {
        global $wpdb;
        // Clear cached results so new checks appear on next analysis.
        $wpdb->query(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_gvseo_seo_results'"
        );
        // Set a flag so the sitemap class flushes rewrite rules on the next
        // 'init' action — we cannot call add_rewrite_rule() here because
        // $wp_rewrite is not yet initialised during plugins_loaded.
        update_option( 'gvseo_flush_rewrite_rules', '1' );
        return 'SEO result cache cleared. Rewrite rules will flush on next page load.';
    }

    /* ═══════════════════════════════════════════════════════════════
       PUBLIC ACCESSORS
       ═══════════════════════════════════════════════════════════════ */

    /**
     * Get the full upgrade history log.
     *
     * @return array[]
     */
    public static function get_history() {
        return (array) get_option( self::HISTORY_KEY, [] );
    }

    /**
     * Get the installed DB version.
     */
    public static function get_db_version() {
        return get_option( self::DB_VERSION_KEY, '0.0.0' );
    }

    /**
     * List all registered migrations.
     */
    public static function get_migrations() {
        return self::$migrations;
    }

    /**
     * Force-run a specific migration by version (admin use only).
     *
     * @param  string $version Version string, e.g. '2.1.0'.
     * @return string|null     Result message or null if version not found.
     */
    public static function force_migration( $version ) {
        if ( ! current_user_can( 'manage_options' ) ) { return null; }
        if ( ! isset( self::$migrations[ $version ] ) ) { return null; }
        $method = self::$migrations[ $version ];
        return method_exists( __CLASS__, $method ) ? self::$method() : null;
    }

    /**
     * Clean uninstall: remove all plugin options and post meta.
     * Called from uninstall.php — NOT called on deactivation.
     */
    public static function uninstall() {
        global $wpdb;

        // Options.
        $option_keys = [
            'gvseo_global_settings', 'gvseo_version', self::DB_VERSION_KEY,
            self::HISTORY_KEY, GVSEO_SEO_Analyzer::OPTION_SUMMARY,
        ];
        foreach ( $option_keys as $key ) {
            delete_option( $key );
        }

        // Post meta — all _gvseo_ prefixed keys.
        $wpdb->query(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_gvseo_%'"
        );
    }
}
