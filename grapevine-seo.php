<?php
/**
 * Plugin Name:  Grapevine SEO
 * Plugin URI:   https://github.com/webkeith/grapevine-seo
 * Description:  JSON-LD Schema markup + full SEO analysis engine with per-page scoring, site-wide reports, and rich results compliance.
 * Version:      2.0.0
 * Author:       Keith Quinones
 * Author URI:   https://github.com/webkeith
 * License:      GPL v2 or later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  grapevine-seo
 * Domain Path:  /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Update URI:   https://github.com/webkeith/grapevine-seo
 *
 * @package GrapevineSEO
 *
 * ──────────────────────────────────────────────────────────────────────────
 *  GITHUB UPDATE CHECKER SETUP
 * ──────────────────────────────────────────────────────────────────────────
 *  1. Replace webkeith with your real GitHub username above and
 *     in the GVSEO_GITHUB_REPO constant below.
 *  2. Push releases to GitHub using a tag that matches the Version header
 *     (e.g. tag "v2.0.1" → WordPress sees version "2.0.1").
 *  3. For PRIVATE repos: set GVSEO_GITHUB_TOKEN to a Personal Access Token
 *     with "repo" scope. For PUBLIC repos leave it empty — no token needed.
 *  4. The plugin-update-checker library lives in lib/plugin-update-checker/
 *     and is bundled with the plugin (no Composer required on the site).
 * ──────────────────────────────────────────────────────────────────────────
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ── Plugin constants ─────────────────────────────────── */
define( 'GVSEO_VERSION',     '2.0.0' );
define( 'GVSEO_DIR',         plugin_dir_path( __FILE__ ) );
define( 'GVSEO_URL',         plugin_dir_url( __FILE__ ) );
define( 'GVSEO_BASE',        plugin_basename( __FILE__ ) );
define( 'GVSEO_FILE',        __FILE__ );

/* ── GitHub update settings — EDIT THESE ─────────────── */
define( 'GVSEO_GITHUB_REPO',  'https://github.com/webkeith/grapevine-seo' );
define( 'GVSEO_GITHUB_TOKEN', '' ); // Leave empty for public repos.
                                  // For private repos: paste a Personal Access Token here,
                                  // or better — store it via wp-config.php:
                                  //   define('GVSEO_GITHUB_TOKEN', 'ghp_xxxxxxxxxxxxxxxxxxxx');

/* ── Bootstrap Plugin Update Checker ─────────────────── */
require_once GVSEO_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$gvseo_updater = PucFactory::buildUpdateChecker(
    GVSEO_GITHUB_REPO,   // Your GitHub repo URL
    GVSEO_FILE,          // Full path to the main plugin file
    'grapevine-seo'   // Plugin slug (must match the folder name exactly)
);

// Tell PUC to use GitHub Releases as the update source.
// Create a GitHub Release tagged "v2.0.1" → WordPress will see version 2.0.1.
$gvseo_updater->setBranch( 'main' );             // or 'master' — branch to track
$gvseo_updater->getVcsApi()->enableReleaseAssets(); // use Release assets (the ZIP) if present

// Private repo: attach a Personal Access Token.
if ( defined( 'GVSEO_GITHUB_TOKEN' ) && GVSEO_GITHUB_TOKEN ) {
    $gvseo_updater->setAuthentication( GVSEO_GITHUB_TOKEN );
}

/* ── Main plugin class (singleton) ───────────────────── */
final class Grapevine_SEO {

    private static $instance = null;

    public static function get() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_files();
        add_action( 'admin_menu',            [ $this, 'menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );
        add_filter( 'plugin_action_links_' . GVSEO_BASE, [ $this, 'action_links' ] );
    }

    private function load_files() {
        require_once GVSEO_DIR . 'includes/class-gvseo-settings.php';
        require_once GVSEO_DIR . 'includes/class-gvseo-frontend.php';
        require_once GVSEO_DIR . 'includes/class-gvseo-meta-box.php';
        require_once GVSEO_DIR . 'includes/class-gvseo-dashboard.php';
        require_once GVSEO_DIR . 'includes/class-gvseo-seo-analyzer.php';
        require_once GVSEO_DIR . 'includes/class-gvseo-seo-page.php';
        require_once GVSEO_DIR . 'includes/class-gvseo-woo-bridge.php';
        require_once GVSEO_DIR . 'includes/class-gvseo-upgrader.php';
        require_once GVSEO_DIR . 'includes/class-gvseo-version-page.php';
    }

    /* ── Admin menus ──────────────────────────────────── */
    public function menus() {
        $icon = 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.8"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>'
        );

        add_menu_page(
            'Grapevine SEO', 'Grapevine SEO', 'manage_options',
            'grapevine-seo', [ 'GVSEO_Dashboard', 'render' ], $icon, 58
        );
        add_submenu_page( 'grapevine-seo', 'Schema Dashboard', 'Schema Dashboard',
            'manage_options', 'grapevine-seo', [ 'GVSEO_Dashboard', 'render' ] );
        add_submenu_page( 'grapevine-seo', 'SEO Analysis', 'SEO Analysis',
            'manage_options', 'grapevine-seo-seo', [ 'GVSEO_SEO_Page', 'render' ] );
        add_submenu_page( 'grapevine-seo', 'Global Settings', 'Global Settings',
            'manage_options', 'grapevine-seo-settings', [ 'GVSEO_Settings', 'render' ] );
        add_submenu_page( 'grapevine-seo', 'Version Control', 'Version Control',
            'manage_options', 'grapevine-seo-version', [ 'GVSEO_Version_Page', 'render' ] );
    }

    /* ── Admin assets ─────────────────────────────────── */
    public function assets( $hook ) {
        $pages = [
            'toplevel_page_grapevine-seo',
            'grapevine-seo_page_grapevine-seo-seo',
            'grapevine-seo_page_grapevine-seo-settings',
            'grapevine-seo_page_grapevine-seo-version',
            'post.php', 'post-new.php',
        ];
        if ( ! in_array( $hook, $pages, true ) ) {
            return;
        }

        wp_enqueue_style(
            'gvseo-admin', GVSEO_URL . 'admin/css/admin.css', [], GVSEO_VERSION
        );
        wp_enqueue_script(
            'gvseo-chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            [], '4.4.0', true
        );
        wp_enqueue_script(
            'gvseo-admin', GVSEO_URL . 'admin/js/admin.js',
            [ 'jquery', 'gvseo-chartjs' ], GVSEO_VERSION, true
        );
        wp_localize_script( 'gvseo-admin', 'RAS', [
            'ajax'    => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'gvseo_nonce' ),
            'siteUrl' => get_bloginfo( 'url' ),
        ] );
    }

    /* ── Plugin action links ──────────────────────────── */
    public function action_links( $links ) {
        return array_merge( [
            '<a href="' . admin_url( 'admin.php?page=grapevine-seo-seo' ) . '">SEO Analysis</a>',
            '<a href="' . admin_url( 'admin.php?page=grapevine-seo-settings' ) . '">Settings</a>',
        ], $links );
    }
}

/* ── Activation / deactivation ─────────────────────── */
register_activation_hook( __FILE__, function () {
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
        ] );
    }
    update_option( 'gvseo_version', GVSEO_VERSION );
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
    flush_rewrite_rules();
} );

/* ── Boot ──────────────────────────────────────────── */
add_action( 'plugins_loaded', function () {
    Grapevine_SEO::get();
    // Run pending DB migrations after plugin classes are loaded.
    if ( class_exists( 'GVSEO_Upgrader' ) ) {
        GVSEO_Upgrader::maybe_upgrade();
    }
} );
