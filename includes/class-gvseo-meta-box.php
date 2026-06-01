<?php
/**
 * Meta Box — Schema + SEO tabs.
 * Two-tab interface per post/page: Schema override | SEO fields.
 * @package GrapevineSEO
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class GVSEO_Meta_Box {

    public static function init() {
        add_action( 'add_meta_boxes', [ __CLASS__, 'register' ] );
        add_action( 'save_post',      [ __CLASS__, 'save' ], 10, 2 );
    }

    public static function register() {
        $types = get_post_types( [ 'public' => true ], 'names' );
        foreach ( $types as $t ) {
            add_meta_box( 'gvseo-meta-box', '★ Grapevine SEO', [ __CLASS__, 'render' ],
                $t, 'normal', 'high' );
        }
    }

    /* ─── RENDER ───────────────────────────────────────────────────── */
    public static function render( $post ) {
        wp_nonce_field( 'gvseo_mb_save', 'gvseo_mb_nonce' );

        $mode    = get_post_meta( $post->ID, '_gvseo_schema_mode', true ) ?: 'global';
        $type    = get_post_meta( $post->ID, '_gvseo_schema_type', true ) ?: 'Article';

        /* SEO fields */
        $kw        = get_post_meta( $post->ID, '_gvseo_focus_kw',          true );
        $kw_sec    = get_post_meta( $post->ID, '_gvseo_focus_kw_secondary', true );
        $meta_desc = get_post_meta( $post->ID, '_gvseo_meta_desc', true );
        $og_title  = get_post_meta( $post->ID, '_gvseo_og_title',  true );
        $og_desc   = get_post_meta( $post->ID, '_gvseo_og_desc',   true );
        $og_img    = get_post_meta( $post->ID, '_gvseo_og_image',  true );
        $noindex   = get_post_meta( $post->ID, '_gvseo_noindex',   true );
        $seo_score = get_post_meta( $post->ID, '_gvseo_seo_score', true );
        $seo_ts    = get_post_meta( $post->ID, '_gvseo_seo_ts',    true );

        /* Schema-specific fields */
        $author_name  = get_post_meta( $post->ID, '_gvseo_author_name',  true );
        $author_url   = get_post_meta( $post->ID, '_gvseo_author_url',   true );
        $faq_items    = json_decode( stripslashes( (string) get_post_meta( $post->ID, '_gvseo_faq_items', true ) ), true ) ?: [ [ 'q' => '', 'a' => '' ] ];
        $steps        = json_decode( stripslashes( (string) get_post_meta( $post->ID, '_gvseo_steps', true ) ), true ) ?: [ [ 'name' => '', 'text' => '' ] ];
        $total_time   = get_post_meta( $post->ID, '_gvseo_total_time', true );
        $price        = get_post_meta( $post->ID, '_gvseo_price', true );
        $currency     = get_post_meta( $post->ID, '_gvseo_currency', true ) ?: 'USD';
        $avail        = get_post_meta( $post->ID, '_gvseo_availability', true ) ?: 'InStock';
        $sku          = get_post_meta( $post->ID, '_gvseo_sku', true );
        $rating       = get_post_meta( $post->ID, '_gvseo_rating', true );
        $rcount       = get_post_meta( $post->ID, '_gvseo_rating_count', true );
        $ev_start     = get_post_meta( $post->ID, '_gvseo_event_start', true );
        $ev_end       = get_post_meta( $post->ID, '_gvseo_event_end', true );
        $ev_status    = get_post_meta( $post->ID, '_gvseo_event_status', true ) ?: 'EventScheduled';
        $ev_attend    = get_post_meta( $post->ID, '_gvseo_event_attend', true ) ?: 'OfflineEventAttendanceMode';
        $venue        = get_post_meta( $post->ID, '_gvseo_venue', true );
        $venue_addr   = get_post_meta( $post->ID, '_gvseo_venue_address', true );
        $venue_city   = get_post_meta( $post->ID, '_gvseo_venue_city', true );
        $venue_ctry   = get_post_meta( $post->ID, '_gvseo_venue_country', true );
        $prep_time    = get_post_meta( $post->ID, '_gvseo_prep_time', true );
        $cook_time    = get_post_meta( $post->ID, '_gvseo_cook_time', true );
        $ingredients  = get_post_meta( $post->ID, '_gvseo_ingredients', true );
        $recipe_yield = get_post_meta( $post->ID, '_gvseo_recipe_yield', true );
        $calories     = get_post_meta( $post->ID, '_gvseo_calories', true );
        $custom_json  = get_post_meta( $post->ID, '_gvseo_custom_json', true );

        $schema_types = [
            'Article' => 'Article', 'BlogPosting' => 'Blog Post',
            'NewsArticle' => 'News Article', 'WebPage' => 'Web Page',
            'FAQPage' => 'FAQ Page', 'HowTo' => 'How-To',
            'Product' => 'Product', 'Event' => 'Event',
            'Recipe' => 'Recipe', 'LocalBusiness' => 'Local Business',
            'Custom' => 'Custom JSON-LD',
        ];

        $score_label = $seo_score !== '' && $seo_score !== false ? GVSEO_SEO_Analyzer::label( (int) $seo_score ) : 'none';
        ?>
        <div class="gvseo-mb">
            <!-- TABS -->
            <div class="gvseo-mb-tabs">
                <button type="button" class="gvseo-mb-tab gvseo-mb-tab-active" data-tab="schema">⬡ Schema</button>
                <button type="button" class="gvseo-mb-tab" data-tab="seo">
                    🔍 SEO Analysis
                    <?php if ( $seo_score !== '' && $seo_score !== false ) : ?>
                        <span class="gvseo-mb-badge gvseo-mb-badge-<?php echo esc_attr( $score_label ); ?>"><?php echo (int) $seo_score; ?></span>
                    <?php endif; ?>
                </button>
            </div>

            <!-- ════════ SCHEMA TAB ════════ -->
            <div class="gvseo-mb-panel gvseo-mb-panel-active" id="gvseo-tab-schema">
                <div class="gvseo-mb-row">
                    <label class="gvseo-mb-radio <?php echo $mode === 'global' ? 'checked' : ''; ?>">
                        <input type="radio" name="_gvseo_schema_mode" value="global" <?php checked( $mode, 'global' ); ?>>
                        <span>Use Global Schema</span>
                    </label>
                    <label class="gvseo-mb-radio <?php echo $mode === 'override' ? 'checked' : ''; ?>">
                        <input type="radio" name="_gvseo_schema_mode" value="override" <?php checked( $mode, 'override' ); ?>>
                        <span>Override for this page</span>
                    </label>
                    <label class="gvseo-mb-radio <?php echo $mode === 'disabled' ? 'checked' : ''; ?>">
                        <input type="radio" name="_gvseo_schema_mode" value="disabled" <?php checked( $mode, 'disabled' ); ?>>
                        <span>Disable Schema</span>
                    </label>
                    <a href="https://search.google.com/test/rich-results" target="_blank" class="gvseo-btn gvseo-btn-xs gvseo-btn-ghost" style="margin-left:auto;">Test in Google ↗</a>
                </div>

                <div id="gvseo-schema-override" style="<?php echo 'override' === $mode ? '' : 'display:none;'; ?>">
                    <!-- Type selector -->
                    <div class="gvseo-mb-field-row">
                        <div class="gvseo-mb-field">
                            <label>Schema Type</label>
                            <select name="_gvseo_schema_type" id="gvseo_schema_type" class="gvseo-mb-select">
                                <?php foreach ( $schema_types as $val => $lbl ) : ?>
                                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $type, $val ); ?>><?php echo esc_html( $lbl ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="gvseo-mb-field">
                            <label>Author Name <span class="gvseo-mb-muted">(Article types)</span></label>
                            <input type="text" name="_gvseo_author_name" value="<?php echo esc_attr( $author_name ); ?>" placeholder="Post author name">
                        </div>
                        <div class="gvseo-mb-field">
                            <label>Author URL</label>
                            <input type="url" name="_gvseo_author_url" value="<?php echo esc_url( $author_url ); ?>" placeholder="https://…">
                        </div>
                    </div>

                    <!-- FAQ items -->
                    <div class="gvseo-schema-group" data-for="FAQPage">
                        <div class="gvseo-mb-section-label">FAQ Items <span class="gvseo-mb-muted">— must match text visible on the page</span></div>
                        <div id="gvseo-faq-list">
                            <?php foreach ( $faq_items as $i => $fi ) : ?>
                            <div class="gvseo-repeater-item">
                                <div class="gvseo-ri-num"><?php echo $i + 1; ?></div>
                                <div class="gvseo-ri-body">
                                    <input type="text" name="_gvseo_faq_q[]" value="<?php echo esc_attr( $fi['q'] ); ?>" placeholder="Question">
                                    <textarea name="_gvseo_faq_a[]" rows="2" placeholder="Answer"><?php echo esc_textarea( $fi['a'] ); ?></textarea>
                                </div>
                                <button type="button" class="gvseo-ri-del" data-list="gvseo-faq-list">✕</button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="gvseo-btn gvseo-btn-xs gvseo-btn-ghost gvseo-add-row" data-list="gvseo-faq-list" data-template="faq">+ Add Question</button>
                    </div>

                    <!-- Steps (HowTo / Recipe) -->
                    <div class="gvseo-schema-group" data-for="HowTo Recipe">
                        <div class="gvseo-mb-section-label">Steps</div>
                        <div id="gvseo-steps-list">
                            <?php foreach ( $steps as $i => $st ) : ?>
                            <div class="gvseo-repeater-item">
                                <div class="gvseo-ri-num"><?php echo $i + 1; ?></div>
                                <div class="gvseo-ri-body">
                                    <input type="text" name="_gvseo_step_name[]" value="<?php echo esc_attr( $st['name'] ); ?>" placeholder="Step title">
                                    <textarea name="_gvseo_step_text[]" rows="2" placeholder="Step description"><?php echo esc_textarea( $st['text'] ); ?></textarea>
                                </div>
                                <button type="button" class="gvseo-ri-del" data-list="gvseo-steps-list">✕</button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="gvseo-btn gvseo-btn-xs gvseo-btn-ghost gvseo-add-row" data-list="gvseo-steps-list" data-template="step">+ Add Step</button>
                    </div>

                    <!-- HowTo extras -->
                    <div class="gvseo-schema-group" data-for="HowTo">
                        <div class="gvseo-mb-field-row">
                            <div class="gvseo-mb-field"><label>Total Time (ISO 8601)</label>
                                <input type="text" name="_gvseo_total_time" value="<?php echo esc_attr( $total_time ); ?>" placeholder="PT30M"></div>
                        </div>
                    </div>

                    <!-- Product fields -->
                    <div class="gvseo-schema-group" data-for="Product">
                        <div class="gvseo-mb-section-label">Product Details</div>
                        <div class="gvseo-mb-field-row">
                            <div class="gvseo-mb-field"><label>SKU</label><input type="text" name="_gvseo_sku" value="<?php echo esc_attr( $sku ); ?>"></div>
                            <div class="gvseo-mb-field"><label>Price</label><input type="text" name="_gvseo_price" value="<?php echo esc_attr( $price ); ?>" placeholder="19.99"></div>
                            <div class="gvseo-mb-field"><label>Currency (ISO 4217)</label><input type="text" name="_gvseo_currency" value="<?php echo esc_attr( $currency ); ?>" placeholder="USD" maxlength="3"></div>
                        </div>
                        <div class="gvseo-mb-field-row">
                            <div class="gvseo-mb-field"><label>Availability</label>
                                <select name="_gvseo_availability" class="gvseo-mb-select">
                                    <?php foreach ( [ 'InStock', 'OutOfStock', 'PreOrder', 'BackOrder', 'Discontinued', 'SoldOut' ] as $av ) : ?>
                                        <option value="<?php echo esc_attr( $av ); ?>" <?php selected( $avail, $av ); ?>><?php echo esc_html( $av ); ?></option>
                                    <?php endforeach; ?>
                                </select></div>
                            <div class="gvseo-mb-field"><label>Rating (1–5)</label><input type="number" name="_gvseo_rating" min="1" max="5" step="0.1" value="<?php echo esc_attr( $rating ); ?>"></div>
                            <div class="gvseo-mb-field"><label>Rating Count</label><input type="number" name="_gvseo_rating_count" min="1" value="<?php echo esc_attr( $rcount ); ?>"></div>
                        </div>
                    </div>

                    <!-- Event fields -->
                    <div class="gvseo-schema-group" data-for="Event">
                        <div class="gvseo-mb-section-label">Event Details</div>
                        <div class="gvseo-mb-field-row">
                            <div class="gvseo-mb-field"><label>Start Date & Time <span class="gvseo-required">*</span></label>
                                <input type="datetime-local" name="_gvseo_event_start" value="<?php echo esc_attr( $ev_start ); ?>"></div>
                            <div class="gvseo-mb-field"><label>End Date & Time</label>
                                <input type="datetime-local" name="_gvseo_event_end" value="<?php echo esc_attr( $ev_end ); ?>"></div>
                        </div>
                        <div class="gvseo-mb-field-row">
                            <div class="gvseo-mb-field"><label>Event Status <span class="gvseo-required">*</span></label>
                                <select name="_gvseo_event_status" class="gvseo-mb-select">
                                    <?php foreach ( [ 'EventScheduled', 'EventCancelled', 'EventPostponed', 'EventRescheduled', 'EventMovedOnline' ] as $es ) :
                                        echo '<option value="' . esc_attr( $es ) . '" ' . selected( $ev_status, $es, false ) . '>' . esc_html( $es ) . '</option>';
                                    endforeach; ?>
                                </select></div>
                            <div class="gvseo-mb-field"><label>Attendance Mode <span class="gvseo-required">*</span></label>
                                <select name="_gvseo_event_attend" class="gvseo-mb-select">
                                    <?php foreach ( [ 'OfflineEventAttendanceMode', 'OnlineEventAttendanceMode', 'MixedEventAttendanceMode' ] as $ea ) :
                                        echo '<option value="' . esc_attr( $ea ) . '" ' . selected( $ev_attend, $ea, false ) . '>' . esc_html( $ea ) . '</option>';
                                    endforeach; ?>
                                </select></div>
                        </div>
                        <div class="gvseo-mb-field-row">
                            <div class="gvseo-mb-field"><label>Venue Name</label><input type="text" name="_gvseo_venue" value="<?php echo esc_attr( $venue ); ?>"></div>
                            <div class="gvseo-mb-field"><label>Street Address</label><input type="text" name="_gvseo_venue_address" value="<?php echo esc_attr( $venue_addr ); ?>"></div>
                            <div class="gvseo-mb-field"><label>City</label><input type="text" name="_gvseo_venue_city" value="<?php echo esc_attr( $venue_city ); ?>"></div>
                            <div class="gvseo-mb-field"><label>Country (ISO)</label><input type="text" name="_gvseo_venue_country" value="<?php echo esc_attr( $venue_ctry ); ?>" maxlength="2"></div>
                        </div>
                    </div>

                    <!-- Recipe fields -->
                    <div class="gvseo-schema-group" data-for="Recipe">
                        <div class="gvseo-mb-section-label">Recipe Details <span class="gvseo-mb-muted">— featured image is required by Google</span></div>
                        <div class="gvseo-mb-field-row">
                            <div class="gvseo-mb-field"><label>Prep Time (ISO)</label><input type="text" name="_gvseo_prep_time" value="<?php echo esc_attr( $prep_time ); ?>" placeholder="PT15M"></div>
                            <div class="gvseo-mb-field"><label>Cook Time (ISO)</label><input type="text" name="_gvseo_cook_time" value="<?php echo esc_attr( $cook_time ); ?>" placeholder="PT30M"></div>
                            <div class="gvseo-mb-field"><label>Total Time (ISO)</label><input type="text" name="_gvseo_total_time_r" value="<?php echo esc_attr( $total_time ); ?>" placeholder="PT45M"></div>
                            <div class="gvseo-mb-field"><label>Yield</label><input type="text" name="_gvseo_recipe_yield" value="<?php echo esc_attr( $recipe_yield ); ?>" placeholder="4 servings"></div>
                        </div>
                        <div class="gvseo-mb-field">
                            <label>Ingredients <span class="gvseo-mb-muted">(one per line)</span></label>
                            <textarea name="_gvseo_ingredients" rows="5" placeholder="2 cups flour&#10;1 tsp salt&#10;3 large eggs"><?php echo esc_textarea( $ingredients ); ?></textarea>
                        </div>
                        <div class="gvseo-mb-field-row">
                            <div class="gvseo-mb-field"><label>Calories</label><input type="text" name="_gvseo_calories" value="<?php echo esc_attr( $calories ); ?>" placeholder="250"></div>
                            <div class="gvseo-mb-field"><label>Rating (1–5)</label><input type="number" name="_gvseo_rating" min="1" max="5" step="0.1" value="<?php echo esc_attr( $rating ); ?>"></div>
                            <div class="gvseo-mb-field"><label>Rating Count</label><input type="number" name="_gvseo_rating_count" min="1" value="<?php echo esc_attr( $rcount ); ?>"></div>
                        </div>
                    </div>

                    <!-- Custom JSON-LD -->
                    <div class="gvseo-schema-group" data-for="Custom">
                        <div class="gvseo-mb-section-label">Custom JSON-LD</div>
                        <textarea name="_gvseo_custom_json" rows="10" class="gvseo-code-area" placeholder='{"@context":"https://schema.org","@type":"Thing","name":"…"}'><?php echo esc_textarea( $custom_json ); ?></textarea>
                        <div id="gvseo-json-status" class="gvseo-json-status"></div>
                    </div>
                </div><!-- /#gvseo-schema-override -->
            </div><!-- /#gvseo-tab-schema -->

            <!-- ════════ SEO TAB ════════ -->
            <div class="gvseo-mb-panel" id="gvseo-tab-seo">

                <!-- Score widget -->
                <div class="gvseo-seo-score-row">
                    <div class="gvseo-seo-circle gvseo-seo-circle-<?php echo esc_attr( $score_label ); ?>">
                        <span><?php echo ( $seo_score !== '' && $seo_score !== false ) ? (int) $seo_score : '–'; ?></span>
                    </div>
                    <div class="gvseo-seo-score-meta">
                        <strong>SEO Score</strong>
                        <p><?php
                            if ( $seo_ts ) {
                                printf( 'Analyzed %s ago', human_time_diff( (int) $seo_ts ) );
                            } else {
                                echo 'Not yet analyzed';
                            }
                        ?></p>
                    </div>
                    <button type="button" class="gvseo-btn gvseo-btn-primary gvseo-btn-xs gvseo-mb-analyze"
                        data-post="<?php echo (int) $post->ID; ?>">
                        <?php echo ( $seo_score !== '' ) ? '↺ Re-analyze' : '🔍 Analyze Now'; ?>
                    </button>
                </div>

                <!-- Mini check results -->
                <div id="gvseo-mb-checks" class="gvseo-mb-checks">
                    <?php
                    $raw = get_post_meta( $post->ID, '_gvseo_seo_results', true );
                    $res = $raw ? json_decode( $raw, true ) : [];
                    if ( $res ) {
                        foreach ( $res as $id => $r ) {
                            $icon = $r['status'] === 'pass' ? '✓' : ( $r['status'] === 'warn' ? '⚠' : '✗' );
                            printf(
                                '<div class="gvseo-mc gvseo-mc-%s"><span class="gvseo-mc-icon">%s</span><span class="gvseo-mc-label">%s</span><span class="gvseo-mc-msg">%s</span></div>',
                                esc_attr( $r['status'] ), esc_html( $icon ),
                                esc_html( $r['label'] ), esc_html( $r['message'] )
                            );
                        }
                    } else {
                        echo '<p class="gvseo-mb-muted-p">Run analysis to see detailed checks.</p>';
                    }
                    ?>
                </div>

                <div class="gvseo-mb-sep"></div>

                <!-- Focus keyword -->
                <div class="gvseo-mb-field">
                    <label>Focus Keyword</label>
                    <input type="text" name="_gvseo_focus_kw" value="<?php echo esc_attr( $kw ); ?>" placeholder="e.g. best coffee recipes">
                </div>

                <!-- Secondary keywords -->
                <div class="gvseo-mb-field">
                    <label>Secondary Keywords <span class="gvseo-mb-badge-optional">Optional</span></label>
                    <textarea name="_gvseo_focus_kw_secondary" rows="3"
                        placeholder="<?php esc_attr_e( 'One keyword per line, e.g.:
coffee brewing guide
best coffee methods
how to brew coffee', 'grapevine-seo' ); ?>"><?php echo esc_textarea( $kw_sec ); ?></textarea>
                    <p class="gvseo-mb-hint">Additional keywords to track. Each will be checked for presence in content. One per line.</p>
                </div>

                <!-- Meta description -->
                <div class="gvseo-mb-field">
                    <label>Meta Description <span id="gvseo-desc-count" class="gvseo-char-counter">0 / 160</span></label>
                    <textarea id="gvseo-meta-desc" name="_gvseo_meta_desc" rows="3"
                        placeholder="120–160 character description for search results…"><?php echo esc_textarea( $meta_desc ); ?></textarea>
                    <div class="gvseo-desc-bar"><div id="gvseo-desc-fill" class="gvseo-desc-fill"></div></div>
                </div>

                <!-- OG fields -->
                <div class="gvseo-mb-field-row">
                    <div class="gvseo-mb-field">
                        <label>OG Title</label>
                        <input type="text" name="_gvseo_og_title" value="<?php echo esc_attr( $og_title ); ?>" placeholder="Defaults to page title">
                    </div>
                    <div class="gvseo-mb-field">
                        <label>OG Description</label>
                        <input type="text" name="_gvseo_og_desc" value="<?php echo esc_attr( $og_desc ); ?>" placeholder="Defaults to meta description">
                    </div>
                </div>

                <div class="gvseo-mb-field">
                    <label>Social Image URL</label>
                    <input type="url" name="_gvseo_og_image" value="<?php echo esc_url( $og_img ); ?>" placeholder="Defaults to featured image — recommended 1200×630">
                </div>

                <!-- No-index -->
                <div class="gvseo-mb-toggle-row">
                    <div>
                        <strong>No-Index this page</strong>
                        <p>Tells search engines not to index this URL.</p>
                    </div>
                    <label class="gvseo-toggle">
                        <input type="checkbox" name="_gvseo_noindex" value="1" <?php checked( $noindex, '1' ); ?>>
                        <span></span>
                    </label>
                </div>

                <a href="<?php echo esc_url( admin_url( 'admin.php?page=grapevine-seo-seo' ) ); ?>" class="gvseo-btn gvseo-btn-ghost gvseo-btn-xs" style="margin-top:12px;">📊 Open Full SEO Dashboard ↗</a>
            </div><!-- /#gvseo-tab-seo -->
        </div><!-- /.gvseo-mb -->
        <?php
    }

    /* ─── SAVE ─────────────────────────────────────────────────────── */
    public static function save( $post_id, $post ) {
        if ( ! isset( $_POST['gvseo_mb_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gvseo_mb_nonce'] ) ), 'gvseo_mb_save' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
        if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

        /* Mode & type */
        $mode = sanitize_key( $_POST['_gvseo_schema_mode'] ?? 'global' );
        update_post_meta( $post_id, '_gvseo_schema_mode', $mode );
        update_post_meta( $post_id, '_gvseo_schema_type', sanitize_text_field( $_POST['_gvseo_schema_type'] ?? 'Article' ) );

        /* Text fields */
        $texts = [
            '_gvseo_author_name', '_gvseo_author_url', '_gvseo_sku', '_gvseo_price', '_gvseo_currency',
            '_gvseo_availability', '_gvseo_rating', '_gvseo_rating_count',
            '_gvseo_event_start', '_gvseo_event_end', '_gvseo_event_status', '_gvseo_event_attend',
            '_gvseo_venue', '_gvseo_venue_address', '_gvseo_venue_city', '_gvseo_venue_country',
            '_gvseo_prep_time', '_gvseo_cook_time', '_gvseo_total_time', '_gvseo_recipe_yield', '_gvseo_calories',
            '_gvseo_focus_kw', '_gvseo_og_title', '_gvseo_og_desc', '_gvseo_og_image',
        ];
        foreach ( $texts as $k ) {
            update_post_meta( $post_id, $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ?? '' ) ) );
        }

        /* Textareas */
        update_post_meta( $post_id, '_gvseo_meta_desc',          sanitize_textarea_field( wp_unslash( $_POST['_gvseo_meta_desc'] ?? '' ) ) );
        update_post_meta( $post_id, '_gvseo_focus_kw_secondary', sanitize_textarea_field( wp_unslash( $_POST['_gvseo_focus_kw_secondary'] ?? '' ) ) );
        update_post_meta( $post_id, '_gvseo_ingredients',  sanitize_textarea_field( wp_unslash( $_POST['_gvseo_ingredients'] ?? '' ) ) );

        /* Checkbox */
        update_post_meta( $post_id, '_gvseo_noindex', isset( $_POST['_gvseo_noindex'] ) ? '1' : '0' );

        /* FAQ items */
        $qs = array_map( 'sanitize_text_field', wp_unslash( $_POST['_gvseo_faq_q'] ?? [] ) );
        $as = array_map( 'sanitize_textarea_field', wp_unslash( $_POST['_gvseo_faq_a'] ?? [] ) );
        $faq = [];
        foreach ( $qs as $i => $q ) {
            if ( trim( $q ) ) { $faq[] = [ 'q' => $q, 'a' => $as[ $i ] ?? '' ]; }
        }
        update_post_meta( $post_id, '_gvseo_faq_items', wp_slash( wp_json_encode( $faq ) ) );

        /* Steps */
        $sn = array_map( 'sanitize_text_field', wp_unslash( $_POST['_gvseo_step_name'] ?? [] ) );
        $st = array_map( 'sanitize_textarea_field', wp_unslash( $_POST['_gvseo_step_text'] ?? [] ) );
        $steps = [];
        foreach ( $sn as $i => $n ) {
            if ( trim( $n ) ) { $steps[] = [ 'name' => $n, 'text' => $st[ $i ] ?? '' ]; }
        }
        update_post_meta( $post_id, '_gvseo_steps', wp_slash( wp_json_encode( $steps ) ) );

        /* Custom JSON */
        $cj = wp_unslash( $_POST['_gvseo_custom_json'] ?? '' );
        if ( $cj && null !== json_decode( $cj ) ) {
            update_post_meta( $post_id, '_gvseo_custom_json', wp_slash( trim( $cj ) ) );
        }
    }
}

GVSEO_Meta_Box::init();
