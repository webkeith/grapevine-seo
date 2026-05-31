<?php
/**
 * SEO Analysis Page — Grapevine SEO
 * Full dashboard: site score gauge, distribution chart, run-all, per-page dropdown, all-pages table.
 * @package GrapevineSEO
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class GVSEO_SEO_Page {

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }

        $summary   = GVSEO_SEO_Analyzer::summary();
        $all_posts = GVSEO_SEO_Analyzer::all_posts();
        $total     = count( $all_posts );
        $analyzed  = count( array_filter( $all_posts, fn($p) => $p['score'] !== null ) );
        $last_run  = $summary['ts'] ? human_time_diff( $summary['ts'] ) . ' ago' : 'Never';
        $avg       = $summary['avg'];
        ?>
        <div class="gvseo-wrap">
        <div class="gvseo-page-header">
            <div class="gvseo-page-title"><span class="gvseo-logo-star">★</span> Grapevine SEO <span class="gvseo-page-sub">/ SEO Analysis</span></div>
            <?php GVSEO_Settings::nav( 'seo' ); ?>
        </div>
        <div class="gvseo-page-body">

            <!-- ══ ROW 1: Score + Distribution + Run ═══════════════════ -->
            <div class="gvseo-seo-top">

                <!-- Site Score Gauge -->
                <div class="gvseo-card gvseo-gauge-card">
                    <div class="gvseo-card-head">
                        <h3>📈 Site SEO Score</h3>
                        <p>Average across <?php echo $analyzed; ?> analyzed pages</p>
                    </div>
                    <div class="gvseo-card-body gvseo-gauge-body">
                        <div class="gvseo-gauge-wrap">
                            <canvas id="gvseo-gauge" width="200" height="200"></canvas>
                            <div class="gvseo-gauge-center">
                                <span class="gvseo-gauge-num"><?php echo $avg; ?></span>
                                <span class="gvseo-gauge-lbl"><?php echo self::label_text( GVSEO_SEO_Analyzer::label( $avg ) ); ?></span>
                            </div>
                        </div>
                        <div class="gvseo-gauge-legend">
                            <?php foreach ( [ 'excellent' => [ 'Excellent', '#10b981' ], 'good' => [ 'Good', '#3b82f6' ], 'needs_work' => [ 'Needs Work', '#f59e0b' ], 'poor' => [ 'Poor', '#ef4444' ] ] as $k => [ $lbl, $col ] ) : ?>
                                <div class="gvseo-legend-row">
                                    <span class="gvseo-legend-dot" style="background:<?php echo $col; ?>"></span>
                                    <span class="gvseo-legend-lbl"><?php echo $lbl; ?></span>
                                    <span class="gvseo-legend-val"><?php echo $summary[ $k ]; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Distribution Donut -->
                <div class="gvseo-card gvseo-dist-card">
                    <div class="gvseo-card-head"><h3>📊 Score Distribution</h3><p>Pages by SEO grade</p></div>
                    <div class="gvseo-card-body gvseo-dist-body">
                        <canvas id="gvseo-dist" width="240" height="240"></canvas>
                        <div class="gvseo-dist-legend">
                            <?php
                            $cats = [
                                'excellent'  => [ 'Excellent (80–100)',  '#10b981', $summary['excellent'] ],
                                'good'       => [ 'Good (60–79)',        '#3b82f6', $summary['good'] ],
                                'needs_work' => [ 'Needs Work (40–59)', '#f59e0b', $summary['needs_work'] ],
                                'poor'       => [ 'Poor (0–39)',         '#ef4444', $summary['poor'] ],
                            ];
                            foreach ( $cats as [ $lbl, $col, $cnt ] ) : ?>
                                <div class="gvseo-dl-row">
                                    <span class="gvseo-dl-dot" style="background:<?php echo $col; ?>"></span>
                                    <span><?php echo $lbl; ?></span>
                                    <strong><?php echo $cnt; ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Run Analysis -->
                <div class="gvseo-card gvseo-run-card">
                    <div class="gvseo-card-head"><h3>⚡ Run Analysis</h3><p>Scan all pages to refresh scores</p></div>
                    <div class="gvseo-card-body">
                        <div class="gvseo-run-stats">
                            <div class="gvseo-rs"><span class="gvseo-rs-num" id="gvseo-stat-total"><?php echo $total; ?></span><span>Total</span></div>
                            <div class="gvseo-rs"><span class="gvseo-rs-num gvseo-col-blue" id="gvseo-stat-analyzed"><?php echo $analyzed; ?></span><span>Analyzed</span></div>
                            <div class="gvseo-rs"><span class="gvseo-rs-num gvseo-col-yellow" id="gvseo-stat-pending"><?php echo $total - $analyzed; ?></span><span>Pending</span></div>
                        </div>
                        <div id="gvseo-prog-wrap" style="display:none; margin:12px 0;">
                            <div class="gvseo-prog-bar"><div class="gvseo-prog-fill" id="gvseo-prog-fill"></div></div>
                            <p class="gvseo-prog-txt" id="gvseo-prog-txt">Analyzing…</p>
                        </div>
                        <button id="gvseo-run-btn" type="button" class="gvseo-btn gvseo-btn-primary gvseo-btn-full gvseo-btn-lg">
                            ▶ Analyze Entire Site
                        </button>
                        <p class="gvseo-hint" style="text-align:center; margin-top:10px;">
                            Last run: <strong id="gvseo-last-run"><?php echo esc_html( $last_run ); ?></strong>
                        </p>
                    </div>
                </div>

            </div><!-- /.gvseo-seo-top -->

            <!-- ══ ROW 2: Per-Page Analyzer ═════════════════════════════ -->
            <div class="gvseo-card">
                <div class="gvseo-card-head gvseo-flex-head">
                    <div><h3>🔍 Page SEO Analyzer</h3><p>Pick any page to see its full SEO report with checks, scores, and fix suggestions.</p></div>
                </div>
                <div class="gvseo-card-body">
                    <!-- Selector row -->
                    <div class="gvseo-selector-row">
                        <select id="gvseo-page-select" class="gvseo-page-select">
                            <option value="">— Select a page —</option>
                            <?php foreach ( $all_posts as $p ) : ?>
                                <option value="<?php echo (int) $p['id']; ?>"
                                    data-edit="<?php echo esc_url( $p['edit'] ); ?>"
                                    data-view="<?php echo esc_url( $p['view'] ); ?>"
                                    data-score="<?php echo $p['score'] !== null ? (int) $p['score'] : ''; ?>"
                                    data-label="<?php echo esc_attr( $p['label'] ); ?>">
                                    <?php echo esc_html( $p['title'] ); ?>
                                    <?php echo $p['score'] !== null ? '(' . $p['score'] . '/100)' : '(not analyzed)'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button id="gvseo-analyze-btn" type="button" class="gvseo-btn gvseo-btn-primary">🔍 Analyze</button>
                        <a id="gvseo-edit-link" href="#" target="_blank" class="gvseo-btn gvseo-btn-secondary" style="display:none;">✏️ Edit</a>
                        <a id="gvseo-view-link" href="#" target="_blank" class="gvseo-btn gvseo-btn-ghost" style="display:none;">🔗 View</a>
                    </div>

                    <!-- Loading -->
                    <div id="gvseo-analyzing" style="display:none;" class="gvseo-loading-box">
                        <div class="gvseo-spinner"></div><p>Analyzing page…</p>
                    </div>

                    <!-- Results panel -->
                    <div id="gvseo-results" style="display:none; margin-top:20px;">

                        <!-- Score header -->
                        <div class="gvseo-result-header">
                            <h4 id="gvseo-result-title"></h4>
                            <div class="gvseo-result-meta">
                                <div class="gvseo-score-circle" id="gvseo-score-circle"><span id="gvseo-score-num">0</span></div>
                                <div class="gvseo-score-pswf">
                                    <div class="gvseo-pswf-row gvseo-pswf-pass"><span id="gvseo-cnt-pass">0</span> ✓ Passed</div>
                                    <div class="gvseo-pswf-row gvseo-pswf-warn"><span id="gvseo-cnt-warn">0</span> ⚠ Warnings</div>
                                    <div class="gvseo-pswf-row gvseo-pswf-fail"><span id="gvseo-cnt-fail">0</span> ✗ Failed</div>
                                </div>
                                <div class="gvseo-score-bar-wrap">
                                    <div class="gvseo-score-bar"><div class="gvseo-score-fill" id="gvseo-score-fill"></div></div>
                                    <span id="gvseo-score-badge" class="gvseo-score-badge"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Category check cards -->
                        <div id="gvseo-check-grid" class="gvseo-check-grid"></div>

                    </div><!-- /#gvseo-results -->

                    <div id="gvseo-empty-hint" class="gvseo-empty-hint">
                        <span>🔎</span><p>Select a page above and click Analyze to see its full SEO report.</p>
                    </div>
                </div>
            </div>

            <!-- ══ ROW 3: All Pages Table ════════════════════════════════ -->
            <div class="gvseo-card">
                <div class="gvseo-card-head gvseo-flex-head">
                    <div><h3>📋 All Pages — SEO Scores</h3><p>Click a row to load its analysis. Run site analysis to populate all scores.</p></div>
                    <div class="gvseo-table-filters" id="gvseo-filters">
                        <?php foreach ( [ 'all' => 'All', 'poor' => 'Poor', 'needs_work' => 'Needs Work', 'good' => 'Good', 'excellent' => 'Excellent', 'unanalyzed' => 'Not Analyzed' ] as $k => $lbl ) : ?>
                            <button class="gvseo-filter-btn <?php echo $k === 'all' ? 'active' : ''; ?>" data-filter="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $lbl ); ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="gvseo-card-body gvseo-no-pad">
                    <div class="gvseo-table-search-bar">
                        <input type="text" id="gvseo-table-search" placeholder="Search pages…">
                    </div>
                    <table class="gvseo-table" id="gvseo-pages-table">
                        <thead>
                            <tr>
                                <th>Page</th>
                                <th>Type</th>
                                <th>Score</th>
                                <th>Status</th>
                                <th>Last Analyzed</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $all_posts as $p ) : ?>
                            <tr class="gvseo-tr" data-label="<?php echo esc_attr( $p['label'] ); ?>" data-id="<?php echo (int) $p['id']; ?>">
                                <td>
                                    <a href="#" class="gvseo-tbl-title gvseo-open-row"
                                        data-id="<?php echo (int) $p['id']; ?>"
                                        data-edit="<?php echo esc_url( $p['edit'] ); ?>"
                                        data-view="<?php echo esc_url( $p['view'] ); ?>">
                                        <?php echo esc_html( $p['title'] ); ?>
                                    </a>
                                </td>
                                <td><span class="gvseo-type-badge"><?php echo esc_html( $p['type'] ); ?></span></td>
                                <td>
                                    <?php if ( $p['score'] !== null ) : ?>
                                        <div class="gvseo-mini-bar-wrap">
                                            <div class="gvseo-mini-bar">
                                                <div class="gvseo-mini-fill gvseo-fill-<?php echo esc_attr( $p['label'] ); ?>" style="width:<?php echo (int) $p['score']; ?>%"></div>
                                            </div>
                                            <strong><?php echo (int) $p['score']; ?></strong>
                                        </div>
                                    <?php else : ?>
                                        <span class="gvseo-muted-dash">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="gvseo-status-badge gvseo-sb-<?php echo esc_attr( $p['label'] ); ?>"><?php echo esc_html( self::badge_text( $p['label'] ) ); ?></span></td>
                                <td class="gvseo-ts-cell"><?php echo $p['analyzed'] ? esc_html( human_time_diff( $p['analyzed'] ) . ' ago' ) : '—'; ?></td>
                                <td class="gvseo-actions-cell">
                                    <button class="gvseo-action-icon gvseo-open-row" title="Analyze"
                                        data-id="<?php echo (int) $p['id']; ?>"
                                        data-edit="<?php echo esc_url( $p['edit'] ); ?>"
                                        data-view="<?php echo esc_url( $p['view'] ); ?>">🔍</button>
                                    <a href="<?php echo esc_url( $p['edit'] ); ?>" class="gvseo-action-icon" title="Edit">✏️</a>
                                    <a href="<?php echo esc_url( $p['view'] ); ?>" class="gvseo-action-icon" title="View" target="_blank">🔗</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /.gvseo-page-body -->
        </div><!-- /.gvseo-wrap -->

        <!-- JS data -->
        <script>
        window.GVSEO_SEO = {
            summary: <?php echo wp_json_encode( $summary ); ?>,
            posts: <?php echo wp_json_encode( array_map( fn($p) => [
                'id'    => $p['id'], 'title' => $p['title'],
                'score' => $p['score'], 'label' => $p['label'],
                'edit'  => $p['edit'], 'view' => $p['view'], 'analyzed' => $p['analyzed'],
            ], $all_posts ) ); ?>,
            checks: <?php echo wp_json_encode( GVSEO_SEO_Analyzer::checks() ); ?>,
            cats: {
                title:'Title Tag', meta:'Meta Description', url:'URL / Slug',
                headings:'Headings', content:'Content', images:'Image SEO',
                links:'Links', technical:'Technical SEO', schema:'Schema',
                social:'Social / OG', keyword:'Focus Keyword', product:'WooCommerce Product'
            },
            catIcons: {
                title:'🏷️', meta:'📝', url:'🔗', headings:'📑', content:'📄',
                images:'🖼️', links:'🔀', technical:'⚙️', schema:'⬡',
                social:'📣', keyword:'🎯', product:'🛒'
            }
        };
        </script>
        <?php
    }

    private static function label_text( $l ) {
        return [ 'excellent' => 'Excellent', 'good' => 'Good', 'needs_work' => 'Needs Work', 'poor' => 'Poor' ][ $l ] ?? 'N/A';
    }

    private static function badge_text( $l ) {
        return [ 'excellent' => '★ Excellent', 'good' => '▲ Good', 'needs_work' => '⚠ Needs Work',
                 'poor' => '✗ Poor', 'unanalyzed' => '○ Not Analyzed' ][ $l ] ?? $l;
    }
}
