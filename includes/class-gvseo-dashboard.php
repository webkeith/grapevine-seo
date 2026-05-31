<?php
/**
 * Schema Dashboard — Grapevine SEO
 * @package GrapevineSEO
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class GVSEO_Dashboard {

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $types = get_post_types( [ 'public' => true ], 'objects' );
        $total = 0; $with_schema = 0;
        foreach ( $types as $pt ) {
            $posts = get_posts( [ 'post_type' => $pt->name, 'post_status' => 'publish', 'posts_per_page' => -1 ] );
            foreach ( $posts as $p ) {
                $total++;
                $mode = get_post_meta( $p->ID, '_gvseo_schema_mode', true );
                if ( 'disabled' !== $mode ) { $with_schema++; }
            }
        }
        $pct = $total > 0 ? round( $with_schema / $total * 100 ) : 0;
        ?>
        <div class="gvseo-wrap">
        <div class="gvseo-page-header">
            <div class="gvseo-page-title"><span class="gvseo-logo-star">★</span> Grapevine SEO <span class="gvseo-page-sub">/ Schema Dashboard</span></div>
            <?php GVSEO_Settings::nav( 'dashboard' ); ?>
        </div>
        <div class="gvseo-page-body">
            <div class="gvseo-kpi-grid">
                <div class="gvseo-kpi gvseo-kpi-blue"><div class="gvseo-kpi-icon">📄</div><div><div class="gvseo-kpi-val"><?php echo $total; ?></div><div class="gvseo-kpi-lbl">Total Pages</div></div></div>
                <div class="gvseo-kpi gvseo-kpi-green"><div class="gvseo-kpi-icon">✅</div><div><div class="gvseo-kpi-val"><?php echo $with_schema; ?></div><div class="gvseo-kpi-lbl">With Schema</div></div></div>
                <div class="gvseo-kpi gvseo-kpi-yellow"><div class="gvseo-kpi-icon">⚠️</div><div><div class="gvseo-kpi-val"><?php echo $total - $with_schema; ?></div><div class="gvseo-kpi-lbl">Without Schema</div></div></div>
                <div class="gvseo-kpi gvseo-kpi-purple"><div class="gvseo-kpi-icon">📊</div><div><div class="gvseo-kpi-val"><?php echo $pct; ?>%</div><div class="gvseo-kpi-lbl">Schema Coverage</div></div></div>
            </div>
            <div class="gvseo-card">
                <div class="gvseo-card-head"><h3>🚀 Quick Actions</h3></div>
                <div class="gvseo-card-body gvseo-quick-links">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=grapevine-seo-seo' ) ); ?>" class="gvseo-btn gvseo-btn-primary">🔍 SEO Analysis Dashboard</a>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=grapevine-seo-settings' ) ); ?>" class="gvseo-btn gvseo-btn-secondary">⚙️ Global Settings</a>
                    <a href="https://search.google.com/test/rich-results" target="_blank" class="gvseo-btn gvseo-btn-ghost">🔗 Rich Results Tester ↗</a>
                    <a href="https://schema.org/" target="_blank" class="gvseo-btn gvseo-btn-ghost">📖 Schema.org Docs ↗</a>
                </div>
            </div>
        </div></div>
        <?php
    }
}
