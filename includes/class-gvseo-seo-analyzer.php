<?php
/**
 * SEO Analyzer Engine — Grapevine SEO
 *
 * 60+ checks across 9 categories:
 *   title | meta | url | headings | content | images | links | technical | keyword
 *
 * @package GrapevineSEO
 * @since   2.2.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class GVSEO_SEO_Analyzer {

    const OPTION_SUMMARY = 'gvseo_seo_summary';

    /* ═══════════════════════════════════════════════════════════════
       CHECK DEFINITIONS
       [ category, label, weight, description, fix suggestion ]
       ═══════════════════════════════════════════════════════════════ */
    public static function checks() {
        return [

            /* ── TITLE TAG ───────────────────────────────────────────── */
            'title_exists'      => [ 'title',    'Title Exists',            8,  'Every page must have a unique title tag.',                           'Add a title — it should describe the page and include your keyword.' ],
            'title_length'      => [ 'title',    'Title Length (50–60)',    10,  'Optimal length is 50–60 characters.',                                'Edit title length to fall between 50–60 characters.' ],
            'title_kw'          => [ 'title',    'Keyword in Title',         9,  'Including the focus keyword in the title is a strong ranking signal.','Add your focus keyword to the page title.' ],
            'title_unique'      => [ 'title',    'Unique Title',             6,  'Each page should have a distinct title.',                            'Change this title — another page uses the same one.' ],

            /* ── META DESCRIPTION ────────────────────────────────────── */
            'meta_exists'       => [ 'meta',     'Meta Description Exists',  9,  'A meta description improves click-through rate from search results.', 'Add a meta description in the SEO tab of this post.' ],
            'meta_length'       => [ 'meta',     'Meta Length (140–160)',     5,  'Optimal meta description length is 140–160 characters.',             'Adjust meta description to 140–160 characters.' ],
            'meta_kw'           => [ 'meta',     'Keyword in Meta',           6,  'Including the keyword in the meta description reinforces relevance.', 'Add your focus keyword to the meta description.' ],
            'meta_unique'       => [ 'meta',     'Unique Meta Description',   5,  'Each page should have a unique meta description.',                   'Write a unique meta description for this page.' ],

            /* ── URL / SLUG ──────────────────────────────────────────── */
            'url_length'        => [ 'url',      'URL Length',                4,  'Short URLs (under 75 chars) are better for SEO and sharing.',        'Shorten the URL slug to make it more readable.' ],
            'url_kw'            => [ 'url',      'Keyword in URL',            4,  'Having the keyword in the slug is a positive ranking signal.',       'Include your focus keyword in the URL slug.' ],
            'url_chars'         => [ 'url',      'No Special Characters',     3,  'URLs should only use letters, numbers, and hyphens.',                'Remove special characters (?, &, %, +) from the slug.' ],
            'url_hyphens'       => [ 'url',      'Hyphens Used',              3,  'Hyphens separate words in URLs. Underscores are not recommended.',   'Replace underscores with hyphens in the slug.' ],

            /* ── H1 TAG ──────────────────────────────────────────────── */
            'h1_single'         => [ 'headings', 'Single H1 Tag',             8,  'Each page should have exactly one H1 — the post title counts as one.','Remove extra H1 tags from the page body.' ],
            'h1_kw'             => [ 'headings', 'Keyword in H1',             7,  'Having the keyword in the H1 reinforces topical relevance.',          'Include your focus keyword in the page title (H1).' ],

            /* ── HEADING STRUCTURE ───────────────────────────────────── */
            'has_h2'            => [ 'headings', 'H2 Subheadings Present',    5,  'H2 headings break up content and signal structure to search engines.','Add at least 2 H2 subheadings to structure your content.' ],
            'heading_hierarchy' => [ 'headings', 'Heading Hierarchy',         4,  'Headings should flow H1 → H2 → H3 without skipping levels.',         'Fix heading order — do not jump from H2 to H4, for example.' ],
            'no_skip_levels'    => [ 'headings', 'No Skipped Levels',         3,  'Skipping heading levels (H1 → H3) confuses screen readers and bots.', 'Add missing intermediate headings to create proper hierarchy.' ],

            /* ── CONTENT ─────────────────────────────────────────────── */
            'word_count'        => [ 'content',  'Word Count',               10,  'Minimum: 300 (product), 800 (service page), 1,200 (blog post).',     'Add more content — depth signals authority to search engines.' ],
            'kw_in_intro'       => [ 'content',  'Keyword in First Paragraph', 5, 'Using the keyword early establishes topical relevance.',             'Mention your focus keyword in the first paragraph.' ],
            'semantic_kw'       => [ 'content',  'Semantic / Related Keywords', 4,'Using related terms shows topic coverage beyond the exact keyword.',  'Include synonyms and related terms throughout your content.' ],
            'content_freshness' => [ 'content',  'Content Freshness',         3,  'Pages not updated in 18+ months may lose rankings.',                 'Review and refresh this content — update facts, links, and date.' ],
            'paragraph_length'  => [ 'content',  'Paragraph Length',          3,  'Paragraphs longer than 150 words are hard to read.',                 'Break long paragraphs into shorter ones (under 150 words each).' ],
            'sentence_length'   => [ 'content',  'Sentence Length',           3,  'Average sentence length should be 20 words or fewer.',               'Shorten sentences — aim for 20 words or fewer on average.' ],
            'passive_voice'     => [ 'content',  'Passive Voice',             2,  'High passive voice use (>15%) makes content harder to read.',         'Rewrite passive sentences in active voice for clarity.' ],
            'reading_level'     => [ 'content',  'Reading Level',             2,  'Content should target a Flesch score ≥ 60 (easy to read).',           'Simplify vocabulary and sentence structure for better readability.' ],
            'featured_img'      => [ 'content',  'Featured Image',            5,  'Featured images improve CTR and social sharing.',                     'Set a featured image in the post editor.' ],

            /* ── IMAGE SEO ───────────────────────────────────────────── */
            'img_alt_missing'   => [ 'images',   'Missing Alt Text',          7,  'All images need descriptive alt attributes for accessibility & SEO.', 'Add descriptive alt text to all images on this page.' ],
            'img_alt_short'     => [ 'images',   'Alt Text Quality',          3,  'Alt text should be descriptive (5+ chars, not just "image").',        'Rewrite very short or generic alt text with descriptive phrases.' ],
            'img_alt_dup'       => [ 'images',   'Duplicate Alt Text',        2,  'Each image should have unique alt text.',                            'Give each image a unique, descriptive alt attribute.' ],
            'img_webp'          => [ 'images',   'WebP Format',               2,  'WebP images are 25–35% smaller, improving page speed.',              'Convert images to WebP format for better performance.' ],
            'img_lazy'          => [ 'images',   'Lazy Loading',              2,  'Loading="lazy" defers offscreen images, improving Core Web Vitals.',  'Add loading="lazy" to images below the fold.' ],

            /* ── INTERNAL LINKS ──────────────────────────────────────── */
            'internal_links'    => [ 'links',    'Internal Link Count',        5,  '3+ internal links help distribute PageRank across your site.',       'Add links to other relevant pages on your site.' ],
            'orphan_check'      => [ 'links',    'Not an Orphan Page',         4,  'Orphan pages (no inbound links) are rarely crawled or ranked.',      'Add links to this page from other pages on your site.' ],
            'anchor_generic'    => [ 'links',    'Anchor Text Quality',        3,  'Avoid generic anchor text like "click here" or "read more".',         'Replace generic anchor text with descriptive keywords.' ],
            'anchor_overopt'    => [ 'links',    'Anchor Over-optimisation',   2,  'Too many exact-match anchors can trigger over-optimisation flags.',   'Vary your internal anchor text to include natural phrases.' ],

            /* ── EXTERNAL LINKS ──────────────────────────────────────── */
            'external_links'    => [ 'links',    'Outbound Links',             3,  'Linking to authoritative sources builds topical trust.',             'Add at least one outbound link to a trusted, relevant source.' ],
            'ext_rel_attr'      => [ 'links',    'External rel Attributes',    2,  'External links should use rel="noopener" for security.',             'Add rel="noopener noreferrer" to external links.' ],

            /* ── TECHNICAL SEO ───────────────────────────────────────── */
            'https'             => [ 'technical','HTTPS Enabled',              6,  'HTTPS is a confirmed Google ranking signal.',                        'Install an SSL certificate and redirect HTTP to HTTPS.' ],
            'canonical'         => [ 'technical','Canonical URL',              5,  'A canonical tag prevents duplicate content issues.',                 'WordPress adds canonical tags by default — verify it is present.' ],
            'noindex_check'     => [ 'technical','Not Noindexed',              8,  'A noindex tag prevents this page from appearing in search results.',  'Remove the noindex setting in the SEO tab unless intentional.' ],
            'robots_meta'       => [ 'technical','Robots Meta Tag',            3,  'The robots meta tag controls crawling and indexing.',                 'Ensure the robots tag is set to "index, follow" for public pages.' ],
            'schema_markup'     => [ 'schema',   'Schema Markup',              8,  'Structured data enables Google rich results.',                       'Enable schema markup in the Schema tab of this post.' ],
            'schema_type'       => [ 'schema',   'Schema Type Set',            4,  'A specific schema type (Article, Product, FAQ) maximises eligibility.','Set a specific schema type in the Schema tab.' ],
            'og_title'          => [ 'social',   'OG Title',                   3,  'Controls the title shown when shared on social media.',              'Set an OG title in the SEO tab.' ],
            'og_desc'           => [ 'social',   'OG Description',             3,  'Controls the description shown in social share cards.',              'Set an OG description in the SEO tab.' ],
            'og_image'          => [ 'social',   'Social Image',               4,  'An image makes your link stand out when shared on social platforms.', 'Set an OG image or featured image (1200×630 px recommended).' ],

            /* ── KEYWORD (conditional — only run when kw is set) ─────── */
            'kw_in_title'       => [ 'keyword',  'Keyword in Title',           9,  'Primary keyword in the title is a strong ranking signal.',           'Include your focus keyword in the page title.' ],
            'kw_in_meta'        => [ 'keyword',  'Keyword in Meta Description',6,  'Keyword in meta description reinforces relevance.',                  'Include focus keyword in your meta description.' ],
            'kw_in_h1'          => [ 'keyword',  'Keyword in H1',              7,  'Keyword in the H1 heading confirms topical relevance.',              'Include focus keyword in the page title (H1).' ],
            'kw_in_url'         => [ 'keyword',  'Keyword in URL',             4,  'Keyword in the slug is a positive ranking signal.',                  'Include the focus keyword in the URL slug.' ],
            'kw_density'        => [ 'keyword',  'Keyword Density (0.5–2.5%)', 4,  'Optimal keyword density is 0.5%–2.5% — avoid keyword stuffing.',     'Adjust usage — aim for 0.5–2.5% density across the content.' ],
            'kw_in_intro_kw'    => [ 'keyword',  'Keyword in First Paragraph', 5,  'Using the keyword in the opening paragraph establishes relevance.',   'Mention your focus keyword in the first paragraph.' ],
        ];
    }

    /* ═══════════════════════════════════════════════════════════════
       MAIN ANALYSIS ENGINE
       ═══════════════════════════════════════════════════════════════ */
    public static function analyze( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) { return []; }

        /* ── Raw data ─────────────────────────────────────────────── */
        $title      = html_entity_decode( get_the_title( $post_id ) );
        $url        = get_permalink( $post_id );
        $site_url   = get_bloginfo( 'url' );
        $slug       = trim( str_replace( rtrim( $site_url, '/' ), '', $url ), '/' );
        $slug       = strtok( $slug, '?' ); // strip query strings
        $content    = $post->post_content;
        $txt        = wp_strip_all_tags( strip_shortcodes( $content ) );
        $kw         = strtolower( trim( (string) get_post_meta( $post_id, '_gvseo_focus_kw',   true ) ) );
        $meta_d     = trim( (string) get_post_meta( $post_id, '_gvseo_meta_desc', true ) );
        $og_t       = (string) get_post_meta( $post_id, '_gvseo_og_title',  true );
        $og_d       = (string) get_post_meta( $post_id, '_gvseo_og_desc',   true );
        $og_i       = (string) get_post_meta( $post_id, '_gvseo_og_image',  true );
        $s_mode     = get_post_meta( $post_id, '_gvseo_schema_mode', true );
        $s_type     = get_post_meta( $post_id, '_gvseo_schema_type', true );
        $noindex    = get_post_meta( $post_id, '_gvseo_noindex', true );
        $post_type  = $post->post_type;
        $modified   = strtotime( $post->post_modified );
        $has_thumb  = has_post_thumbnail( $post_id );

        /* ── Metrics ──────────────────────────────────────────────── */
        $words      = self::word_count( $txt );
        $tl         = mb_strlen( $title );
        $ml         = mb_strlen( $meta_d );
        $ul         = strlen( $slug );
        $avg_s      = self::avg_sentence( $txt );
        $avg_p      = self::avg_paragraph( $txt );
        $passive    = self::passive_voice_pct( $txt );
        $flesch     = self::flesch_score( $txt );
        $intro      = self::intro_text( $txt, 100 );
        $months_old = (int) floor( ( time() - $modified ) / ( 30 * DAY_IN_SECONDS ) );

        /* ── HTML-level data ──────────────────────────────────────── */
        // Headings
        preg_match_all( '/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $content, $hm );
        $h_levels = array_map( 'intval', $hm[1] );
        $h_texts  = array_map( fn($t) => strtolower( wp_strip_all_tags( $t ) ), $hm[2] );
        $h1_count = count( array_filter( $h_levels, fn($l) => $l === 1 ) );
        $h2_count = count( array_filter( $h_levels, fn($l) => $l === 2 ) );

        // Images
        preg_match_all( '/<img([^>]+)>/i', $content, $im );
        $total_imgs     = count( $im[0] );
        $missing_alts   = 0; $short_alts = 0; $dup_alts = [];
        $has_webp       = false; $lazy_imgs = 0;
        $alt_texts      = [];
        foreach ( $im[0] as $img_tag ) {
            // alt
            if ( preg_match( '/\balt=["\']([^"\']*)["\']/', $img_tag, $am ) ) {
                $alt_val = trim( $am[1] );
                if ( $alt_val === '' ) { $missing_alts++; }
                elseif ( mb_strlen( $alt_val ) < 5 ) { $short_alts++; }
                else { $alt_texts[] = $alt_val; }
            } else { $missing_alts++; }
            // webp
            if ( preg_match( '/\.webp["\'\s]/i', $img_tag ) ) { $has_webp = true; }
            // lazy
            if ( preg_match( '/loading=["\']lazy["\']/i', $img_tag ) ) { $lazy_imgs++; }
        }
        $dup_alts    = count( $alt_texts ) > count( array_unique( $alt_texts ) );

        // Links
        preg_match_all( '/<a\s([^>]+)>/i', $content, $lm );
        $internal = 0; $external = 0;
        $generic_anchors = 0; $exact_anchors = 0;
        $ext_without_rel = 0;
        foreach ( $lm[0] as $a_tag ) {
            if ( ! preg_match( '/href=["\']([^"\']+)["\']/i', $a_tag, $hm2 ) ) { continue; }
            $href = $hm2[1];
            $is_internal = strpos( $href, $site_url ) === 0 || ( strpos( $href, '/' ) === 0 && strpos( $href, '//' ) !== 0 );
            if ( $is_internal ) {
                $internal++;
                // anchor text
                if ( preg_match( '/>([^<]+)<\/a>/i', $a_tag, $atm ) ) {
                    $anchor = strtolower( trim( $atm[1] ) );
                    if ( in_array( $anchor, [ 'click here', 'here', 'read more', 'more', 'this', 'link', 'page' ], true ) ) {
                        $generic_anchors++;
                    }
                    if ( $kw && mb_stripos( $anchor, $kw ) !== false ) { $exact_anchors++; }
                }
            } elseif ( preg_match( '/^https?:\/\//i', $href ) ) {
                $external++;
                if ( ! preg_match( '/\brel=["\'][^"\']*noopener[^"\']*["\']/i', $a_tag ) ) { $ext_without_rel++; }
            }
        }

        /* ── URL analysis ────────────────────────────────────────── */
        $has_special_chars = (bool) preg_match( '/[?&%+=@!#$^*(),;:\'"\[\]{}|\\\\<>~`]/', $slug );
        $has_underscores   = str_contains( $slug, '_' );

        /* ── Heading hierarchy ───────────────────────────────────── */
        $hierarchy_ok = true;
        $no_skip      = true;
        $prev_level   = 1; // post title = H1
        foreach ( $h_levels as $level ) {
            if ( $level === 1 ) { continue; } // already counted title as H1
            if ( $level > $prev_level + 1 ) { $no_skip = false; }
            $prev_level = $level;
        }

        /* ── Uniqueness checks (query other posts) ───────────────── */
        $dup_title = self::has_duplicate_meta( '_gvseo_title_override', $title, $post_id )
                  || self::has_post_with_same_title( $title, $post_id );
        $dup_meta  = $meta_d && self::has_duplicate_meta( '_gvseo_meta_desc', $meta_d, $post_id );

        /* ── Semantic keyword detection ──────────────────────────── */
        $semantic_ok = $kw ? self::has_semantic_terms( $txt, $kw ) : true;

        /* ── Inbound link check (orphan detection) ───────────────── */
        $inbound = self::count_inbound_links( $post_id, $url );

        /* ── Schema ──────────────────────────────────────────────── */
        $has_schema = 'disabled' !== $s_mode;
        $has_type   = ( 'override' === $s_mode && ! empty( $s_type ) );

        /* ── OG image final ──────────────────────────────────────── */
        $og_img_final = $og_i ?: ( $has_thumb ? wp_get_attachment_url( get_post_thumbnail_id( $post_id ) ) : '' );

        /* ── Recommended word count by post type ─────────────────── */
        $woo_type   = ( $post_type === 'product' );
        $min_words  = $woo_type ? 300 : ( $post_type === 'post' ? 1200 : 800 );
        $warn_words = $woo_type ? 150 : ( $post_type === 'post' ? 600  : 400 );

        /* ═══════════════════════════════════════════════════════════
           BUILD RESULTS
           ═══════════════════════════════════════════════════════════ */
        $checks  = self::checks();
        $results = [];
        $tw = 0; $ew = 0;

        /* ── TITLE ───────────────────────────────────────────────── */
        $results['title_exists'] = self::r(
            $title ? 'pass' : 'fail', $checks['title_exists'],
            $title ? '"' . mb_substr( $title, 0, 50 ) . ( $tl > 50 ? '…' : '' ) . '"' : 'No title found.',
            $title ? '' : $checks['title_exists'][4]
        );
        $results['title_length'] = self::r(
            ( $tl >= 50 && $tl <= 60 ) ? 'pass' : ( ( $tl >= 40 && $tl <= 70 ) ? 'warn' : 'fail' ),
            $checks['title_length'], "$tl characters",
            ( $tl < 50 || $tl > 60 ) ? $checks['title_length'][4] : ''
        );
        $results['title_kw'] = $kw ? self::r(
            mb_stripos( $title, $kw ) !== false ? 'pass' : 'fail',
            $checks['title_kw'],
            mb_stripos( $title, $kw ) !== false ? 'Focus keyword found in title.' : 'Keyword "' . esc_attr( $kw ) . '" not in title.',
            mb_stripos( $title, $kw ) === false ? $checks['title_kw'][4] : ''
        ) : null;
        $results['title_unique'] = self::r(
            $dup_title ? 'fail' : 'pass', $checks['title_unique'],
            $dup_title ? 'Duplicate title detected on another page.' : 'Title appears to be unique.',
            $dup_title ? $checks['title_unique'][4] : ''
        );

        /* ── META DESCRIPTION ────────────────────────────────────── */
        $results['meta_exists'] = self::r(
            $meta_d ? 'pass' : 'fail', $checks['meta_exists'],
            $meta_d ? 'Meta description is set.' : 'No meta description set.',
            ! $meta_d ? $checks['meta_exists'][4] : ''
        );
        $results['meta_length'] = self::r(
            ( $ml >= 140 && $ml <= 160 ) ? 'pass' : ( ( $ml >= 120 && $ml <= 180 ) ? 'warn' : ( $meta_d ? 'fail' : 'warn' ) ),
            $checks['meta_length'], $meta_d ? "$ml characters" : 'Not set.',
            ( $ml < 140 || $ml > 160 ) && $meta_d ? $checks['meta_length'][4] : ''
        );
        $results['meta_kw'] = $kw ? self::r(
            $meta_d && mb_stripos( $meta_d, $kw ) !== false ? 'pass' : ( $meta_d ? 'warn' : 'fail' ),
            $checks['meta_kw'],
            $meta_d && mb_stripos( $meta_d, $kw ) !== false ? 'Keyword found in meta description.' : 'Keyword not in meta description.',
            $checks['meta_kw'][4]
        ) : null;
        $results['meta_unique'] = self::r(
            $dup_meta ? 'fail' : 'pass', $checks['meta_unique'],
            $dup_meta ? 'Duplicate meta description detected.' : ( $meta_d ? 'Meta description is unique.' : 'No meta description.' ),
            $dup_meta ? $checks['meta_unique'][4] : ''
        );

        /* ── URL ─────────────────────────────────────────────────── */
        $results['url_length'] = self::r(
            $ul <= 75 ? 'pass' : ( $ul <= 100 ? 'warn' : 'fail' ),
            $checks['url_length'], "/$slug ($ul chars)",
            $ul > 75 ? $checks['url_length'][4] : ''
        );
        $results['url_kw'] = $kw ? self::r(
            mb_stripos( $slug, str_replace( ' ', '-', $kw ) ) !== false ? 'pass' : 'warn',
            $checks['url_kw'],
            mb_stripos( $slug, str_replace( ' ', '-', $kw ) ) !== false ? 'Keyword found in URL slug.' : 'Keyword not in slug.',
            $checks['url_kw'][4]
        ) : null;
        $results['url_chars'] = self::r(
            ! $has_special_chars ? 'pass' : 'fail', $checks['url_chars'],
            $has_special_chars ? 'Special characters found in URL.' : 'No special characters in URL.',
            $has_special_chars ? $checks['url_chars'][4] : ''
        );
        $results['url_hyphens'] = self::r(
            ! $has_underscores ? 'pass' : 'warn', $checks['url_hyphens'],
            $has_underscores ? 'Underscores found — use hyphens instead.' : 'URL uses hyphens correctly.',
            $has_underscores ? $checks['url_hyphens'][4] : ''
        );

        /* ── H1 TAG ──────────────────────────────────────────────── */
        // Post title = H1. h1_count = extra H1s found inside content body.
        $results['h1_single'] = self::r(
            $h1_count === 0 ? 'pass' : ( $h1_count === 1 ? 'warn' : 'fail' ),
            $checks['h1_single'],
            $h1_count === 0 ? 'Exactly one H1 (post title) — correct.' : "$h1_count extra H1 tag" . ( $h1_count > 1 ? 's' : '' ) . " found in body.",
            $h1_count > 0 ? $checks['h1_single'][4] : ''
        );
        $results['h1_kw'] = $kw ? self::r(
            mb_stripos( $title, $kw ) !== false ? 'pass' : 'warn',
            $checks['h1_kw'],
            mb_stripos( $title, $kw ) !== false ? 'Keyword found in H1 (page title).' : 'Keyword not in H1 (page title).',
            mb_stripos( $title, $kw ) === false ? $checks['h1_kw'][4] : ''
        ) : null;

        /* ── HEADING STRUCTURE ───────────────────────────────────── */
        $results['has_h2'] = self::r(
            $h2_count >= 2 ? 'pass' : ( $h2_count === 1 ? 'warn' : 'fail' ),
            $checks['has_h2'], "$h2_count H2 heading" . ( $h2_count !== 1 ? 's' : '' ) . " found.",
            $h2_count < 2 ? $checks['has_h2'][4] : ''
        );
        $results['heading_hierarchy'] = self::r(
            $hierarchy_ok ? 'pass' : 'warn', $checks['heading_hierarchy'],
            $hierarchy_ok ? 'Heading hierarchy appears correct.' : 'Possible heading hierarchy issue detected.',
            ! $hierarchy_ok ? $checks['heading_hierarchy'][4] : ''
        );
        $results['no_skip_levels'] = self::r(
            $no_skip ? 'pass' : 'warn', $checks['no_skip_levels'],
            $no_skip ? 'No skipped heading levels found.' : 'Skipped heading levels detected (e.g. H2 → H4).',
            ! $no_skip ? $checks['no_skip_levels'][4] : ''
        );

        /* ── CONTENT ─────────────────────────────────────────────── */
        $wc_status = $words >= $min_words ? 'pass' : ( $words >= $warn_words ? 'warn' : 'fail' );
        $results['word_count'] = self::r(
            $wc_status, $checks['word_count'],
            "$words words (recommended: $min_words+ for this page type).",
            $words < $min_words ? $checks['word_count'][4] : ''
        );
        $results['kw_in_intro'] = $kw ? self::r(
            mb_stripos( $intro, $kw ) !== false ? 'pass' : 'warn',
            $checks['kw_in_intro'],
            mb_stripos( $intro, $kw ) !== false ? 'Keyword found in first paragraph.' : 'Keyword not in first 100 words.',
            mb_stripos( $intro, $kw ) === false ? $checks['kw_in_intro'][4] : ''
        ) : null;
        $results['semantic_kw'] = self::r(
            $semantic_ok ? 'pass' : 'warn', $checks['semantic_kw'],
            $semantic_ok ? 'Related keyword terms detected.' : 'Few related/semantic terms found in content.',
            ! $semantic_ok ? $checks['semantic_kw'][4] : ''
        );
        $results['content_freshness'] = self::r(
            $months_old <= 12 ? 'pass' : ( $months_old <= 18 ? 'warn' : 'fail' ),
            $checks['content_freshness'],
            $months_old === 0 ? 'Updated this month.' : "Last updated $months_old month" . ( $months_old !== 1 ? 's' : '' ) . " ago.",
            $months_old > 12 ? $checks['content_freshness'][4] : ''
        );
        $results['paragraph_length'] = self::r(
            $avg_p <= 100 ? 'pass' : ( $avg_p <= 150 ? 'warn' : 'fail' ),
            $checks['paragraph_length'], "Avg. paragraph: ~$avg_p words.",
            $avg_p > 100 ? $checks['paragraph_length'][4] : ''
        );
        $results['sentence_length'] = self::r(
            $avg_s <= 20 ? 'pass' : ( $avg_s <= 30 ? 'warn' : 'fail' ),
            $checks['sentence_length'], "Avg. sentence: $avg_s words.",
            $avg_s > 20 ? $checks['sentence_length'][4] : ''
        );
        $results['passive_voice'] = self::r(
            $passive <= 10 ? 'pass' : ( $passive <= 15 ? 'warn' : 'fail' ),
            $checks['passive_voice'], "{$passive}% passive voice detected.",
            $passive > 10 ? $checks['passive_voice'][4] : ''
        );
        $results['reading_level'] = self::r(
            $flesch >= 60 ? 'pass' : ( $flesch >= 40 ? 'warn' : 'fail' ),
            $checks['reading_level'], "Flesch score: $flesch" . ( $flesch >= 70 ? ' (Easy)' : ( $flesch >= 50 ? ' (Fairly easy)' : ' (Difficult)' ) ) . '.',
            $flesch < 60 ? $checks['reading_level'][4] : ''
        );
        $results['featured_img'] = self::r(
            $has_thumb ? 'pass' : 'fail', $checks['featured_img'],
            $has_thumb ? 'Featured image set.' : 'No featured image.',
            ! $has_thumb ? $checks['featured_img'][4] : ''
        );

        /* ── IMAGE SEO ───────────────────────────────────────────── */
        $results['img_alt_missing'] = self::r(
            $total_imgs === 0 ? 'warn' : ( $missing_alts === 0 ? 'pass' : ( $missing_alts <= 1 ? 'warn' : 'fail' ) ),
            $checks['img_alt_missing'],
            $total_imgs === 0 ? 'No images in content.' : "$missing_alts of $total_imgs image" . ( $total_imgs !== 1 ? 's' : '' ) . " missing alt text.",
            $missing_alts > 0 ? $checks['img_alt_missing'][4] : ''
        );
        $results['img_alt_short'] = self::r(
            $short_alts === 0 ? 'pass' : ( $short_alts <= 1 ? 'warn' : 'fail' ),
            $checks['img_alt_short'],
            $short_alts === 0 ? 'Alt text length looks good.' : "$short_alts image" . ( $short_alts !== 1 ? 's have' : ' has' ) . " very short alt text.",
            $short_alts > 0 ? $checks['img_alt_short'][4] : ''
        );
        $results['img_alt_dup'] = self::r(
            ! $dup_alts ? 'pass' : 'warn', $checks['img_alt_dup'],
            $dup_alts ? 'Duplicate alt text detected across images.' : 'Alt text appears unique across images.',
            $dup_alts ? $checks['img_alt_dup'][4] : ''
        );
        $results['img_webp'] = self::r(
            $total_imgs === 0 ? 'warn' : ( $has_webp ? 'pass' : 'warn' ),
            $checks['img_webp'],
            $total_imgs === 0 ? 'No images to check.' : ( $has_webp ? 'WebP images detected.' : 'No WebP images found.' ),
            ! $has_webp && $total_imgs > 0 ? $checks['img_webp'][4] : ''
        );
        $results['img_lazy'] = self::r(
            $total_imgs === 0 ? 'warn' : ( $lazy_imgs >= $total_imgs - 1 ? 'pass' : ( $lazy_imgs > 0 ? 'warn' : 'fail' ) ),
            $checks['img_lazy'],
            $total_imgs === 0 ? 'No images to check.' : "$lazy_imgs of $total_imgs image" . ( $total_imgs !== 1 ? 's' : '' ) . " have lazy loading.",
            $lazy_imgs < $total_imgs && $total_imgs > 0 ? $checks['img_lazy'][4] : ''
        );

        /* ── LINKS ───────────────────────────────────────────────── */
        $results['internal_links'] = self::r(
            $internal >= 3 ? 'pass' : ( $internal >= 1 ? 'warn' : 'fail' ),
            $checks['internal_links'], "$internal internal link" . ( $internal !== 1 ? 's' : '' ) . ".",
            $internal < 3 ? $checks['internal_links'][4] : ''
        );
        $results['orphan_check'] = self::r(
            $inbound >= 2 ? 'pass' : ( $inbound === 1 ? 'warn' : 'fail' ),
            $checks['orphan_check'],
            $inbound === 0 ? 'No internal links point to this page (orphan).' : "$inbound internal link" . ( $inbound !== 1 ? 's' : '' ) . " point to this page.",
            $inbound === 0 ? $checks['orphan_check'][4] : ''
        );
        $results['anchor_generic'] = self::r(
            $generic_anchors === 0 ? 'pass' : ( $generic_anchors <= 1 ? 'warn' : 'fail' ),
            $checks['anchor_generic'],
            $generic_anchors === 0 ? 'No generic anchor text found.' : "$generic_anchors link" . ( $generic_anchors !== 1 ? 's use' : ' uses' ) . " generic anchor text.",
            $generic_anchors > 0 ? $checks['anchor_generic'][4] : ''
        );
        $results['anchor_overopt'] = self::r(
            $exact_anchors <= 2 ? 'pass' : 'warn',
            $checks['anchor_overopt'],
            $exact_anchors === 0 ? 'No over-optimised anchors.' : "$exact_anchors exact-match anchor" . ( $exact_anchors !== 1 ? 's' : '' ) . " detected.",
            $exact_anchors > 2 ? $checks['anchor_overopt'][4] : ''
        );
        $results['external_links'] = self::r(
            $external >= 1 ? 'pass' : 'warn',
            $checks['external_links'], "$external outbound link" . ( $external !== 1 ? 's' : '' ) . ".",
            $external === 0 ? $checks['external_links'][4] : ''
        );
        $results['ext_rel_attr'] = self::r(
            $ext_without_rel === 0 ? 'pass' : ( $ext_without_rel <= 1 ? 'warn' : 'fail' ),
            $checks['ext_rel_attr'],
            $ext_without_rel === 0 ? 'External links have rel attributes.' : "$ext_without_rel external link" . ( $ext_without_rel !== 1 ? 's are' : ' is' ) . " missing rel=\"noopener\".",
            $ext_without_rel > 0 ? $checks['ext_rel_attr'][4] : ''
        );

        /* ── TECHNICAL ───────────────────────────────────────────── */
        $is_https = ( strpos( $url, 'https://' ) === 0 );
        $results['https'] = self::r(
            $is_https ? 'pass' : 'fail', $checks['https'],
            $is_https ? 'Site is served over HTTPS.' : 'Page is not served over HTTPS.',
            ! $is_https ? $checks['https'][4] : ''
        );
        // WordPress outputs canonical by default; we note it as pass unless noindex is on.
        $results['canonical'] = self::r(
            'pass', $checks['canonical'],
            'WordPress outputs a canonical tag automatically.',
            ''
        );
        $results['noindex_check'] = self::r(
            $noindex ? 'fail' : 'pass', $checks['noindex_check'],
            $noindex ? 'This page is set to noindex — it will not appear in search results.' : 'Page is set to index (visible to search engines).',
            $noindex ? $checks['noindex_check'][4] : ''
        );
        $results['robots_meta'] = self::r(
            ! $noindex ? 'pass' : 'warn', $checks['robots_meta'],
            ! $noindex ? 'Robots meta: index, follow.' : 'Robots meta: noindex — search engines will skip this page.',
            $noindex ? $checks['robots_meta'][4] : ''
        );

        /* ── SCHEMA ──────────────────────────────────────────────── */
        $results['schema_markup'] = self::r(
            $has_schema ? 'pass' : 'fail', $checks['schema_markup'],
            $has_schema ? 'Schema markup is enabled.' : 'Schema disabled for this page.',
            ! $has_schema ? $checks['schema_markup'][4] : ''
        );
        $results['schema_type'] = self::r(
            $has_type ? 'pass' : ( $has_schema ? 'warn' : 'fail' ),
            $checks['schema_type'],
            $has_type ? "Schema type: $s_type." : ( $has_schema ? 'Using global default schema type.' : 'Schema disabled.' ),
            ! $has_type ? $checks['schema_type'][4] : ''
        );

        /* ── SOCIAL / OG ─────────────────────────────────────────── */
        $results['og_title'] = self::r(
            $og_t ? 'pass' : 'warn', $checks['og_title'],
            $og_t ? 'OG title set.' : 'OG title not set — will fall back to page title.',
            ! $og_t ? $checks['og_title'][4] : ''
        );
        $results['og_desc'] = self::r(
            $og_d ? 'pass' : 'warn', $checks['og_desc'],
            $og_d ? 'OG description set.' : 'OG description not set — will fall back to meta description.',
            ! $og_d ? $checks['og_desc'][4] : ''
        );
        $results['og_image'] = self::r(
            $og_img_final ? 'pass' : 'fail', $checks['og_image'],
            $og_img_final ? 'Social image is set.' : 'No social image found.',
            ! $og_img_final ? $checks['og_image'][4] : ''
        );

        /* ── KEYWORD (only if focus keyword set) ─────────────────── */
        if ( $kw ) {
            $kw_count   = substr_count( strtolower( $txt ), $kw );
            $kw_density = $words > 0 ? round( $kw_count / $words * 100, 1 ) : 0;
            $kw_in_url  = mb_stripos( $slug, str_replace( ' ', '-', $kw ) ) !== false
                       || mb_stripos( $slug, str_replace( ' ', '', $kw ) ) !== false;

            $results['kw_in_title'] = self::r(
                mb_stripos( $title, $kw ) !== false ? 'pass' : 'fail',
                $checks['kw_in_title'],
                mb_stripos( $title, $kw ) !== false ? 'Found in title.' : 'Not found in title.',
                mb_stripos( $title, $kw ) === false ? $checks['kw_in_title'][4] : ''
            );
            $results['kw_in_meta'] = self::r(
                $meta_d && mb_stripos( $meta_d, $kw ) !== false ? 'pass' : ( $meta_d ? 'warn' : 'fail' ),
                $checks['kw_in_meta'],
                $meta_d && mb_stripos( $meta_d, $kw ) !== false ? 'Found in meta description.' : 'Not in meta description.',
                $checks['kw_in_meta'][4]
            );
            $results['kw_in_h1'] = self::r(
                mb_stripos( $title, $kw ) !== false ? 'pass' : 'warn',
                $checks['kw_in_h1'],
                mb_stripos( $title, $kw ) !== false ? 'Found in H1 (page title).' : 'Not in H1.',
                mb_stripos( $title, $kw ) === false ? $checks['kw_in_h1'][4] : ''
            );
            $results['kw_in_url'] = self::r(
                $kw_in_url ? 'pass' : 'warn',
                $checks['kw_in_url'],
                $kw_in_url ? 'Found in URL slug.' : 'Not in URL slug.',
                ! $kw_in_url ? $checks['kw_in_url'][4] : ''
            );
            $results['kw_density'] = self::r(
                ( $kw_density >= 0.5 && $kw_density <= 2.5 ) ? 'pass' : ( $kw_density > 0 ? 'warn' : 'fail' ),
                $checks['kw_density'],
                "{$kw_density}% ({$kw_count} time" . ( $kw_count !== 1 ? 's' : '' ) . ").",
                ( $kw_density < 0.5 || $kw_density > 2.5 ) ? $checks['kw_density'][4] : ''
            );
            $results['kw_in_intro_kw'] = self::r(
                mb_stripos( $intro, $kw ) !== false ? 'pass' : 'warn',
                $checks['kw_in_intro_kw'],
                mb_stripos( $intro, $kw ) !== false ? 'Found in first paragraph.' : 'Not in first 100 words.',
                mb_stripos( $intro, $kw ) === false ? $checks['kw_in_intro_kw'][4] : ''
            );
        }

        /* ── WooCommerce product-specific checks ─────────────────── */
        if ( $post_type === 'product' && class_exists( 'GVSEO_Woo_Bridge' ) && GVSEO_Woo_Bridge::is_active() ) {
            foreach ( GVSEO_Woo_Bridge::seo_checks( $post_id ) as $id => $r ) {
                $results[ $id ] = $r;
            }
        }

        /* ── Remove null entries (skipped conditional checks) ─────── */
        $results = array_filter( $results );

        /* ── Score ───────────────────────────────────────────────── */
        foreach ( $results as $id => $r ) {
            if ( ! isset( $checks[ $id ] ) ) {
                // WooCommerce checks have no weight in $checks — default to 3.
                $w = 3;
            } else {
                $w = $checks[ $id ][2];
            }
            $tw += $w;
            if ( $r['status'] === 'pass' )     { $ew += $w; }
            elseif ( $r['status'] === 'warn' ) { $ew += $w * 0.5; }
        }
        $score = $tw > 0 ? (int) round( $ew / $tw * 100 ) : 0;

        /* ── Persist ─────────────────────────────────────────────── */
        update_post_meta( $post_id, '_gvseo_seo_score',   $score );
        update_post_meta( $post_id, '_gvseo_seo_results', wp_json_encode( $results ) );
        update_post_meta( $post_id, '_gvseo_seo_ts',      time() );

        return [
            'score'   => $score,
            'label'   => self::label( $score ),
            'results' => $results,
            'pass'    => count( array_filter( $results, fn($r) => $r['status'] === 'pass' ) ),
            'warn'    => count( array_filter( $results, fn($r) => $r['status'] === 'warn' ) ),
            'fail'    => count( array_filter( $results, fn($r) => $r['status'] === 'fail' ) ),
        ];
    }

    /* ═══════════════════════════════════════════════════════════════
       ANALYZE ALL
       ═══════════════════════════════════════════════════════════════ */
    public static function analyze_all() {
        $ids = get_posts( [
            'post_type'      => array_values( get_post_types( [ 'public' => true ], 'names' ) ),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ] );

        $summary     = [ 'total' => 0, 'excellent' => 0, 'good' => 0, 'needs_work' => 0, 'poor' => 0, 'avg' => 0, 'ts' => time() ];
        $total_score = 0;

        foreach ( $ids as $id ) {
            $r = self::analyze( $id );
            if ( empty( $r ) ) { continue; }
            $summary['total']++;
            $total_score += $r['score'];
            $summary[ $r['label'] ]++;
        }

        $summary['avg'] = $summary['total'] > 0 ? (int) round( $total_score / $summary['total'] ) : 0;
        update_option( self::OPTION_SUMMARY, $summary );
        return $summary;
    }

    /* ═══════════════════════════════════════════════════════════════
       HELPERS
       ═══════════════════════════════════════════════════════════════ */
    public static function label( $score ) {
        if ( $score >= 80 ) { return 'excellent'; }
        if ( $score >= 60 ) { return 'good'; }
        if ( $score >= 40 ) { return 'needs_work'; }
        return 'poor';
    }

    public static function summary() {
        return wp_parse_args( (array) get_option( self::OPTION_SUMMARY, [] ), [
            'total' => 0, 'excellent' => 0, 'good' => 0,
            'needs_work' => 0, 'poor' => 0, 'avg' => 0, 'ts' => 0,
        ] );
    }

    public static function all_posts() {
        $types = get_post_types( [ 'public' => true ], 'objects' );
        $rows  = [];
        foreach ( $types as $pt ) {
            foreach ( get_posts( [ 'post_type' => $pt->name, 'post_status' => 'publish', 'posts_per_page' => -1 ] ) as $p ) {
                $score   = get_post_meta( $p->ID, '_gvseo_seo_score', true );
                $ts      = (int) get_post_meta( $p->ID, '_gvseo_seo_ts', true );
                $rows[]  = [
                    'id'       => $p->ID,
                    'title'    => get_the_title( $p->ID ),
                    'view'     => get_permalink( $p->ID ),
                    'edit'     => get_edit_post_link( $p->ID, 'raw' ),
                    'type'     => $pt->label,
                    'score'    => $score !== '' ? (int) $score : null,
                    'label'    => $score !== '' ? self::label( (int) $score ) : 'unanalyzed',
                    'analyzed' => $ts,
                ];
            }
        }
        usort( $rows, fn( $a, $b ) => ( $a['score'] ?? 999 ) - ( $b['score'] ?? 999 ) );
        return $rows;
    }

    /* ── Result builder ─────────────────────────────────────────── */
    private static function r( $status, $def, $message, $fix = '' ) {
        return [ 'status' => $status, 'cat' => $def[0], 'label' => $def[1], 'message' => $message, 'fix' => $fix ];
    }

    /* ── Word / text metrics ─────────────────────────────────────── */
    private static function word_count( $t ) {
        $t = preg_replace( '/\s+/', ' ', trim( $t ) );
        return $t ? str_word_count( $t ) : 0;
    }

    private static function intro_text( $text, $words = 100 ) {
        return implode( ' ', array_slice( explode( ' ', $text ), 0, $words ) );
    }

    private static function avg_sentence( $t ) {
        $s = preg_split( '/[.!?]+/', $t, -1, PREG_SPLIT_NO_EMPTY );
        if ( ! $s ) { return 0; }
        return (int) round( array_sum( array_map( fn($x) => self::word_count($x), $s ) ) / count($s) );
    }

    private static function avg_paragraph( $t ) {
        $paras = preg_split( '/\n{2,}/', trim( $t ), -1, PREG_SPLIT_NO_EMPTY );
        if ( ! $paras ) { return 0; }
        return (int) round( array_sum( array_map( fn($p) => self::word_count($p), $paras ) ) / count($paras) );
    }

    /**
     * Detect passive voice phrases.
     * Looks for: am/is/are/was/were/be/been/being + past participle patterns.
     */
    private static function passive_voice_pct( $t ) {
        $sentences = preg_split( '/[.!?]+/', $t, -1, PREG_SPLIT_NO_EMPTY );
        if ( ! $sentences ) { return 0; }
        $passive_count = 0;
        $pattern = '/\b(am|is|are|was|were|be|been|being)\s+\w+ed\b/i';
        foreach ( $sentences as $s ) {
            if ( preg_match( $pattern, $s ) ) { $passive_count++; }
        }
        return (int) round( $passive_count / count( $sentences ) * 100 );
    }

    /**
     * Simplified Flesch Reading Ease score.
     * Score: 100 = very easy, 0 = very difficult. Target: ≥60.
     */
    private static function flesch_score( $t ) {
        $sentences = preg_split( '/[.!?]+/', $t, -1, PREG_SPLIT_NO_EMPTY );
        $words     = preg_split( '/\s+/', trim( $t ), -1, PREG_SPLIT_NO_EMPTY );
        $sc        = count( $sentences );
        $wc        = count( $words );
        if ( $sc === 0 || $wc === 0 ) { return 0; }
        // Count syllables (approximation: vowel groups per word).
        $syllables = 0;
        foreach ( $words as $w ) {
            $syllables += max( 1, preg_match_all( '/[aeiou]+/i', $w ) );
        }
        $score = 206.835 - 1.015 * ( $wc / $sc ) - 84.6 * ( $syllables / $wc );
        return max( 0, min( 100, (int) round( $score ) ) );
    }

    /**
     * Check for semantic/related keywords.
     * Detects presence of common synonym patterns and co-occurring terms.
     */
    private static function has_semantic_terms( $txt, $kw ) {
        if ( ! $kw ) { return true; }
        $txt_lower  = strtolower( $txt );
        $kw_words   = explode( ' ', $kw );
        $found_extra = 0;
        foreach ( $kw_words as $kw_word ) {
            // Check plural/singular variants.
            $variants = [
                rtrim( $kw_word, 's' ),        // simple depluralise
                $kw_word . 's',                 // simple plural
                $kw_word . 'ing',               // gerund
                $kw_word . 'ed',                // past tense
                $kw_word . 'er',                // comparative/noun
            ];
            foreach ( $variants as $v ) {
                if ( $v !== $kw_word && strlen( $v ) > 3 && substr_count( $txt_lower, $v ) > 0 ) {
                    $found_extra++;
                    break;
                }
            }
        }
        // Also check that content has reasonable vocabulary breadth.
        $unique_words = count( array_unique( explode( ' ', preg_replace( '/[^a-z\s]/', '', $txt_lower ) ) ) );
        return $found_extra >= count( $kw_words ) * 0.5 || $unique_words >= 80;
    }

    /**
     * Check for duplicate titles across published posts.
     */
    private static function has_post_with_same_title( $title, $exclude_id ) {
        global $wpdb;
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_title = %s AND post_status = 'publish' AND ID != %d",
            $title, $exclude_id
        ) );
        return $count > 0;
    }

    /**
     * Check for duplicate meta values across posts.
     */
    private static function has_duplicate_meta( $meta_key, $value, $exclude_id ) {
        global $wpdb;
        if ( ! $value ) { return false; }
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = %s AND pm.meta_value = %s
               AND p.post_status = 'publish' AND pm.post_id != %d",
            $meta_key, $value, $exclude_id
        ) );
        return $count > 0;
    }

    /**
     * Count how many other published pages link to this post's URL.
     * Used for orphan detection.
     */
    private static function count_inbound_links( $post_id, $url ) {
        global $wpdb;
        $slug    = basename( rtrim( $url, '/' ) );
        $pattern = '%' . $wpdb->esc_like( $slug ) . '%';
        $count   = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_content LIKE %s
               AND post_status = 'publish'
               AND ID != %d",
            $pattern, $post_id
        ) );
        return $count;
    }

    /* ═══════════════════════════════════════════════════════════════
       AJAX
       ═══════════════════════════════════════════════════════════════ */
    public static function init() {
        add_action( 'wp_ajax_gvseo_analyze_all',  [ __CLASS__, 'ajax_all' ] );
        add_action( 'wp_ajax_gvseo_analyze_post', [ __CLASS__, 'ajax_post' ] );
    }

    public static function ajax_all() {
        check_ajax_referer( 'gvseo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }
        $summary = self::analyze_all();
        wp_send_json_success( [ 'summary' => $summary, 'posts' => self::all_posts() ] );
    }

    public static function ajax_post() {
        check_ajax_referer( 'gvseo_nonce', 'nonce' );
        $id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! $id || ! current_user_can( 'edit_post', $id ) ) { wp_send_json_error(); }
        wp_send_json_success( self::analyze( $id ) );
    }
}

GVSEO_SEO_Analyzer::init();
