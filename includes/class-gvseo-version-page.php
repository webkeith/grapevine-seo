<?php
/**
 * Version Control Admin Page — Grapevine SEO
 *
 * Shows: current version, full changelog, upgrade history log,
 * migration status table, and a manual re-run tool.
 *
 * @package GrapevineSEO
 * @since   2.2.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class GVSEO_Version_Page {

    /**
     * Full changelog — add a new entry at the top for every release.
     * Format: [ version, date, type, changes[] ]
     * Types: 'major' | 'minor' | 'patch'
     */
    public static function changelog() {
        return [
            [
                'version' => '2.2.0',
                'date'    => '2025-06-01',
                'type'    => 'minor',
                'summary' => 'Expanded SEO Analyzer — 60+ checks',
                'changes' => [
                    'added'   => [
                        '60+ SEO checks across 9 categories: Title, Meta, URL, Headings, Content, Image SEO, Links, Technical SEO, Keyword',
                        'Title checks: exists, length 50–60, keyword in title, duplicate detection',
                        'Meta description checks: exists, length 140–160, keyword, uniqueness',
                        'URL/Slug checks: length, keyword, special characters, hyphens',
                        'Heading checks: single H1, keyword in H1, H2 present, hierarchy, no skipped levels',
                        'Content checks: word count by page type (300/800/1200), paragraph length, sentence length',
                        'Passive voice detection with percentage score',
                        'Flesch Reading Ease score calculation',
                        'Content freshness (months since last update)',
                        'Semantic keyword detection (synonyms, variants, related terms)',
                        'Image SEO: missing alt text, short alt text, duplicate alt text, WebP format, lazy loading',
                        'Link checks: internal link count, orphan page detection via DB query, generic anchor text, over-optimised anchors',
                        'External link rel="noopener" attribute check',
                        'Technical: HTTPS check, canonical tag, noindex detection, robots meta',
                        'Version control system (this page)',
                        'Upgrade history log',
                        'Database migration engine (class-ras-upgrader.php)',
                        'uninstall.php for clean removal',
                    ],
                    'changed' => [
                        'SEO score cache cleared on upgrade — re-analysis required',
                        'Category icons updated in check grid (9 distinct categories)',
                        'Check grid now shows colour-coded left border per category',
                    ],
                    'fixed'   => [],
                ],
            ],
            [
                'version' => '2.1.0',
                'date'    => '2025-05-28',
                'type'    => 'minor',
                'summary' => 'WooCommerce Data Bridge + Custom Post Type support',
                'changes' => [
                    'added'   => [
                        'WooCommerce Data Bridge (class-ras-woo-bridge.php)',
                        'Auto-pull price, SKU, stock status, ratings, gallery images from WooCommerce',
                        'AggregateOffer for variable products (price range)',
                        'Individual Offer objects for grouped products',
                        'Product reviews as Review[] in schema',
                        'Product attributes as additionalProperty[]',
                        'GTIN and MPN support via _gtin / _mpn post meta',
                        'WooCommerce OG product meta tags (product:price:amount, product:price:currency)',
                        'CPT-specific default schema type table in Settings',
                        'schema_type_for_post_type() smart defaults (post → Article, page → WebPage, product → Product)',
                        '5 WooCommerce-specific SEO checks (price, SKU, gallery, reviews, short description)',
                        'CPT-aware breadcrumbs with archive links',
                        'WooCommerce bridge toggle in Settings',
                        'JobPosting, Course, SoftwareApplication, VideoObject schema builders',
                    ],
                    'changed' => [
                        'Schema dispatcher now reads CPT defaults from Settings',
                        'Settings page expanded with CPT defaults table and WooCommerce section',
                        'Breadcrumbs use product_cat for WooCommerce products',
                    ],
                    'fixed'   => [
                        'Schema dispatcher no longer defaults all CPTs to Article type',
                    ],
                ],
            ],
            [
                'version' => '2.0.0',
                'date'    => '2025-05-24',
                'type'    => 'major',
                'summary' => 'Renamed to Grapevine SEO — Full rewrite',
                'changes' => [
                    'added'   => [
                        'Plugin renamed Schema King → Grapevine SEO',
                        'SEO Analysis Engine with 22 checks across 5 categories',
                        'Site-wide SEO score gauge chart (Chart.js doughnut)',
                        'Score distribution donut chart',
                        'Analyze Entire Site button with live progress bar',
                        'Per-page SEO analyzer with dropdown selector',
                        'All-pages table with filter by grade and search',
                        'Two-tab meta box: Schema | SEO Analysis',
                        'Focus keyword, meta description, OG tags, no-index fields per post',
                        'Meta description character counter with colour bar',
                        'Mini check results in post editor SEO tab',
                        'GitHub Update Checker integration (Plugin Update Checker v5.3)',
                        'GitHub Actions release workflow (.github/workflows/release.yml)',
                        'GITHUB_SETUP.md setup guide',
                        'Light theme admin CSS',
                        'Google Rich Results compliant schema: Article, FAQPage, HowTo, Product, Event, Recipe, LocalBusiness',
                    ],
                    'changed' => [
                        'Class prefix changed from Schema_King_ to GVSEO_',
                        'Meta key prefix changed from _schema_king_ to _gvseo_',
                        'Option prefix changed from schema_king_ to gvseo_',
                        'Admin menu slug: grapevine-seo',
                    ],
                    'fixed'   => [
                        'Article headline truncated to 110 chars (Google requirement)',
                        'Event requires eventStatus + eventAttendanceMode',
                        'Product uses ratingCount (not reviewCount) for AggregateRating',
                        'Recipe image marked as required for rich results eligibility',
                        'Organization @id added for entity linking',
                    ],
                ],
            ],
        ];
    }

    /* ═══════════════════════════════════════════════════════════════
       RENDER
       ═══════════════════════════════════════════════════════════════ */
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }

        // Handle force-run migration action.
        if ( isset( $_POST['gvseo_force_migration'], $_POST['gvseo_migration_nonce'], $_POST['gvseo_migration_version'] ) &&
             wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gvseo_migration_nonce'] ) ), 'gvseo_force_migration' ) ) {
            $ver    = sanitize_text_field( wp_unslash( $_POST['gvseo_migration_version'] ) );
            $result = GVSEO_Upgrader::force_migration( $ver );
            if ( $result ) {
                echo '<div class="notice notice-success is-dismissible"><p><strong>Migration v' . esc_html( $ver ) . ' run:</strong> ' . esc_html( $result ) . '</p></div>';
            }
        }

        $installed   = GVSEO_Upgrader::get_db_version();
        $current     = GVSEO_VERSION;
        $is_current  = version_compare( $installed, $current, '>=' );
        $history     = GVSEO_Upgrader::get_history();
        $migrations  = GVSEO_Upgrader::get_migrations();
        $changelog   = self::changelog();
        ?>
        <div class="gvseo-wrap">
        <div class="gvseo-page-header">
            <div class="gvseo-page-title"><span class="gvseo-logo-star">★</span> Grapevine SEO <span class="gvseo-page-sub">/ Version Control</span></div>
            <?php GVSEO_Settings::nav( 'version' ); ?>
        </div>
        <div class="gvseo-page-body">

            <!-- ── Status Banner ────────────────────────────────────── -->
            <div class="gvseo-card gvseo-version-banner gvseo-version-banner-<?php echo $is_current ? 'ok' : 'warn'; ?>">
                <div class="gvseo-vb-inner">
                    <div class="gvseo-vb-icon"><?php echo $is_current ? '✅' : '⚠️'; ?></div>
                    <div>
                        <h2><?php echo $is_current ? 'Up to date' : 'Upgrade pending'; ?></h2>
                        <p>
                            Plugin: <strong>v<?php echo esc_html( $current ); ?></strong> &nbsp;|&nbsp;
                            DB schema: <strong>v<?php echo esc_html( $installed ); ?></strong>
                            <?php if ( ! $is_current ) : ?>
                                &nbsp;— <em>Migrations will run automatically on next page load.</em>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="gvseo-vb-meta">
                        <span class="gvseo-tag gvseo-tag-blue">v<?php echo esc_html( $current ); ?></span>
                        <a href="https://github.com/webkeith/grapevine-seo/releases" target="_blank" rel="noopener noreferrer" class="gvseo-btn gvseo-btn-ghost gvseo-btn-xs">GitHub Releases ↗</a>
                    </div>
                </div>
            </div>

            <div class="gvseo-vc-grid">

                <!-- ── Changelog ────────────────────────────────────── -->
                <div class="gvseo-card gvseo-vc-changelog">
                    <div class="gvseo-card-head"><h3>📋 Changelog</h3><p>Full release history for Grapevine SEO.</p></div>
                    <div class="gvseo-card-body gvseo-no-pad">
                        <?php foreach ( $changelog as $release ) :
                            $type_color = [ 'major' => '#dc2626', 'minor' => '#2563eb', 'patch' => '#059669' ][ $release['type'] ] ?? '#6b7280';
                            $type_bg    = [ 'major' => '#fef2f2', 'minor' => '#eff4ff', 'patch' => '#ecfdf5' ][ $release['type'] ] ?? '#f9fafb';
                            $is_inst    = version_compare( $installed, $release['version'], '>=' );
                        ?>
                            <div class="gvseo-cl-release <?php echo $release['version'] === $current ? 'gvseo-cl-current' : ''; ?>">
                                <div class="gvseo-cl-header">
                                    <div class="gvseo-cl-version-row">
                                        <span class="gvseo-cl-version">v<?php echo esc_html( $release['version'] ); ?></span>
                                        <span class="gvseo-cl-type" style="background:<?php echo $type_bg; ?>;color:<?php echo $type_color; ?>;">
                                            <?php echo esc_html( strtoupper( $release['type'] ) ); ?>
                                        </span>
                                        <?php if ( $release['version'] === $current ) : ?>
                                            <span class="gvseo-tag gvseo-tag-blue">Current</span>
                                        <?php elseif ( $is_inst ) : ?>
                                            <span class="gvseo-tag gvseo-tag-grey">Installed</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="gvseo-cl-meta">
                                        <span class="gvseo-cl-date">📅 <?php echo esc_html( $release['date'] ); ?></span>
                                        <span class="gvseo-cl-summary"><?php echo esc_html( $release['summary'] ); ?></span>
                                    </div>
                                </div>
                                <div class="gvseo-cl-body">
                                    <?php if ( ! empty( $release['changes']['added'] ) ) : ?>
                                        <div class="gvseo-cl-section">
                                            <h5><span class="gvseo-cl-dot gvseo-cl-dot-added"></span> Added</h5>
                                            <ul>
                                                <?php foreach ( $release['changes']['added'] as $item ) : ?>
                                                    <li><?php echo esc_html( $item ); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $release['changes']['changed'] ) ) : ?>
                                        <div class="gvseo-cl-section">
                                            <h5><span class="gvseo-cl-dot gvseo-cl-dot-changed"></span> Changed</h5>
                                            <ul>
                                                <?php foreach ( $release['changes']['changed'] as $item ) : ?>
                                                    <li><?php echo esc_html( $item ); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $release['changes']['fixed'] ) ) : ?>
                                        <div class="gvseo-cl-section">
                                            <h5><span class="gvseo-cl-dot gvseo-cl-dot-fixed"></span> Fixed</h5>
                                            <ul>
                                                <?php foreach ( $release['changes']['fixed'] as $item ) : ?>
                                                    <li><?php echo esc_html( $item ); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ── Right panel ──────────────────────────────────── -->
                <div class="gvseo-vc-side">

                    <!-- Migration Status -->
                    <div class="gvseo-card">
                        <div class="gvseo-card-head"><h3>🔄 Migration Status</h3><p>All registered database migrations and their run status.</p></div>
                        <div class="gvseo-card-body gvseo-no-pad">
                            <table class="gvseo-table">
                                <thead>
                                    <tr><th>Version</th><th>Status</th><th>Action</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ( $migrations as $ver => $method ) :
                                    $ran     = version_compare( $installed, $ver, '>=' );
                                    $status  = $ran ? 'pass' : 'pending';
                                    $icon    = $ran ? '✓' : '○';
                                    $color   = $ran ? '#059669' : '#d97706';
                                ?>
                                    <tr>
                                        <td><code>v<?php echo esc_html( $ver ); ?></code></td>
                                        <td>
                                            <span style="color:<?php echo $color; ?>; font-weight:700;">
                                                <?php echo $icon; ?> <?php echo $ran ? 'Applied' : 'Pending'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="post" style="display:inline;">
                                                <?php wp_nonce_field( 'gvseo_force_migration', 'gvseo_migration_nonce' ); ?>
                                                <input type="hidden" name="gvseo_migration_version" value="<?php echo esc_attr( $ver ); ?>">
                                                <button type="submit" class="gvseo-btn gvseo-btn-ghost gvseo-btn-xs"
                                                    onclick="return confirm('Force-run migration v<?php echo esc_attr( $ver ); ?>?')">
                                                    ↺ Re-run
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Upgrade History -->
                    <div class="gvseo-card">
                        <div class="gvseo-card-head"><h3>📜 Upgrade History</h3><p>Log of every upgrade that has run on this site.</p></div>
                        <div class="gvseo-card-body">
                            <?php if ( empty( $history ) ) : ?>
                                <p class="gvseo-hint" style="text-align:center;padding:20px 0;">No upgrades recorded yet.</p>
                            <?php else : ?>
                                <?php foreach ( array_reverse( $history ) as $entry ) : ?>
                                    <div class="gvseo-history-entry">
                                        <div class="gvseo-he-header">
                                            <span class="gvseo-he-version">
                                                v<?php echo esc_html( $entry['from'] ); ?> → v<?php echo esc_html( $entry['to'] ); ?>
                                            </span>
                                            <span class="gvseo-he-date"><?php echo esc_html( date( 'Y-m-d H:i', $entry['timestamp'] ) ); ?></span>
                                        </div>
                                        <div class="gvseo-he-meta">
                                            PHP <?php echo esc_html( $entry['php_version'] ); ?> &nbsp;·&nbsp;
                                            WP <?php echo esc_html( $entry['wp_version'] ); ?>
                                        </div>
                                        <?php foreach ( $entry['migrations'] as $m ) : ?>
                                            <div class="gvseo-he-step">
                                                <span class="gvseo-he-dot">✓</span>
                                                <code>v<?php echo esc_html( $m['version'] ); ?></code>
                                                <span><?php echo esc_html( $m['result'] ); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if ( ! empty( $entry['errors'] ) ) : ?>
                                            <?php foreach ( $entry['errors'] as $err ) : ?>
                                                <div class="gvseo-he-step gvseo-he-error">
                                                    <span class="gvseo-he-dot">✗</span>
                                                    <span><?php echo esc_html( $err ); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Release Guide -->
                    <div class="gvseo-card">
                        <div class="gvseo-card-head"><h3>🚀 How to Release a New Version</h3></div>
                        <div class="gvseo-card-body">
                            <ol class="gvseo-vc-steps">
                                <li>
                                    <strong>Add a migration</strong> to <code>class-ras-upgrader.php</code>:<br>
                                    <code>private static function migrate_X_Y_Z() { … }</code><br>
                                    Register it in <code>$migrations</code>.
                                </li>
                                <li>
                                    <strong>Bump the version</strong> in <code>grapevine-seo.php</code>:<br>
                                    <code>* Version: X.Y.Z</code><br>
                                    <code>define('GVSEO_VERSION', 'X.Y.Z');</code>
                                </li>
                                <li>
                                    <strong>Add a changelog entry</strong> in <code>class-ras-version-page.php</code> → <code>changelog()</code>.
                                </li>
                                <li>
                                    <strong>Tag and push:</strong><br>
                                    <code>git commit -am "Release X.Y.Z"</code><br>
                                    <code>git tag vX.Y.Z</code><br>
                                    <code>git push origin main --tags</code>
                                </li>
                                <li>GitHub Actions builds the ZIP → Release published → WP shows update.</li>
                            </ol>
                        </div>
                    </div>

                </div><!-- /.gvseo-vc-side -->
            </div><!-- /.gvseo-vc-grid -->
        </div><!-- /.gvseo-page-body -->
        </div><!-- /.gvseo-wrap -->
        <?php
    }
}
