<?php
/**
 * XML Sitemap — Grapevine SEO
 *
 * Generates a sitemap index + per-post-type sitemaps at:
 *   /sitemap.xml              → index listing all sub-sitemaps
 *   /sitemap-posts.xml        → blog posts
 *   /sitemap-pages.xml        → pages
 *   /sitemap-{post_type}.xml  → any other public post type
 *
 * Respects GVSEO_Settings exclusion rules.
 * Paginates at 500 URLs per sitemap to keep file sizes manageable.
 *
 * @package GrapevineSEO
 * @since   2.4.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class GVSEO_Sitemap {

    const PER_PAGE = 500;

    public static function init() {
        add_action( 'init',              [ __CLASS__, 'add_rewrite_rules' ] );
        add_action( 'init',              [ __CLASS__, 'maybe_flush_rewrite_rules' ] );
        add_filter( 'query_vars',        [ __CLASS__, 'add_query_vars' ] );
        add_action( 'template_redirect', [ __CLASS__, 'handle_request' ] );
        add_action( 'save_post',         [ __CLASS__, 'flush_on_save' ] );
        add_filter( 'robots_txt',        [ __CLASS__, 'add_to_robots' ], 10, 2 );
    }

    /**
     * Flush rewrite rules on the next 'init' if a migration or activation
     * set the flag. Safe to call here because $wp_rewrite is ready.
     */
    public static function maybe_flush_rewrite_rules() {
        if ( get_option( 'gvseo_flush_rewrite_rules' ) ) {
            delete_option( 'gvseo_flush_rewrite_rules' );
            flush_rewrite_rules();
        }
    }

    /* ── Rewrite rules ────────────────────────────────────────────── */
    public static function add_rewrite_rules() {
        // Sitemap index
        add_rewrite_rule( '^sitemap\.xml$', 'index.php?gvseo_sitemap=index', 'top' );
        // Sub-sitemaps with optional page number: sitemap-posts.xml, sitemap-pages-2.xml
        add_rewrite_rule(
            '^sitemap-([a-z0-9_-]+?)(-(\d+))?\.xml$',
            'index.php?gvseo_sitemap=$matches[1]&gvseo_sitemap_page=$matches[3]',
            'top'
        );
    }

    public static function add_query_vars( $vars ) {
        $vars[] = 'gvseo_sitemap';
        $vars[] = 'gvseo_sitemap_page';
        return $vars;
    }

    /* ── Route requests ───────────────────────────────────────────── */
    public static function handle_request() {
        $sitemap = get_query_var( 'gvseo_sitemap' );
        if ( ! $sitemap ) { return; }

        $page = max( 1, (int) get_query_var( 'gvseo_sitemap_page', 1 ) );

        header( 'Content-Type: text/xml; charset=UTF-8' );
        header( 'X-Robots-Tag: noindex, follow' );

        if ( $sitemap === 'index' ) {
            echo self::build_index(); // phpcs:ignore
        } else {
            echo self::build_post_type_sitemap( $sitemap, $page ); // phpcs:ignore
        }
        exit;
    }

    /* ── Sitemap Index ─────────────────────────────────────────────── */
    private static function build_index() {
        $excluded = GVSEO_Settings::get_excluded_types();
        $types    = get_post_types( [ 'public' => true ], 'names' );
        $entries  = [];

        foreach ( $types as $type ) {
            if ( in_array( $type, $excluded, true ) ) { continue; }
            $count = (int) wp_count_posts( $type )->publish;
            if ( $count === 0 ) { continue; }

            $pages = max( 1, (int) ceil( $count / self::PER_PAGE ) );
            for ( $p = 1; $p <= $pages; $p++ ) {
                $slug     = self::type_to_slug( $type );
                $filename = $p > 1 ? "sitemap-{$slug}-{$p}.xml" : "sitemap-{$slug}.xml";
                $entries[] = [
                    'loc'     => home_url( '/' ) . $filename,
                    'lastmod' => self::last_modified( $type ),
                ];
            }
        }

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . esc_url( GVSEO_URL . 'admin/sitemap.xsl' ) . '"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ( $entries as $entry ) {
            $xml .= "\t<sitemap>\n";
            $xml .= "\t\t<loc>" . esc_url( $entry['loc'] ) . "</loc>\n";
            $xml .= "\t\t<lastmod>" . esc_html( $entry['lastmod'] ) . "</lastmod>\n";
            $xml .= "\t</sitemap>\n";
        }
        $xml .= '</sitemapindex>';
        return $xml;
    }

    /* ── Post-type sub-sitemap ─────────────────────────────────────── */
    private static function build_post_type_sitemap( $slug, $page ) {
        $type     = self::slug_to_type( $slug );
        $excluded = GVSEO_Settings::get_excluded_types();

        if ( ! $type || in_array( $type, $excluded, true ) ) {
            status_header( 404 );
            return '<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
        }

        $posts = get_posts( [
            'post_type'      => $type,
            'post_status'    => 'publish',
            'posts_per_page' => self::PER_PAGE,
            'paged'          => $page,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            'meta_query'     => [
                // Skip manually noindexed posts.
                [
                    'relation' => 'OR',
                    [ 'key' => '_gvseo_noindex', 'compare' => 'NOT EXISTS' ],
                    [ 'key' => '_gvseo_noindex', 'value' => '0' ],
                ],
            ],
        ] );

        $priorities = [
            'page'    => '0.8',
            'post'    => '0.6',
            'product' => '0.7',
        ];
        $freqs = [
            'page'    => 'monthly',
            'post'    => 'weekly',
            'product' => 'weekly',
        ];
        $priority = $priorities[ $type ] ?? '0.5';
        $freq     = $freqs[ $type ] ?? 'monthly';

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<?xml-stylesheet type="text/xsl" href="' . esc_url( GVSEO_URL . 'admin/sitemap.xsl' ) . '"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        $xml .= '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        foreach ( $posts as $post ) {
            // Skip excluded IDs.
            if ( GVSEO_Settings::is_post_excluded( $post->ID ) ) { continue; }

            $url     = get_permalink( $post->ID );
            $lastmod = gmdate( 'Y-m-d\TH:i:s+00:00', strtotime( $post->post_modified_gmt ) );

            $xml .= "\t<url>\n";
            $xml .= "\t\t<loc>" . esc_url( $url ) . "</loc>\n";
            $xml .= "\t\t<lastmod>" . esc_html( $lastmod ) . "</lastmod>\n";
            $xml .= "\t\t<changefreq>" . esc_html( $freq ) . "</changefreq>\n";
            $xml .= "\t\t<priority>" . esc_html( $priority ) . "</priority>\n";

            // Image sitemap entries.
            $images = self::get_post_images( $post->ID, $post->post_content );
            foreach ( $images as $img ) {
                $xml .= "\t\t<image:image>\n";
                $xml .= "\t\t\t<image:loc>" . esc_url( $img['url'] ) . "</image:loc>\n";
                if ( $img['title'] ) {
                    $xml .= "\t\t\t<image:title>" . esc_html( $img['title'] ) . "</image:title>\n";
                }
                if ( $img['caption'] ) {
                    $xml .= "\t\t\t<image:caption>" . esc_html( $img['caption'] ) . "</image:caption>\n";
                }
                $xml .= "\t\t</image:image>\n";
            }

            $xml .= "\t</url>\n";
        }

        $xml .= '</urlset>';
        return $xml;
    }

    /* ── Helpers ────────────────────────────────────────────────────── */
    private static function type_to_slug( $type ) {
        // product → products, post → posts, page → pages
        return sanitize_title( $type ) . 's';
    }

    private static function slug_to_type( $slug ) {
        // Reverse: strip trailing s if it maps to a real type, else try exact match.
        $without_s = preg_replace( '/s$/', '', $slug );
        if ( post_type_exists( $without_s ) ) { return $without_s; }
        if ( post_type_exists( $slug ) )       { return $slug; }
        // Check all public types for a slug match.
        foreach ( get_post_types( [ 'public' => true ], 'names' ) as $type ) {
            if ( sanitize_title( $type ) . 's' === $slug ) { return $type; }
        }
        return null;
    }

    private static function last_modified( $type ) {
        global $wpdb;
        $date = $wpdb->get_var( $wpdb->prepare(
            "SELECT MAX(post_modified_gmt) FROM {$wpdb->posts}
             WHERE post_type = %s AND post_status = 'publish'",
            $type
        ) );
        return $date ? gmdate( 'Y-m-d\TH:i:s+00:00', strtotime( $date ) ) : gmdate( 'Y-m-d' );
    }

    private static function get_post_images( $post_id, $content ) {
        $images = [];

        // Featured image.
        if ( has_post_thumbnail( $post_id ) ) {
            $thumb_id = get_post_thumbnail_id( $post_id );
            $src      = wp_get_attachment_image_src( $thumb_id, 'full' );
            if ( $src ) {
                $att = get_post( $thumb_id );
                $images[] = [
                    'url'     => $src[0],
                    'title'   => $att ? $att->post_title   : '',
                    'caption' => $att ? $att->post_excerpt : '',
                ];
            }
        }

        // Content images (max 10 per post).
        preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $m );
        foreach ( array_slice( $m[1], 0, 10 ) as $url ) {
            // Skip data URIs and external images.
            if ( strpos( $url, 'data:' ) === 0 ) { continue; }
            if ( strpos( $url, home_url() ) === false && strpos( $url, '/' ) !== 0 ) { continue; }
            $images[] = [ 'url' => $url, 'title' => '', 'caption' => '' ];
        }

        return $images;
    }

    /* ── robots.txt integration ─────────────────────────────────────── */
    public static function add_to_robots( $output, $public ) {
        if ( $public ) {
            $output .= "\nSitemap: " . home_url( '/sitemap.xml' ) . "\n";
        }
        return $output;
    }

    /* ── Cache invalidation ─────────────────────────────────────────── */
    public static function flush_on_save( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
        // Nothing to flush — sitemaps are generated dynamically.
        // If you add caching later, clear it here.
    }

    /* ── Activation: flush rewrite rules ───────────────────────────── */
    public static function activate() {
        // Set flag — actual flush happens on next init hook when $wp_rewrite is ready.
        update_option( 'gvseo_flush_rewrite_rules', '1' );
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    /* ── Settings page: sitemap status widget ────────────────────────── */
    public static function sitemap_url() {
        return home_url( '/sitemap.xml' );
    }
}

GVSEO_Sitemap::init();
