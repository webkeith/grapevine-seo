<?php
/**
 * Schema Dashboard — Grapevine SEO
 * @package GrapevineSEO
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class GVSEO_Dashboard {

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }

        /* ── Count schema coverage ─────────────────────────────────────
         *
         * Logic:
         *  - A page HAS schema if _gvseo_schema_mode is NOT 'disabled'
         *    (includes '' empty/never set, 'global', 'override').
         *  - A page is WITHOUT schema only if it was explicitly set to
         *    'disabled' by the user.
         *  - Excluded post types (builder templates etc.) are skipped.
         * ─────────────────────────────────────────────────────────────── */
        $excluded    = GVSEO_Settings::get_excluded_types();
        $types       = get_post_types( [ 'public' => true ], 'objects' );
        $total       = 0;
        $with_schema = 0;
        $by_type     = [];

        foreach ( $types as $pt ) {
            // Skip excluded post types.
            if ( in_array( $pt->name, $excluded, true ) ) { continue; }

            $posts = get_posts( [
                'post_type'      => $pt->name,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ] );

            $pt_total  = count( $posts );
            $pt_schema = 0;

            foreach ( $posts as $post_id ) {
                // Skip individually excluded post IDs.
                if ( GVSEO_Settings::is_post_excluded( $post_id ) ) {
                    $pt_total--;
                    continue;
                }
                $total++;
                $mode = get_post_meta( $post_id, '_gvseo_schema_mode', true );
                // 'disabled' = user explicitly turned off schema.
                // '', 'global', 'override' = schema is active.
                if ( 'disabled' !== $mode ) {
                    $with_schema++;
                    $pt_schema++;
                }
            }

            if ( $pt_total > 0 ) {
                $by_type[] = [
                    'label'  => $pt->label,
                    'total'  => $pt_total,
                    'schema' => $pt_schema,
                    'pct'    => (int) round( $pt_schema / $pt_total * 100 ),
                ];
            }
        }

        $without = $total - $with_schema;
        $pct     = $total > 0 ? (int) round( $with_schema / $total * 100 ) : 0;

        // SEO summary from last analysis run.
        $seo = GVSEO_SEO_Analyzer::summary();
        ?>
        <div class="gvseo-wrap">
        <div class="gvseo-page-header">
            <div class="gvseo-page-title"><span class="gvseo-logo-star">★</span> Grapevine SEO <span class="gvseo-page-sub">/ Schema Dashboard</span></div>
            <?php GVSEO_Settings::nav( 'dashboard' ); ?>
        </div>
        <div class="gvseo-page-body">

            <!-- ── Schema KPIs ───────────────────────────────────────── -->
            <div class="gvseo-kpi-grid">
                <div class="gvseo-kpi gvseo-kpi-blue">
                    <div class="gvseo-kpi-icon">📄</div>
                    <div><div class="gvseo-kpi-val"><?php echo (int) $total; ?></div><div class="gvseo-kpi-lbl">Total Pages</div></div>
                </div>
                <div class="gvseo-kpi gvseo-kpi-green">
                    <div class="gvseo-kpi-icon">✅</div>
                    <div><div class="gvseo-kpi-val"><?php echo (int) $with_schema; ?></div><div class="gvseo-kpi-lbl">With Schema</div></div>
                </div>
                <div class="gvseo-kpi gvseo-kpi-yellow">
                    <div class="gvseo-kpi-icon">⚠️</div>
                    <div><div class="gvseo-kpi-val"><?php echo (int) $without; ?></div><div class="gvseo-kpi-lbl">Without Schema</div></div>
                </div>
                <div class="gvseo-kpi gvseo-kpi-purple">
                    <div class="gvseo-kpi-icon">📊</div>
                    <div><div class="gvseo-kpi-val"><?php echo (int) $pct; ?>%</div><div class="gvseo-kpi-lbl">Schema Coverage</div></div>
                </div>
            </div>

            <!-- ── Schema by post type ───────────────────────────────── -->
            <?php if ( ! empty( $by_type ) ) : ?>
            <div class="gvseo-card">
                <div class="gvseo-card-head"><h3>📦 Schema Coverage by Post Type</h3></div>
                <div class="gvseo-card-body gvseo-no-pad">
                    <table class="gvseo-table">
                        <thead>
                            <tr>
                                <th>Post Type</th>
                                <th>Total</th>
                                <th>With Schema</th>
                                <th>Without Schema</th>
                                <th>Coverage</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $by_type as $row ) : ?>
                            <tr class="gvseo-tr">
                                <td><?php echo esc_html( $row['label'] ); ?></td>
                                <td><?php echo (int) $row['total']; ?></td>
                                <td style="color:var(--c-green);font-weight:600;"><?php echo (int) $row['schema']; ?></td>
                                <td style="color:var(--c-yellow);font-weight:600;"><?php echo (int) ( $row['total'] - $row['schema'] ); ?></td>
                                <td>
                                    <div class="gvseo-mini-bar-wrap">
                                        <div class="gvseo-mini-bar">
                                            <div class="gvseo-mini-fill" style="width:<?php echo (int) $row['pct']; ?>%;background:<?php echo $row['pct'] >= 80 ? 'var(--c-green)' : ( $row['pct'] >= 50 ? 'var(--c-blue)' : 'var(--c-yellow)' ); ?>;"></div>
                                        </div>
                                        <strong><?php echo (int) $row['pct']; ?>%</strong>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── SEO Summary ───────────────────────────────────────── -->
            <?php if ( $seo['total'] > 0 ) : ?>
            <div class="gvseo-card">
                <div class="gvseo-card-head"><h3>🔍 SEO Health Summary</h3><p>From last site-wide analysis — go to SEO Analysis to refresh.</p></div>
                <div class="gvseo-card-body">
                    <div class="gvseo-kpi-grid">
                        <?php
                        $seo_kpis = [
                            [ 'excellent', '🟢', 'Excellent', $seo['excellent'] ],
                            [ 'good',      '🔵', 'Good',      $seo['good'] ],
                            [ 'needs_work','🟡', 'Needs Work',$seo['needs_work'] ],
                            [ 'poor',      '🔴', 'Poor',      $seo['poor'] ],
                        ];
                        $colors = [ 'excellent'=>'green','good'=>'blue','needs_work'=>'yellow','poor'=>'purple' ];
                        foreach ( $seo_kpis as [ $key, $icon, $lbl, $cnt ] ) : ?>
                            <div class="gvseo-kpi gvseo-kpi-<?php echo esc_attr( $colors[ $key ] ); ?>">
                                <div class="gvseo-kpi-icon"><?php echo esc_html( $icon ); ?></div>
                                <div><div class="gvseo-kpi-val"><?php echo (int) $cnt; ?></div><div class="gvseo-kpi-lbl"><?php echo esc_html( $lbl ); ?></div></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="gvseo-hint" style="margin-top:12px;">
                        Average SEO score: <strong><?php echo (int) $seo['avg']; ?>/100</strong> across <?php echo (int) $seo['total']; ?> pages.
                        <?php if ( $seo['ts'] ) : ?>
                            Last analysed <?php echo esc_html( human_time_diff( (int) $seo['ts'] ) ); ?> ago.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── Quick Actions ─────────────────────────────────────── -->
            <div class="gvseo-card">
                <div class="gvseo-card-head"><h3>🚀 Quick Actions</h3></div>
                <div class="gvseo-card-body gvseo-quick-links">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=grapevine-seo-seo' ) ); ?>" class="gvseo-btn gvseo-btn-primary">🔍 SEO Analysis</a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=grapevine-seo-settings' ) ); ?>" class="gvseo-btn gvseo-btn-secondary">⚙️ Global Settings</a>
                    <a href="<?php echo esc_url( home_url( '/sitemap.xml' ) ); ?>" target="_blank" class="gvseo-btn gvseo-btn-ghost">🗺️ View Sitemap ↗</a>
                    <a href="https://search.google.com/test/rich-results" target="_blank" class="gvseo-btn gvseo-btn-ghost">🔗 Rich Results Tester ↗</a>
                    <a href="https://validator.schema.org/" target="_blank" class="gvseo-btn gvseo-btn-ghost">✅ Schema Validator ↗</a>
                </div>
            </div>

        </div></div>
        <?php
    }
}
