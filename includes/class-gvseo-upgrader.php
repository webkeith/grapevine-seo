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
        '2.5.0' => 'migrate_2_5_0',
        '2.6.0' => 'migrate_2_6_0',
        '2.7.0' => 'migrate_2_7_0',
        '2.10.0' => 'migrate_2_10_0',
        '2.11.0' => 'migrate_2_11_0',
        '2.12.0' => 'migrate_2_12_0',
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
                error_log( '[Grapevine SEO] Migration error for ' . $version . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
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
        $deleted = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
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
        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_gvseo_seo_results'"
        );
        // Set a flag so the sitemap class flushes rewrite rules on the next
        // 'init' action — we cannot call add_rewrite_rule() here because
        // $wp_rewrite is not yet initialised during plugins_loaded.
        update_option( 'gvseo_flush_rewrite_rules', '1' );
        return 'SEO result cache cleared. Rewrite rules will flush on next page load.';
    }

    /**
     * v2.5.0 — TikTok social field + compatibility detection.
     * Ensures social_tt key exists in existing settings.
     */
    private static function migrate_2_5_0() {
        $settings = get_option( 'gvseo_global_settings', [] );
        if ( ! array_key_exists( 'social_tt', $settings ) ) {
            $settings['social_tt'] = '';
            update_option( 'gvseo_global_settings', $settings );
        }
        return 'TikTok social field added.';
    }

    /**
     * v2.6.0 — LocalBusiness schema (separate entity from Organization).
     * Adds all lb_* keys to existing installs.
     */
    private static function migrate_2_6_0() {
        $settings = get_option( 'gvseo_global_settings', [] );
        $changed  = false;

        // Migrate old flat lb_* fields into lb_locations[0] if they existed.
        $old_fields = [ 'lb_enabled', 'lb_type', 'lb_name', 'lb_street', 'lb_city' ];
        $has_old    = false;
        foreach ( $old_fields as $f ) {
            if ( array_key_exists( $f, $settings ) ) { $has_old = true; break; }
        }

        if ( $has_old && empty( $settings['lb_locations'] ) ) {
            $settings['lb_locations'] = [ [
                'enabled'     => $settings['lb_enabled']    ?? '0',
                'type'        => $settings['lb_type']        ?? 'LocalBusiness',
                'name'        => $settings['lb_name']        ?? '',
                'description' => $settings['lb_description'] ?? '',
                'phone'       => $settings['lb_phone']       ?? '',
                'email'       => $settings['lb_email']       ?? '',
                'street'      => $settings['lb_street']      ?? '',
                'city'        => $settings['lb_city']        ?? '',
                'state'       => $settings['lb_state']       ?? '',
                'postcode'    => $settings['lb_postcode']    ?? '',
                'country'     => $settings['lb_country']     ?? '',
                'lat'         => $settings['lb_lat']         ?? '',
                'lng'         => $settings['lb_lng']         ?? '',
                'maps_url'    => $settings['lb_maps_url']    ?? '',
                'price_range' => $settings['lb_price_range'] ?? '',
                'payment'     => $settings['lb_payment']     ?? '',
                'currencies'  => $settings['lb_currencies']  ?? 'AUD',
                'area_served' => $settings['lb_area_served'] ?? '',
                'hours'       => $settings['lb_hours']       ?? [],
                'same_as_org' => $settings['lb_same_as_org'] ?? '1',
            ] ];
            // Remove old flat keys
            foreach ( array_keys( $settings ) as $k ) {
                if ( strpos( $k, 'lb_' ) === 0 && $k !== 'lb_locations' ) {
                    unset( $settings[ $k ] );
                }
            }
            $changed = true;
        } elseif ( ! array_key_exists( 'lb_locations', $settings ) ) {
            $settings['lb_locations'] = [];
            $changed = true;
        }

        if ( $changed ) { update_option( 'gvseo_global_settings', $settings ); }
        return 'LocalBusiness locations schema migrated to multi-location structure.';
    }

    /**
     * v2.7.0 — Organization founder field + department built from lb_locations.
     */
    private static function migrate_2_7_0() {
        $settings = get_option( 'gvseo_global_settings', [] );
        if ( ! array_key_exists( 'org_founder', $settings ) ) {
            $settings['org_founder'] = '';
            update_option( 'gvseo_global_settings', $settings );
        }
        return 'Organization founder field added.';
    }

    /**
     * v2.10.0 — Meta/OG tag output toggle.
     * Defaults to '1' (enabled) so existing sites see no change in behavior
     * until they explicitly disable it — e.g. sites already running Yoast
     * or Rank Math, where Grapevine's own OG tags create duplicates.
     */
    private static function migrate_2_10_0() {
        $settings = get_option( 'gvseo_global_settings', [] );
        if ( ! array_key_exists( 'meta_tags_enabled', $settings ) ) {
            $settings['meta_tags_enabled'] = '1';
            update_option( 'gvseo_global_settings', $settings );
        }
        return 'Meta/OG tag output toggle added.';
    }

    /**
     * v2.11.0 — Stable, slug-based @id for multi-location LocalBusiness schema.
     *
     * BREAKING (one-time, deliberate): prior versions built each location's
     * @id from its array index (#localbusiness, #localbusiness-1, ...), which
     * silently changed if a location was reordered or an earlier one removed
     * — the schema @id is supposed to be a permanent entity identifier, so an
     * index-based scheme was never actually stable. This migration backfills
     * a persistent slug for every existing location (derived from its name,
     * falling back to city, falling back to a positional default), which the
     * frontend now uses to build @id as https://[domain]/[slug]/#business.
     *
     * After updating, each location's live @id on the site WILL change once
     * from the old #localbusiness / #localbusiness-N form to the new
     * slug-based form — re-run Google's Rich Results Test / Schema.org
     * validator against each location after this update to confirm both
     * resolve as distinct entities under their new @id.
     */
    private static function migrate_2_11_0() {
        $settings = get_option( 'gvseo_global_settings', [] );
        $locs     = $settings['lb_locations'] ?? [];
        if ( empty( $locs ) ) {
            return 'No LocalBusiness locations to migrate.';
        }

        $used  = [];
        $count = 0;
        foreach ( $locs as $li => &$loc ) {
            if ( ! empty( $loc['slug'] ) ) {
                $used[ sanitize_title( $loc['slug'] ) ] = true;
                continue;
            }
            $basis = $loc['name'] ?? '';
            if ( ! $basis ) { $basis = $loc['city'] ?? ''; }
            if ( ! $basis ) { $basis = 'location-' . ( (int) $li + 1 ); }
            $slug      = sanitize_title( $basis );
            $base_slug = $slug;
            $n = 2;
            while ( isset( $used[ $slug ] ) ) {
                $slug = $base_slug . '-' . $n++;
            }
            $used[ $slug ] = true;
            $loc['slug']   = $slug;
            $count++;
        }
        unset( $loc );

        if ( $count > 0 ) {
            $settings['lb_locations'] = $locs;
            update_option( 'gvseo_global_settings', $settings );
        }
        return $count . ' LocalBusiness location(s) assigned a stable slug/@id.';
    }

    /**
     * v2.12.0 — XML sitemap output toggle.
     * Defaults to '1' (enabled) — same non-breaking rollout pattern as
     * meta_tags_enabled in 2.10.0. Sites already running another plugin's
     * (e.g. Yoast) sitemap can turn Grapevine SEO's off under Global
     * Settings → XML Sitemap.
     */
    private static function migrate_2_12_0() {
        $settings = get_option( 'gvseo_global_settings', [] );
        if ( ! array_key_exists( 'sitemap_enabled', $settings ) ) {
            $settings['sitemap_enabled'] = '1';
            update_option( 'gvseo_global_settings', $settings );
        }
        return 'XML sitemap output toggle added.';
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
        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_gvseo_%'"
        );
    }
}
