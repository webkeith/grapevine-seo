<?php
/**
 * Settings — Grapevine SEO
 * Global org settings + address + phone + per-CPT schema + exclusion list.
 * @package GrapevineSEO
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class GVSEO_Settings {

    /**
     * Known template post types that should be excluded by default.
     * Elementor, Beaver Builder, Divi, Bricks, Oxygen, etc.
     */
    const DEFAULT_EXCLUDED_TYPES = [
        'elementor_library',   // Elementor templates
        'e-landing-page',      // Elementor landing pages
        'fl-builder-template', // Beaver Builder
        'et_pb_layout',        // Divi layouts
        'bricks_template',     // Bricks Builder
        'oxygen_vsb_template', // Oxygen Builder
        'wp_template',         // WordPress FSE templates
        'wp_template_part',    // WordPress FSE template parts
        'wp_navigation',       // WP navigation blocks
        'wp_global_styles',    // WP global styles
        'wp_block',            // WP reusable blocks
        'nav_menu_item',
        'custom_css',
        'customize_changeset',
        'oembed_cache',
        'user_request',
        'revision',
        'attachment',
    ];

    public static function get() {
        return wp_parse_args( (array) get_option( 'gvseo_global_settings', [] ), [
            // Identity
            'org_name'          => get_bloginfo( 'name' ),
            'org_url'           => get_bloginfo( 'url' ),
            'org_logo'          => '',
            'org_email'         => '',
            'org_phone'         => '',
            'org_founder'       => '',           // Founder name (Person)

            // Primary address
            'org_street'        => '',           // ← NEW
            'org_city'          => '',           // ← NEW
            'org_state'         => '',           // ← NEW
            'org_postcode'      => '',           // ← NEW
            'org_country'       => '',           // ← NEW (ISO 2-letter)

            // Additional address (optional branch/office)
            'org_addr2_enabled' => '0',          // ← NEW
            'org_addr2_name'    => '',           // ← NEW: branch/office name
            'org_addr2_street'  => '',           // ← NEW
            'org_addr2_city'    => '',           // ← NEW
            'org_addr2_state'   => '',           // ← NEW
            'org_addr2_postcode'=> '',           // ← NEW
            'org_addr2_country' => '',           // ← NEW
            'org_addr2_phone'   => '',           // ← NEW: branch phone

            // Social
            'social_fb'         => '', 'social_tw' => '',
            'social_ig'         => '', 'social_li' => '', 'social_yt' => '',
            'social_tt'         => '',  // TikTok

            // Features
            'breadcrumbs'       => '1',
            'sitelinks'         => '1',
            'organization'      => '1',
            'woo_bridge'        => '1',

            // Exclusions
            'excluded_types'    => [],           // ← NEW: user-selected types to exclude
            'excluded_post_ids' => '',           // ← NEW: comma-separated specific post IDs

            // CPT schema defaults
            'cpt_defaults'      => [],

            // ── LocalBusiness schema — multiple locations/branches ────
            // Each entry in lb_locations is one physical location/branch.
            'lb_locations'      => [],
        ] );
    }

    /**
     * Get all post type slugs that should be skipped for schema/SEO.
     * Merges hard-coded system types with user-configured exclusions.
     */
    public static function get_excluded_types() {
        $s       = self::get();
        $user    = is_array( $s['excluded_types'] ) ? $s['excluded_types'] : [];
        return array_unique( array_merge( self::DEFAULT_EXCLUDED_TYPES, $user ) );
    }

    /**
     * Check if a specific post ID is manually excluded.
     */
    public static function is_post_excluded( $post_id ) {
        $s   = self::get();
        $ids = array_filter( array_map( 'trim', explode( ',', $s['excluded_post_ids'] ) ) );
        return in_array( (string) $post_id, $ids, true );
    }

    /**
     * Get the default schema type for a given post type.
     */
    public static function schema_type_for_post_type( $post_type ) {
        $s = self::get();
        if ( ! empty( $s['cpt_defaults'][ $post_type ] ) ) {
            return $s['cpt_defaults'][ $post_type ];
        }
        $defaults = [
            'post'    => 'Article',
            'page'    => 'WebPage',
            'product' => 'Product',
        ];
        return $defaults[ $post_type ] ?? 'WebPage';
    }

    /* ─── SAVE & RENDER ──────────────────────────────────────────── */
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }

        if ( isset( $_POST['gvseo_settings_nonce'] ) &&
             wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gvseo_settings_nonce'] ) ), 'gvseo_save_settings' ) ) {
            $s = [];

            // Text / URL fields
            $text_keys = [
                'org_name','org_url','org_logo','org_email','org_phone','org_founder',
                'org_street','org_city','org_state','org_postcode','org_country',
                'org_addr2_name','org_addr2_street','org_addr2_city',
                'org_addr2_state','org_addr2_postcode','org_addr2_country','org_addr2_phone',
                'social_fb','social_tw','social_ig','social_li','social_yt','social_tt',
                'excluded_post_ids',

            ];
            foreach ( $text_keys as $k ) {
                $s[ $k ] = sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) );
            }

            // Toggles
            $toggles = [ 'breadcrumbs','sitelinks','organization','woo_bridge','org_addr2_enabled' ];
            foreach ( $toggles as $t ) {
                $s[ $t ] = isset( $_POST[ $t ] ) ? '1' : '0';
            }

            // Excluded post types (multi-checkbox)
            $excluded = [];
            if ( isset( $_POST['excluded_types'] ) && is_array( $_POST['excluded_types'] ) ) {
                foreach ( array_map( 'sanitize_key', wp_unslash( $_POST['excluded_types'] ) ) as $pt ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
                    $excluded[] = sanitize_key( $pt ); // already sanitized above
                }
            }
            $s['excluded_types'] = $excluded;

            // Opening hours — array of day groups
            $lb_hours = [];
            if ( isset( $_POST['lb_hour_days'] ) && is_array( $_POST['lb_hour_days'] ) ) {
                foreach ( wp_unslash( $_POST['lb_hour_days'] ) as $idx => $days ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
                    if ( empty( $days ) ) { continue; }
                    $opens  = sanitize_text_field( $_POST['lb_hour_opens'][ $idx ] ?? '' );
                    $closes = sanitize_text_field( $_POST['lb_hour_closes'][ $idx ] ?? '' );
                    $lb_hours[] = [
                        'days'   => array_map( 'sanitize_text_field', (array) $days ),
                        'opens'  => $opens,
                        'closes' => $closes,
                    ];
                }
            }
            $s['lb_hours'] = $lb_hours;

            // CPT schema defaults
            $cpt_defaults = [];
            if ( isset( $_POST['cpt_defaults'] ) && is_array( $_POST['cpt_defaults'] ) ) {
                foreach ( wp_unslash( $_POST['cpt_defaults'] ) as $pt => $schema ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
                    $pt_clean     = sanitize_key( $pt );
                    $schema_clean = sanitize_text_field( wp_unslash( $schema ) );
                    if ( $pt_clean && $schema_clean ) {
                        $cpt_defaults[ $pt_clean ] = $schema_clean;
                    }
                }
            }
            $s['cpt_defaults'] = $cpt_defaults;

            // LocalBusiness locations save
            $lb_locs = [];
            if ( isset( $_POST['lb_loc'] ) && is_array( $_POST['lb_loc'] ) ) {
                foreach ( wp_unslash( $_POST['lb_loc'] ) as $li => $loc ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
                    $text_fields = [ 'type','name','description','phone','email',
                                     'street','city','state','postcode','country',
                                     'lat','lng','maps_url','price_range',
                                     'payment','currencies','area_served' ];
                    $entry = [];
                    foreach ( $text_fields as $tf ) {
                        $entry[ $tf ] = sanitize_text_field( $loc[ $tf ] ?? '' );
                    }
                    $entry['enabled']     = isset( $loc['enabled'] )     ? '1' : '0';
                    $entry['same_as_org'] = isset( $loc['same_as_org'] ) ? '1' : '0';
                    // Hours for this location
                    $entry['hours'] = [];
                    if ( isset( $loc['hour_days'] ) && is_array( $loc['hour_days'] ) ) {
                        foreach ( $loc['hour_days'] as $hi => $days ) {
                            if ( empty( $days ) ) { continue; }
                            $entry['hours'][] = [
                                'days'   => array_map( 'sanitize_text_field', (array) $days ),
                                'opens'  => sanitize_text_field( $loc['hour_opens'][ $hi ] ?? '' ),
                                'closes' => sanitize_text_field( $loc['hour_closes'][ $hi ] ?? '' ),
                            ];
                        }
                    }
                    $lb_locs[] = $entry;
                }
            }
            $s['lb_locations'] = $lb_locs;

            update_option( 'gvseo_global_settings', $s );
            echo '<div class="notice notice-success is-dismissible"><p><strong>✓ Settings saved.</strong></p></div>';
        }

        $s           = self::get();
        $all_types   = self::get_all_post_types_for_exclusion();
        $schema_list = self::schema_type_list();
        $cpt_types   = self::get_public_post_types();
        ?>
        <div class="gvseo-wrap">
        <div class="gvseo-page-header">
            <div class="gvseo-page-title"><span class="gvseo-logo-star">★</span> Grapevine SEO <span class="gvseo-page-sub">/ Global Settings</span></div>
            <?php self::nav( 'settings' ); ?>
        </div>
        <div class="gvseo-page-body">
        <form method="post">
            <?php wp_nonce_field( 'gvseo_save_settings', 'gvseo_settings_nonce' ); ?>

            <!-- ── Organization Identity ────────────────────────────── -->
            <div class="gvseo-card">
                <div class="gvseo-card-head"><h3>🏢 Organization</h3><p>Your brand identity — used in Organization schema, publisher fields, and knowledge graph.</p></div>
                <div class="gvseo-card-body">
                    <div class="gvseo-field-row">
                        <div class="gvseo-field"><label>Organization Name</label><input type="text" name="org_name" value="<?php echo esc_attr( $s['org_name'] ); ?>"></div>
                        <div class="gvseo-field"><label>Website URL</label><input type="url" name="org_url" value="<?php echo esc_attr( $s['org_url'] ); ?>"></div>
                    </div>
                    <div class="gvseo-field-row">
                        <div class="gvseo-field"><label>Logo URL <span class="gvseo-hint-inline">(fits 60×600 px per Google)</span></label><input type="url" name="org_logo" value="<?php echo esc_attr( $s['org_logo'] ); ?>"></div>
                        <div class="gvseo-field"><label>Contact Email</label><input type="email" name="org_email" value="<?php echo esc_attr( $s['org_email'] ); ?>"></div>
                    </div>
                    <div class="gvseo-field-row">
                        <div class="gvseo-field">
                            <label>Founder Name <span class="gvseo-hint-inline">(Person — optional)</span></label>
                            <input type="text" name="org_founder" value="<?php echo esc_attr( $s['org_founder'] ); ?>" placeholder="e.g. Chelsea Jarred">
                            <span class="gvseo-field-hint">Outputs as <code>founder: { @type: Person }</code> in Organization schema.</span>
                        </div>
                        <div class="gvseo-field">
                            <label>Contact Phone <span class="gvseo-hint-inline">(E.164 recommended)</span></label>
                            <input type="tel" name="org_phone" value="<?php echo esc_attr( $s['org_phone'] ); ?>" placeholder="+1-800-555-0100">
                            <span class="gvseo-field-hint">E.g. +1-800-555-0100 or +63-2-555-1234</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Primary Address ──────────────────────────────────── -->
            <div class="gvseo-card">
                <div class="gvseo-card-head">
                    <h3>📍 Primary Address</h3>
                    <p>Output as <code>schema.org/PostalAddress</code> on the Organization schema.</p>
                </div>
                <div class="gvseo-card-body">
                    <div class="gvseo-field gvseo-field-full">
                        <label>Street Address</label>
                        <input type="text" name="org_street" value="<?php echo esc_attr( $s['org_street'] ); ?>" placeholder="123 Main Street, Suite 400">
                    </div>
                    <div class="gvseo-field-row" style="margin-top:12px;">
                        <div class="gvseo-field"><label>City</label><input type="text" name="org_city" value="<?php echo esc_attr( $s['org_city'] ); ?>" placeholder="Makati City"></div>
                        <div class="gvseo-field"><label>State / Province</label><input type="text" name="org_state" value="<?php echo esc_attr( $s['org_state'] ); ?>" placeholder="Metro Manila"></div>
                        <div class="gvseo-field"><label>Postal / ZIP Code</label><input type="text" name="org_postcode" value="<?php echo esc_attr( $s['org_postcode'] ); ?>" placeholder="1200"></div>
                        <div class="gvseo-field">
                            <label>Country Code <span class="gvseo-hint-inline">(ISO 3166-1 alpha-2)</span></label>
                            <input type="text" name="org_country" value="<?php echo esc_attr( $s['org_country'] ); ?>" placeholder="PH" maxlength="2" style="text-transform:uppercase;">
                            <span class="gvseo-field-hint">2-letter code: PH, US, GB, AU…</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Additional Address ───────────────────────────────── -->
            <div class="gvseo-card">
                <div class="gvseo-card-head gvseo-flex-head">
                    <div>
                        <h3>📍 Additional Address <span class="gvseo-tag gvseo-tag-grey">Optional</span></h3>
                        <p>Add a second location — branch office, warehouse, or secondary site.</p>
                    </div>
                    <label class="gvseo-toggle" title="Enable additional address">
                        <input type="checkbox" name="org_addr2_enabled" value="1" id="gvseo-addr2-toggle" <?php checked( $s['org_addr2_enabled'], '1' ); ?>>
                        <span></span>
                    </label>
                </div>
                <div class="gvseo-card-body" id="gvseo-addr2-fields" style="<?php echo $s['org_addr2_enabled'] === '1' ? '' : 'display:none;'; ?>">
                    <div class="gvseo-field gvseo-field-full">
                        <label>Location / Branch Name</label>
                        <input type="text" name="org_addr2_name" value="<?php echo esc_attr( $s['org_addr2_name'] ); ?>" placeholder="North Branch Office">
                    </div>
                    <div class="gvseo-field gvseo-field-full" style="margin-top:10px;">
                        <label>Street Address</label>
                        <input type="text" name="org_addr2_street" value="<?php echo esc_attr( $s['org_addr2_street'] ); ?>" placeholder="456 Second Ave">
                    </div>
                    <div class="gvseo-field-row" style="margin-top:12px;">
                        <div class="gvseo-field"><label>City</label><input type="text" name="org_addr2_city" value="<?php echo esc_attr( $s['org_addr2_city'] ); ?>"></div>
                        <div class="gvseo-field"><label>State / Province</label><input type="text" name="org_addr2_state" value="<?php echo esc_attr( $s['org_addr2_state'] ); ?>"></div>
                        <div class="gvseo-field"><label>Postal / ZIP Code</label><input type="text" name="org_addr2_postcode" value="<?php echo esc_attr( $s['org_addr2_postcode'] ); ?>"></div>
                        <div class="gvseo-field"><label>Country Code</label><input type="text" name="org_addr2_country" value="<?php echo esc_attr( $s['org_addr2_country'] ); ?>" placeholder="PH" maxlength="2"></div>
                        <div class="gvseo-field"><label>Branch Phone</label><input type="tel" name="org_addr2_phone" value="<?php echo esc_attr( $s['org_addr2_phone'] ); ?>" placeholder="+63-2-555-5678"></div>
                    </div>
                </div>
            </div>

            <!-- ── Social Profiles ──────────────────────────────────── -->
            <div class="gvseo-card">
                <div class="gvseo-card-head"><h3>🔗 Social Profiles (sameAs)</h3><p>Links your entity across the web for Google's Knowledge Graph.</p></div>
                <div class="gvseo-card-body">
                    <div class="gvseo-field-row">
                        <div class="gvseo-field"><label>Facebook</label><input type="url" name="social_fb" value="<?php echo esc_attr( $s['social_fb'] ); ?>"></div>
                        <div class="gvseo-field"><label>X / Twitter</label><input type="url" name="social_tw" value="<?php echo esc_attr( $s['social_tw'] ); ?>"></div>
                        <div class="gvseo-field"><label>Instagram</label><input type="url" name="social_ig" value="<?php echo esc_attr( $s['social_ig'] ); ?>"></div>
                    </div>
                    <div class="gvseo-field-row">
                        <div class="gvseo-field"><label>LinkedIn</label><input type="url" name="social_li" value="<?php echo esc_attr( $s['social_li'] ); ?>"></div>
                        <div class="gvseo-field"><label>YouTube</label><input type="url" name="social_yt" value="<?php echo esc_attr( $s['social_yt'] ); ?>"></div>
                        <div class="gvseo-field"><label>TikTok</label><input type="url" name="social_tt" value="<?php echo esc_attr( $s['social_tt'] ); ?>"></div>
                    </div>
                </div>
            </div>

            <!-- ── Exclusions ───────────────────────────────────────── -->
            <div class="gvseo-card">
                <div class="gvseo-card-head">
                    <h3>🚫 Schema & SEO Exclusions</h3>
                    <p>Exclude post types and individual posts from all schema output and SEO analysis. Useful for Elementor templates, Divi layouts, and other builder content that should not be indexed.</p>
                </div>
                <div class="gvseo-card-body">

                    <!-- Post types the user can exclude -->
                    <?php if ( ! empty( $all_types ) ) : ?>
                        <div class="gvseo-excl-section">
                            <h4>Exclude Post Types</h4>
                            <p class="gvseo-hint">Check any post type to remove it from schema output and SEO analysis entirely.</p>
                            <div class="gvseo-excl-grid">
                                <?php foreach ( $all_types as $pt ) :
                                    $checked = in_array( $pt->name, (array) $s['excluded_types'], true );
                                ?>
                                    <label class="gvseo-excl-item">
                                        <input type="checkbox"
                                            name="excluded_types[]"
                                            value="<?php echo esc_attr( $pt->name ); ?>"
                                            <?php checked( $checked ); ?>>
                                        <span class="gvseo-excl-name"><?php echo esc_html( $pt->label ); ?></span>
                                        <code><?php echo esc_html( $pt->name ); ?></code>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Specific post IDs -->
                    <div class="gvseo-excl-section" style="margin-top:20px;">
                        <h4>Exclude Specific Pages / Posts by ID</h4>
                        <p class="gvseo-hint">Comma-separated Post IDs. These individual posts will have schema disabled and will be skipped in SEO analysis.</p>
                        <input type="text"
                            name="excluded_post_ids"
                            value="<?php echo esc_attr( $s['excluded_post_ids'] ); ?>"
                            placeholder="e.g. 42, 117, 305"
                            class="gvseo-wide-input">
                        <p class="gvseo-field-hint">Find a post's ID in the URL when editing: <code>post.php?post=<strong>42</strong>&action=edit</code></p>
                    </div>
                </div>
            </div>


            <!-- ── Local Business Schema ─────────────────────────────── -->
            <div class="gvseo-card">
                <div class="gvseo-card-head gvseo-flex-head">
                    <div>
                        <h3>📍 Local Business Locations</h3>
                        <p>Add one entry per physical location or branch. Each outputs its own <code>@type:LocalBusiness</code> JSON-LD schema — fully validated against <a href="https://validator.schema.org/" target="_blank">schema.org</a>.</p>
                    </div>
                    <button type="button" id="gvseo-add-location" class="gvseo-btn gvseo-btn-primary gvseo-btn-xs">+ Add Location</button>
                </div>
                <div class="gvseo-card-body gvseo-no-pad">
                    <div id="gvseo-lb-locations">
                        <?php
                        $lb_locs   = $s['lb_locations'] ?? [];
                        $lb_types  = self::lb_type_list();
                        $all_days  = [ 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday' ];

                        if ( empty( $lb_locs ) ) :
                        ?>
                            <div id="gvseo-lb-empty" class="gvseo-lb-empty">
                                <span>🏢</span>
                                <p>No locations added yet.<br>Click <strong>+ Add Location</strong> to add your first business location.</p>
                            </div>
                        <?php else : ?>
                            <?php foreach ( $lb_locs as $li => $loc ) : ?>
                                <?php echo self::render_lb_location_card( $li, $loc, $lb_types, $all_days, $s ); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ── CPT Schema Defaults ───────────────────────────────── -->
            <div class="gvseo-card">
                <div class="gvseo-card-head">
                    <h3>📦 Post Type → Schema Defaults</h3>
                    <p>Default schema type per post type. Overridden per-page in the Schema tab of the editor.</p>
                </div>
                <div class="gvseo-card-body gvseo-no-pad">
                    <table class="gvseo-cpt-table">
                        <thead><tr><th>Post Type</th><th>Slug</th><th>Default Schema Type</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ( $cpt_types as $pt ) :
                            $current  = $s['cpt_defaults'][ $pt->name ] ?? self::schema_type_for_post_type( $pt->name );
                            $is_woo   = $pt->name === 'product';
                            $is_smart = in_array( $pt->name, [ 'post', 'page', 'product' ], true );
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $pt->label ); ?></strong>
                                    <?php if ( $is_woo ) : ?><span class="gvseo-woo-badge">WooCommerce</span><?php endif; ?>
                                </td>
                                <td><code><?php echo esc_html( $pt->name ); ?></code></td>
                                <td>
                                    <select name="cpt_defaults[<?php echo esc_attr( $pt->name ); ?>]" class="gvseo-cpt-select">
                                        <?php foreach ( $schema_list as $val => $lbl ) : ?>
                                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current, $val ); ?>><?php echo esc_html( $lbl ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><span class="gvseo-tag <?php echo $is_smart ? 'gvseo-tag-blue' : 'gvseo-tag-grey'; ?>"><?php echo $is_smart ? 'Smart default' : 'Custom CPT'; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Feature Toggles ──────────────────────────────────── -->
            <div class="gvseo-card">
                <div class="gvseo-card-head"><h3>⚙️ Global Schema Features</h3></div>
                <div class="gvseo-card-body">
                    <?php
                    $toggles = [
                        'organization' => [ '🏢 Organization Schema',    'Outputs @type:Organization with @id, address, phone, logo, and sameAs on every page.' ],
                        'sitelinks'    => [ '🔍 Sitelinks Searchbox',     'WebSite + SearchAction markup on the homepage.' ],
                        'breadcrumbs'  => [ '🔗 BreadcrumbList',          'Auto-built breadcrumbs on all non-home pages.' ],
                        'woo_bridge'   => [ '🛒 WooCommerce Data Bridge',  'Auto-pull price, SKU, stock, ratings from WooCommerce products.' ],
                    ];
                    foreach ( $toggles as $key => [ $label, $desc ] ) : ?>
                        <div class="gvseo-toggle-row">
                            <div><strong><?php echo esc_html( $label ); ?></strong><p><?php echo esc_html( $desc ); ?></p></div>
                            <label class="gvseo-toggle">
                                <input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( $s[ $key ], '1' ); ?>>
                                <span></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── Sitemap ───────────────────────────────────────────── -->
            <div class="gvseo-card">
                <div class="gvseo-card-head gvseo-flex-head">
                    <div>
                        <h3>🗺️ XML Sitemap</h3>
                        <p>Auto-generated sitemap index. Respects all exclusion settings above. Linked in your robots.txt automatically.</p>
                    </div>
                    <a href="<?php echo esc_url( GVSEO_Sitemap::sitemap_url() ); ?>" target="_blank" class="gvseo-btn gvseo-btn-ghost gvseo-btn-xs">
                        View Sitemap ↗
                    </a>
                </div>
                <div class="gvseo-card-body">
                    <div class="gvseo-sitemap-info">
                        <div class="gvseo-sm-row">
                            <span>📄 Sitemap Index</span>
                            <a href="<?php echo esc_url( home_url( '/sitemap.xml' ) ); ?>" target="_blank">
                                <?php echo esc_html( home_url( '/sitemap.xml' ) ); ?>
                            </a>
                        </div>
                        <?php
                        $types    = get_post_types( [ 'public' => true ], 'names' );
                        $excluded = GVSEO_Settings::get_excluded_types();
                        foreach ( $types as $type ) :
                            if ( in_array( $type, $excluded, true ) ) { continue; }
                            $count = (int) wp_count_posts( $type )->publish;
                            if ( $count === 0 ) { continue; }
                            $slug = sanitize_title( $type ) . 's';
                            ?>
                            <div class="gvseo-sm-row">
                                <span>📝 <?php echo esc_html( ucfirst( $type ) ); ?></span>
                                <span>
                                    <a href="<?php echo esc_url( home_url( "/sitemap-{$slug}.xml" ) ); ?>" target="_blank">
                                        sitemap-<?php echo esc_html( $slug ); ?>.xml
                                    </a>
                                    <em>(<?php echo (int) $count; ?> URLs)</em>
                                </span>
                            </div>
                        <?php endforeach; ?>
                        <p class="gvseo-hint" style="margin-top:10px;">
                            💡 Submit your sitemap to
                            <a href="https://search.google.com/search-console" target="_blank">Google Search Console</a>
                            to speed up indexing.
                        </p>
                    </div>
                </div>
            </div>

            <div class="gvseo-form-footer">
                <button type="submit" class="gvseo-btn gvseo-btn-primary">💾 Save Settings</button>
            </div>
        </form>
        </div></div>

        <script>
        document.getElementById('gvseo-addr2-toggle').addEventListener('change', function() {
            document.getElementById('gvseo-addr2-fields').style.display = this.checked ? '' : 'none';
        });
        </script>
        <?php
    }

    /* ── Helpers ──────────────────────────────────────────────────── */

    /**
     * Post types shown in the exclusion checkbox grid.
     *
     * Shows: post, page, product (WooCommerce), and any truly custom
     * post types (registered by themes/plugins with show_ui = true).
     * Excludes all WordPress core system types, builder templates,
     * and any other non-content types.
     */
    public static function get_all_post_types_for_exclusion() {
        // Everything that should never appear in the list.
        $always_skip = array_merge( self::DEFAULT_EXCLUDED_TYPES, [
            // WP core non-content
            'revision', 'nav_menu_item', 'custom_css', 'customize_changeset',
            'oembed_cache', 'user_request', 'wp_global_styles',
            // Yoast
            'yst_prominent_words',
            // Common internal types
            'acf-field', 'acf-field-group', 'acf-post-type', 'acf-taxonomy',
            'acf-ui-options-page',
            // Gravity Forms
            'gf_form',
        ] );

        $types = get_post_types( [ 'show_ui' => true ], 'objects' );

        return array_filter( $types, function( $pt ) use ( $always_skip ) {
            // Must not be in skip list.
            if ( in_array( $pt->name, $always_skip, true ) ) { return false; }
            // Must have a readable label.
            if ( empty( trim( $pt->label ) ) ) { return false; }
            return true;
        } );
    }

    /** Public post types for CPT schema defaults table (excluding system types). */
    public static function get_public_post_types() {
        $types = get_post_types( [ 'public' => true ], 'objects' );
        return array_filter( $types, fn( $pt ) => ! in_array( $pt->name, self::DEFAULT_EXCLUDED_TYPES, true ) );
    }

    public static function schema_type_list() {
        return [
            'WebPage' => 'Web Page (generic)', 'Article' => 'Article',
            'BlogPosting' => 'Blog Post', 'NewsArticle' => 'News Article',
            'Product' => 'Product', 'Event' => 'Event', 'FAQPage' => 'FAQ Page',
            'HowTo' => 'How-To', 'Recipe' => 'Recipe',
            'LocalBusiness' => 'Local Business', 'JobPosting' => 'Job Posting',
            'Course' => 'Course', 'SoftwareApplication' => 'Software App',
            'VideoObject' => 'Video', 'Custom' => 'Custom JSON-LD',
        ];
    }

    public static function nav( $active = 'dashboard' ) {
        $links = [
            'dashboard' => [ admin_url( 'admin.php?page=grapevine-seo' ),          '📊 Dashboard' ],
            'seo'       => [ admin_url( 'admin.php?page=grapevine-seo-seo' ),      '🔍 SEO Analysis' ],
            'settings'  => [ admin_url( 'admin.php?page=grapevine-seo-settings' ), '⚙️ Settings' ],
            'version'   => [ admin_url( 'admin.php?page=grapevine-seo-version' ),  '🏷️ Versions' ],
        ];
        echo '<div class="gvseo-nav">';
        foreach ( $links as $key => [ $url, $label ] ) {
            $cls = $key === $active ? 'gvseo-nav-link active' : 'gvseo-nav-link';
            printf( '<a href="%s" class="%s">%s</a>', esc_url( $url ), esc_attr( $cls ), esc_html( $label ) );
        }
        echo '</div>';
    }
    /* ── LocalBusiness helpers ────────────────────────────────────── */

    public static function lb_type_list() {
        return [
            'LocalBusiness' => 'Local Business (generic)',
            'Restaurant' => 'Restaurant', 'FoodEstablishment' => 'Food Establishment',
            'CafeOrCoffeeShop' => 'Café / Coffee Shop', 'Bakery' => 'Bakery',
            'BarOrPub' => 'Bar or Pub', 'FastFoodRestaurant' => 'Fast Food Restaurant',
            'Dentist' => 'Dentist', 'Physician' => 'Physician / Doctor',
            'MedicalBusiness' => 'Medical Business', 'Optician' => 'Optician',
            'Pharmacy' => 'Pharmacy', 'Physiotherapist' => 'Physiotherapist',
            'Attorney' => 'Attorney / Lawyer', 'AccountingService' => 'Accounting / Finance',
            'FinancialService' => 'Financial Service', 'InsuranceAgency' => 'Insurance Agency',
            'RealEstateAgent' => 'Real Estate Agent', 'AutoRepair' => 'Auto Repair',
            'AutoDealer' => 'Auto Dealer', 'BeautySalon' => 'Beauty Salon',
            'HairSalon' => 'Hair Salon', 'HealthClub' => 'Health Club / Gym',
            'Hotel' => 'Hotel', 'LodgingBusiness' => 'Lodging (general)',
            'ChildCare' => 'Child Care', 'CleaningService' => 'Cleaning Service',
            'Electrician' => 'Electrician', 'Plumber' => 'Plumber',
            'GeneralContractor' => 'General Contractor',
            'HomeAndConstructionBusiness' => 'Home & Construction',
            'Locksmith' => 'Locksmith', 'MovingCompany' => 'Moving Company',
            'PetStore' => 'Pet Store', 'Veterinary' => 'Veterinary',
            'Store' => 'Retail Store (generic)', 'ShoppingCenter' => 'Shopping Centre',
            'TravelAgency' => 'Travel Agency', 'SportsActivityLocation' => 'Sports / Recreation',
            'EntertainmentBusiness' => 'Entertainment',
            'ProfessionalService' => 'Professional Service',
            'ITConsultant' => 'IT / Tech Consultant',
            'MarketingAgency' => 'Marketing Agency',
            'DigitalMarketingAgency' => 'Digital Marketing Agency',
            'Winery' => 'Winery', 'Florist' => 'Florist',
            'BookStore' => 'Book Store', 'ComputerStore' => 'Computer Store',
            'JewelryStore' => 'Jewellery Store', 'ShoeStore' => 'Shoe Store',
            'ClothingStore' => 'Clothing Store', 'FurnitureStore' => 'Furniture Store',
            'HomeGoodsStore' => 'Home Goods Store', 'HardwareStore' => 'Hardware Store',
        ];
    }

    /**
     * Render a single location card for the settings UI.
     *
     * @param int   $li       Location index (0-based).
     * @param array $loc      Location data.
     * @param array $lb_types Schema type list.
     * @param array $all_days Day names array.
     * @param array $g        Global settings (for placeholder fallbacks).
     */
    public static function render_lb_location_card( $li, $loc, $lb_types, $all_days, $g ) {
        $name   = ! empty( $loc['name'] ) ? $loc['name'] : ( 'Location ' . ( $li + 1 ) );
        $type   = $loc['type']        ?? 'LocalBusiness';
        $enabled= ( $loc['enabled'] ?? '1' ) === '1';
        ob_start();
        ?>
        <div class="gvseo-lb-card" data-loc="<?php echo (int) $li; ?>">
            <div class="gvseo-lb-card-head">
                <div class="gvseo-lb-card-title">
                    <button type="button" class="gvseo-lb-toggle-btn" title="Expand / collapse">▾</button>
                    <strong class="gvseo-lb-card-name"><?php echo esc_html( $name ); ?></strong>
                    <span class="gvseo-type-badge"><?php echo esc_html( $lb_types[ $type ] ?? $type ); ?></span>
                    <?php if ( ! $enabled ) : ?>
                        <span class="gvseo-tag gvseo-tag-grey" style="margin-left:4px;">Disabled</span>
                    <?php endif; ?>
                </div>
                <div class="gvseo-lb-card-actions">
                    <label class="gvseo-toggle" title="Enable this location">
                        <input type="checkbox" name="lb_loc[<?php echo (int) $li; ?>][enabled]" value="1" <?php checked( $enabled ); ?>>
                        <span></span>
                    </label>
                    <button type="button" class="gvseo-btn gvseo-btn-ghost gvseo-btn-xs gvseo-lb-remove">✕ Remove</button>
                </div>
            </div>
            <div class="gvseo-lb-card-body">
                <!-- Type + Name -->
                <div class="gvseo-field-row">
                    <div class="gvseo-field">
                        <label>Business Type</label>
                        <select name="lb_loc[<?php echo (int) $li; ?>][type]" class="gvseo-cpt-select gvseo-lb-type-select">
                            <?php foreach ( $lb_types as $val => $lbl ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $type, $val ); ?>><?php echo esc_html( $lbl ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="gvseo-field">
                        <label>Location / Branch Name</label>
                        <input type="text" name="lb_loc[<?php echo (int) $li; ?>][name]"
                            value="<?php echo esc_attr( $loc['name'] ?? '' ); ?>"
                            placeholder="e.g. Main Office, North Branch"
                            class="gvseo-lb-name-input">
                    </div>
                </div>
                <div class="gvseo-field">
                    <label>Description</label>
                    <textarea name="lb_loc[<?php echo (int) $li; ?>][description]" rows="2"
                        placeholder="Brief description of this location…"><?php echo esc_textarea( $loc['description'] ?? '' ); ?></textarea>
                </div>

                <!-- Contact -->
                <h4 class="gvseo-section-h4">📞 Contact</h4>
                <div class="gvseo-field-row">
                    <div class="gvseo-field">
                        <label>Phone <span class="gvseo-hint-inline">(E.164)</span></label>
                        <input type="tel" name="lb_loc[<?php echo (int) $li; ?>][phone]"
                            value="<?php echo esc_attr( $loc['phone'] ?? '' ); ?>"
                            placeholder="<?php echo esc_attr( $g['org_phone'] ?? '' ); ?>">
                    </div>
                    <div class="gvseo-field">
                        <label>Email</label>
                        <input type="email" name="lb_loc[<?php echo (int) $li; ?>][email]"
                            value="<?php echo esc_attr( $loc['email'] ?? '' ); ?>"
                            placeholder="<?php echo esc_attr( $g['org_email'] ?? '' ); ?>">
                    </div>
                    <div class="gvseo-field">
                        <label>Google Maps URL</label>
                        <input type="url" name="lb_loc[<?php echo (int) $li; ?>][maps_url]"
                            value="<?php echo esc_attr( $loc['maps_url'] ?? '' ); ?>"
                            placeholder="https://maps.google.com/?cid=…">
                    </div>
                </div>

                <!-- Address -->
                <h4 class="gvseo-section-h4">📍 Address</h4>
                <div class="gvseo-field">
                    <label>Street Address</label>
                    <input type="text" name="lb_loc[<?php echo (int) $li; ?>][street]"
                        value="<?php echo esc_attr( $loc['street'] ?? '' ); ?>">
                </div>
                <div class="gvseo-field-row" style="margin-top:10px;">
                    <div class="gvseo-field"><label>City</label>
                        <input type="text" name="lb_loc[<?php echo (int) $li; ?>][city]" value="<?php echo esc_attr( $loc['city'] ?? '' ); ?>"></div>
                    <div class="gvseo-field"><label>State</label>
                        <input type="text" name="lb_loc[<?php echo (int) $li; ?>][state]" value="<?php echo esc_attr( $loc['state'] ?? '' ); ?>"></div>
                    <div class="gvseo-field"><label>Postcode</label>
                        <input type="text" name="lb_loc[<?php echo (int) $li; ?>][postcode]" value="<?php echo esc_attr( $loc['postcode'] ?? '' ); ?>"></div>
                    <div class="gvseo-field"><label>Country (ISO)</label>
                        <input type="text" name="lb_loc[<?php echo (int) $li; ?>][country]" value="<?php echo esc_attr( $loc['country'] ?? 'AU' ); ?>" maxlength="2"></div>
                </div>

                <!-- Geo -->
                <h4 class="gvseo-section-h4">🌐 Geo Coordinates</h4>
                <div class="gvseo-field-row">
                    <div class="gvseo-field"><label>Latitude</label>
                        <input type="text" name="lb_loc[<?php echo (int) $li; ?>][lat]" value="<?php echo esc_attr( $loc['lat'] ?? '' ); ?>" placeholder="-33.8688"></div>
                    <div class="gvseo-field"><label>Longitude</label>
                        <input type="text" name="lb_loc[<?php echo (int) $li; ?>][lng]" value="<?php echo esc_attr( $loc['lng'] ?? '' ); ?>" placeholder="151.2093"></div>
                    <div class="gvseo-field"><label>Area Served</label>
                        <input type="text" name="lb_loc[<?php echo (int) $li; ?>][area_served]" value="<?php echo esc_attr( $loc['area_served'] ?? '' ); ?>" placeholder="Sydney, NSW"></div>
                </div>

                <!-- Business Details -->
                <h4 class="gvseo-section-h4">💳 Business Details</h4>
                <div class="gvseo-field-row">
                    <div class="gvseo-field"><label>Price Range</label>
                        <select name="lb_loc[<?php echo (int) $li; ?>][price_range]" class="gvseo-cpt-select">
                            <option value="">— Not specified —</option>
                            <?php foreach ( [ '$' => '$ Inexpensive', '$$' => '$$ Moderate', '$$$' => '$$$ Expensive', '$$$$' => '$$$$ Very Expensive' ] as $pr_val => $pr_lbl ) : ?>
                                <option value="<?php echo esc_attr( $pr_val ); ?>" <?php selected( $loc['price_range'] ?? '', $pr_val ); ?>><?php echo esc_html( $pr_lbl ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="gvseo-field"><label>Payment Accepted</label>
                        <input type="text" name="lb_loc[<?php echo (int) $li; ?>][payment]" value="<?php echo esc_attr( $loc['payment'] ?? '' ); ?>" placeholder="Cash, Credit Card, EFTPOS"></div>
                    <div class="gvseo-field"><label>Currencies</label>
                        <input type="text" name="lb_loc[<?php echo (int) $li; ?>][currencies]" value="<?php echo esc_attr( $loc['currencies'] ?? 'AUD' ); ?>"></div>
                </div>

                <!-- Opening Hours -->
                <h4 class="gvseo-section-h4">🕐 Opening Hours</h4>
                <div class="gvseo-hours-wrap" data-loc="<?php echo (int) $li; ?>">
                    <?php
                    $hour_groups = ! empty( $loc['hours'] ) ? $loc['hours'] : [
                        [ 'days' => [ 'Monday','Tuesday','Wednesday','Thursday','Friday' ], 'opens' => '09:00', 'closes' => '17:00' ],
                    ];
                    foreach ( $hour_groups as $hi => $hg ) : ?>
                        <div class="gvseo-hours-row">
                            <div class="gvseo-hours-days">
                                <?php foreach ( $all_days as $day ) : ?>
                                    <label class="gvseo-day-pill <?php echo in_array( $day, (array) $hg['days'], true ) ? 'active' : ''; ?>">
                                        <input type="checkbox"
                                            name="lb_loc[<?php echo (int) $li; ?>][hour_days][<?php echo (int) $hi; ?>][]"
                                            value="<?php echo esc_attr( $day ); ?>"
                                            <?php checked( in_array( $day, (array) $hg['days'], true ) ); ?>>
                                        <?php echo esc_html( substr( $day, 0, 3 ) ); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <div class="gvseo-hours-times">
                                <input type="time" name="lb_loc[<?php echo (int) $li; ?>][hour_opens][<?php echo (int) $hi; ?>]" value="<?php echo esc_attr( $hg['opens'] ); ?>">
                                <span>to</span>
                                <input type="time" name="lb_loc[<?php echo (int) $li; ?>][hour_closes][<?php echo (int) $hi; ?>]" value="<?php echo esc_attr( $hg['closes'] ); ?>">
                                <button type="button" class="gvseo-btn gvseo-btn-ghost gvseo-btn-xs gvseo-remove-hours">✕</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="gvseo-btn gvseo-btn-ghost gvseo-btn-xs gvseo-add-hours" style="margin-top:6px;" data-loc="<?php echo (int) $li; ?>">
                    + Add Hours Group
                </button>

                <!-- sameAs -->
                <div class="gvseo-toggle-row" style="margin-top:16px;">
                    <div><strong>Inherit Social Profiles (sameAs)</strong>
                        <p>Use the social profiles from Organization schema.</p></div>
                    <label class="gvseo-toggle">
                        <input type="checkbox" name="lb_loc[<?php echo (int) $li; ?>][same_as_org]" value="1"
                            <?php checked( ( $loc['same_as_org'] ?? '1' ) === '1' ); ?>>
                        <span></span>
                    </label>
                </div>
            </div><!-- /.gvseo-lb-card-body -->
        </div><!-- /.gvseo-lb-card -->
        <?php
        return ob_get_clean();
    }
}
