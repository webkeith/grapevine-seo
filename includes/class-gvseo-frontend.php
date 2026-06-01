<?php
/**
 * Frontend — JSON-LD schema output & meta tags.
 * Fully Google Rich Results compliant.
 * Supports all public post types, custom post types, and WooCommerce products.
 *
 * @package GrapevineSEO
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class GVSEO_Frontend {

    public static function init() {
        add_action( 'wp_head', [ __CLASS__, 'output' ], 2 );
    }

    public static function output() {
        $g       = GVSEO_Settings::get();
        $schemas = [];

        // 1. Organization (all pages).
        if ( '1' === $g['organization'] ) {
            $schemas[] = self::organization( $g );
        }

        // 2. WebSite + Sitelinks Searchbox (homepage only).
        if ( is_front_page() && '1' === $g['sitelinks'] ) {
            $schemas[] = self::website( $g );
        }

        // 3. BreadcrumbList (non-home).
        if ( ! is_front_page() && '1' === $g['breadcrumbs'] ) {
            $bc = self::breadcrumbs();
            if ( $bc ) { $schemas[] = $bc; }
        }

        // 4. Per-page schema (any singular post type, including CPTs and WC products).
        if ( is_singular() ) {
            $post_id   = get_the_ID();
            $post_type = get_post_type( $post_id );
            $excluded  = GVSEO_Settings::get_excluded_types();
            // Skip schema entirely for excluded post types or manually excluded post IDs.
            if ( ! in_array( $post_type, $excluded, true ) && ! GVSEO_Settings::is_post_excluded( $post_id ) ) {
                $ps = self::page_schema( $post_id );
                if ( $ps ) { $schemas[] = $ps; }
            }
        }

        // 5. Meta/OG tags.
        self::meta_tags();

        foreach ( $schemas as $s ) {
            if ( ! $s ) { continue; }
            echo "\n<script type=\"application/ld+json\">\n";
            echo wp_json_encode( $s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ); // phpcs:ignore
            echo "\n</script>\n";
        }
    }

    /* ── Meta / OG tags ───────────────────────────── */
    private static function meta_tags() {
        if ( ! is_singular() ) { return; }
        $id   = get_the_ID();
        $desc = get_post_meta( $id, '_gvseo_meta_desc', true );
        $og_t = get_post_meta( $id, '_gvseo_og_title', true );
        $og_d = get_post_meta( $id, '_gvseo_og_desc', true );
        $og_i = get_post_meta( $id, '_gvseo_og_image', true );
        $noix = get_post_meta( $id, '_gvseo_noindex', true );

        // WooCommerce short description as fallback description.
        if ( ! $desc && GVSEO_Woo_Bridge::is_active() ) {
            $product = wc_get_product( $id );
            if ( $product ) {
                $desc = wp_strip_all_tags( $product->get_short_description() );
            }
        }

        if ( $desc )  { printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) ); }
        if ( $noix )  { echo '<meta name="robots" content="noindex,nofollow">' . "\n"; }

        $og_title = $og_t ?: get_the_title( $id );
        $og_desc  = $og_d ?: $desc ?: wp_trim_words( get_the_excerpt( $id ), 30 );
        $og_img   = $og_i ?: ( has_post_thumbnail( $id ) ? wp_get_attachment_url( get_post_thumbnail_id( $id ) ) : '' );

        // WooCommerce product image fallback for OG.
        if ( ! $og_img && GVSEO_Woo_Bridge::is_active() ) {
            $product = wc_get_product( $id );
            if ( $product ) {
                $src = wp_get_attachment_image_src( $product->get_image_id(), 'full' );
                if ( $src ) { $og_img = $src[0]; }
            }
        }

        echo '<meta property="og:type" content="' . ( get_post_type( $id ) === 'product' ? 'product' : 'article' ) . '">' . "\n";
        printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( $og_title ) );
        printf( '<meta property="og:url" content="%s">' . "\n", esc_url( get_permalink( $id ) ) );
        if ( $og_desc ) { printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $og_desc ) ); }
        if ( $og_img )  { printf( '<meta property="og:image" content="%s">' . "\n", esc_url( $og_img ) ); }

        // WooCommerce OG product meta.
        if ( get_post_type( $id ) === 'product' && GVSEO_Woo_Bridge::is_active() ) {
            $product = wc_get_product( $id );
            if ( $product && $product->get_price() ) {
                printf( '<meta property="product:price:amount" content="%s">' . "\n", esc_attr( wc_format_decimal( $product->get_price(), 2 ) ) );
                printf( '<meta property="product:price:currency" content="%s">' . "\n", esc_attr( get_woocommerce_currency() ) );
            }
        }
    }

    /* ── Per-page schema dispatcher ───────────────── */
    public static function page_schema( $post_id ) {
        $post      = get_post( $post_id );
        $g         = GVSEO_Settings::get();
        $mode      = get_post_meta( $post_id, '_gvseo_schema_mode', true ) ?: 'global';
        $post_type = $post ? $post->post_type : 'post';

        if ( 'disabled' === $mode ) { return null; }

        // Schema type: override → per-page meta; global → CPT default from settings.
        if ( 'override' === $mode ) {
            $type = get_post_meta( $post_id, '_gvseo_schema_type', true ) ?: GVSEO_Settings::schema_type_for_post_type( $post_type );
        } else {
            $type = GVSEO_Settings::schema_type_for_post_type( $post_type );
        }

        switch ( $type ) {
            case 'Article':
            case 'BlogPosting':
            case 'NewsArticle':
                return self::article( $type, $post, $g );

            case 'FAQPage':
                return self::faq( $post );

            case 'HowTo':
                return self::howto( $post );

            case 'Product':
                // WooCommerce bridge takes priority when active and bridge is enabled.
                if ( '1' === $g['woo_bridge'] && GVSEO_Woo_Bridge::is_active() && $post_type === 'product' ) {
                    return GVSEO_Woo_Bridge::product_schema( $post_id, $g );
                }
                return self::product( $post, $g );

            case 'Event':
                return self::event( $post, $g );

            case 'Recipe':
                return self::recipe( $post, $g );

            case 'LocalBusiness':
                return self::local_business( $post, $g );

            case 'JobPosting':
                return self::job_posting( $post, $g );

            case 'Course':
                return self::course( $post, $g );

            case 'SoftwareApplication':
                return self::software_app( $post, $g );

            case 'VideoObject':
                return self::video_object( $post, $g );

            case 'Custom':
                $raw = get_post_meta( $post_id, '_gvseo_custom_json', true );
                $dec = json_decode( stripslashes( (string) $raw ), true );
                return is_array( $dec ) ? $dec : null;

            case 'WebPage':
            default:
                return self::webpage( $post, $g );
        }
    }

    /* ── Organization ─────────────────────────────── */
    private static function organization( $g ) {
        $url = trailingslashit( $g['org_url'] );
        $s = [
            '@context' => 'https://schema.org', '@type' => 'Organization',
            '@id' => $url . '#organization', 'name' => $g['org_name'], 'url' => $g['org_url'],
        ];
        if ( $g['org_logo'] ) {
            $dim  = self::image_dims( $g['org_logo'] );
            $logo = [ '@type' => 'ImageObject', 'url' => $g['org_logo'] ];
            if ( $dim ) { $logo['width'] = $dim[0]; $logo['height'] = $dim[1]; }
            $s['logo'] = $logo;
        }
        if ( $g['org_email'] ) {
            $s['email'] = $g['org_email'];
            $s['contactPoint'] = [ '@type' => 'ContactPoint', 'email' => $g['org_email'], 'contactType' => 'customer service' ];
        }
        $same = array_filter( [ $g['social_fb'], $g['social_tw'], $g['social_ig'], $g['social_li'], $g['social_yt'] ] );
        if ( $same ) { $s['sameAs'] = array_values( $same ); }
        return $s;
    }

    /* ── WebSite + Sitelinks ──────────────────────── */
    private static function website( $g ) {
        $url = trailingslashit( $g['org_url'] );
        return [
            '@context' => 'https://schema.org', '@type' => 'WebSite',
            '@id' => $url . '#website', 'name' => $g['org_name'], 'url' => $g['org_url'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [ '@type' => 'EntryPoint', 'urlTemplate' => $url . '?s={search_term_string}' ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /* ── BreadcrumbList ───────────────────────────── */
    private static function breadcrumbs() {
        $items = [ [ '@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => home_url('/') ] ];
        $pos   = 2;
        if ( is_singular() ) {
            $post = get_post();
            // WooCommerce: add product category.
            if ( $post->post_type === 'product' ) {
                $terms = get_the_terms( $post->ID, 'product_cat' );
                if ( $terms && ! is_wp_error( $terms ) ) {
                    $term    = array_shift( $terms );
                    $items[] = [ '@type' => 'ListItem', 'position' => $pos++, 'name' => $term->name, 'item' => get_term_link( $term ) ];
                }
            } elseif ( 'post' === $post->post_type ) {
                $cats = get_the_category( $post->ID );
                if ( $cats ) {
                    $items[] = [ '@type' => 'ListItem', 'position' => $pos++, 'name' => $cats[0]->name, 'item' => get_category_link( $cats[0]->term_id ) ];
                }
            } else {
                // Generic CPT: add the post type archive link.
                $pt_obj = get_post_type_object( $post->post_type );
                if ( $pt_obj && $pt_obj->has_archive ) {
                    $archive_url = get_post_type_archive_link( $post->post_type );
                    if ( $archive_url ) {
                        $items[] = [ '@type' => 'ListItem', 'position' => $pos++, 'name' => $pt_obj->label, 'item' => $archive_url ];
                    }
                }
            }
            $items[] = [ '@type' => 'ListItem', 'position' => $pos, 'name' => html_entity_decode( get_the_title( $post->ID ) ), 'item' => get_permalink( $post->ID ) ];
        } elseif ( is_category() || is_tag() || is_tax() ) {
            $t = get_queried_object();
            $items[] = [ '@type' => 'ListItem', 'position' => $pos, 'name' => $t->name, 'item' => get_term_link( $t ) ];
        } elseif ( is_post_type_archive() ) {
            $pt = get_queried_object();
            $items[] = [ '@type' => 'ListItem', 'position' => $pos, 'name' => $pt->label, 'item' => get_post_type_archive_link( $pt->name ) ];
        }
        if ( count( $items ) < 2 ) { return null; }
        return [ '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items ];
    }

    /* ── Article / BlogPosting / NewsArticle ──────── */
    private static function article( $type, $post, $g ) {
        $url     = get_permalink( $post->ID );
        $g_url   = trailingslashit( $g['org_url'] );
        $title   = html_entity_decode( get_the_title( $post->ID ) );

        $pub  = [ '@type' => 'Organization', '@id' => $g_url . '#organization', 'name' => $g['org_name'] ];
        if ( $g['org_logo'] ) {
            $dim  = self::image_dims( $g['org_logo'] );
            $logo = [ '@type' => 'ImageObject', 'url' => $g['org_logo'] ];
            if ( $dim ) { $logo['width'] = $dim[0]; $logo['height'] = $dim[1]; }
            $pub['logo'] = $logo;
        }

        $author_name = get_post_meta( $post->ID, '_gvseo_author_name', true ) ?: get_the_author_meta( 'display_name', $post->post_author );
        $author_url  = get_post_meta( $post->ID, '_gvseo_author_url', true ) ?: get_author_posts_url( $post->post_author );

        $s = [
            '@context' => 'https://schema.org', '@type' => $type,
            '@id'      => $url . '#article',
            'headline' => mb_substr( $title, 0, 110 ),
            'url'      => $url,
            'mainEntityOfPage' => [ '@type' => 'WebPage', '@id' => $url ],
            'datePublished'    => get_the_date( 'c', $post->ID ),
            'dateModified'     => get_the_modified_date( 'c', $post->ID ),
            'author'           => [ '@type' => 'Person', 'name' => $author_name, 'url' => $author_url ],
            'publisher'        => $pub,
            'description'      => wp_strip_all_tags( get_the_excerpt( $post->ID ) ),
            'inLanguage'       => get_bloginfo( 'language' ),
        ];
        $imgs = self::post_images( $post->ID );
        if ( $imgs ) { $s['image'] = count( $imgs ) > 1 ? $imgs : $imgs[0]; }
        return $s;
    }

    /* ── FAQPage ──────────────────────────────────── */
    private static function faq( $post ) {
        $raw   = get_post_meta( $post->ID, '_gvseo_faq_items', true );
        $items = json_decode( stripslashes( (string) $raw ), true ) ?: [];
        $main  = [];
        foreach ( $items as $item ) {
            if ( empty( $item['q'] ) ) { continue; }
            $main[] = [ '@type' => 'Question', 'name' => sanitize_text_field( $item['q'] ),
                        'acceptedAnswer' => [ '@type' => 'Answer', 'text' => wp_kses_post( $item['a'] ) ] ];
        }
        if ( ! $main ) { return null; }
        return [ '@context' => 'https://schema.org', '@type' => 'FAQPage',
                 'name' => html_entity_decode( get_the_title( $post->ID ) ),
                 'url'  => get_permalink( $post->ID ), 'mainEntity' => $main ];
    }

    /* ── HowTo ────────────────────────────────────── */
    private static function howto( $post ) {
        $raw   = get_post_meta( $post->ID, '_gvseo_steps', true );
        $steps = json_decode( stripslashes( (string) $raw ), true ) ?: [];
        $built = [];
        foreach ( $steps as $i => $step ) {
            if ( empty( $step['name'] ) ) { continue; }
            $built[] = [ '@type' => 'HowToStep', 'position' => $i + 1,
                         'name' => sanitize_text_field( $step['name'] ),
                         'text' => sanitize_textarea_field( $step['text'] ),
                         'url'  => get_permalink( $post->ID ) . '#step-' . ( $i + 1 ) ];
        }
        if ( ! $built ) { return null; }
        $s = [ '@context' => 'https://schema.org', '@type' => 'HowTo',
               'name' => html_entity_decode( get_the_title( $post->ID ) ),
               'url'  => get_permalink( $post->ID ), 'step' => $built ];
        $tt = get_post_meta( $post->ID, '_gvseo_total_time', true );
        if ( $tt ) { $s['totalTime'] = $tt; }
        $img = self::post_image( $post->ID );
        if ( $img ) { $s['image'] = $img; }
        return $s;
    }

    /* ── Product (manual fields — non-WooCommerce) ── */
    private static function product( $post, $g ) {
        $price = get_post_meta( $post->ID, '_gvseo_price', true );
        $s = [
            '@context' => 'https://schema.org', '@type' => 'Product',
            'name'     => html_entity_decode( get_the_title( $post->ID ) ),
            'url'      => get_permalink( $post->ID ),
            'description' => wp_strip_all_tags( get_the_excerpt( $post->ID ) ),
            'brand'    => [ '@type' => 'Brand', 'name' => $g['org_name'] ],
        ];
        $sku = get_post_meta( $post->ID, '_gvseo_sku', true );
        if ( $sku ) { $s['sku'] = $sku; }
        if ( $price ) {
            $avail = get_post_meta( $post->ID, '_gvseo_availability', true ) ?: 'InStock';
            $valid = get_post_meta( $post->ID, '_gvseo_price_until', true ) ?: gmdate( 'Y-m-d', strtotime( '+1 year' ) );
            $s['offers'] = [
                '@type' => 'Offer', 'price' => (string) $price,
                'priceCurrency' => get_post_meta( $post->ID, '_gvseo_currency', true ) ?: 'USD',
                'availability' => 'https://schema.org/' . $avail,
                'priceValidUntil' => $valid,
                'url' => get_permalink( $post->ID ),
                'seller' => [ '@type' => 'Organization', 'name' => $g['org_name'] ],
            ];
        }
        $rating = get_post_meta( $post->ID, '_gvseo_rating', true );
        $rcount = get_post_meta( $post->ID, '_gvseo_rating_count', true );
        if ( $rating && $rcount ) {
            $s['aggregateRating'] = [ '@type' => 'AggregateRating',
                'ratingValue' => number_format( (float) $rating, 1 ),
                'ratingCount' => (int) $rcount, 'bestRating' => '5', 'worstRating' => '1' ];
        }
        $imgs = self::post_images( $post->ID );
        if ( $imgs ) { $s['image'] = count( $imgs ) > 1 ? $imgs : $imgs[0]; }
        return $s;
    }

    /* ── Event ────────────────────────────────────── */
    private static function event( $post, $g ) {
        $start = get_post_meta( $post->ID, '_gvseo_event_start', true );
        if ( ! $start ) { return null; }
        $status_map = [
            'EventScheduled'   => 'https://schema.org/EventScheduled',
            'EventCancelled'   => 'https://schema.org/EventCancelled',
            'EventPostponed'   => 'https://schema.org/EventPostponed',
            'EventRescheduled' => 'https://schema.org/EventRescheduled',
            'EventMovedOnline' => 'https://schema.org/EventMovedOnline',
        ];
        $att_map = [
            'OfflineEventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'OnlineEventAttendanceMode'  => 'https://schema.org/OnlineEventAttendanceMode',
            'MixedEventAttendanceMode'   => 'https://schema.org/MixedEventAttendanceMode',
        ];
        $tz  = (float) get_option( 'gmt_offset', 0 );
        $sgn = $tz >= 0 ? '+' : '-';
        $tzs = $sgn . sprintf( '%02d:00', abs( $tz ) );
        $s   = [
            '@context'            => 'https://schema.org', '@type' => 'Event',
            'name'                => html_entity_decode( get_the_title( $post->ID ) ),
            'startDate'           => date( 'Y-m-d\TH:i:s', strtotime( $start ) ) . $tzs,
            'eventStatus'         => $status_map[ get_post_meta( $post->ID, '_gvseo_event_status', true ) ?? '' ] ?? 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => $att_map[ get_post_meta( $post->ID, '_gvseo_event_attend', true ) ?? '' ] ?? 'https://schema.org/OfflineEventAttendanceMode',
            'url'                 => get_permalink( $post->ID ),
            'description'         => wp_strip_all_tags( get_the_excerpt( $post->ID ) ),
            'organizer'           => [ '@type' => 'Organization', 'name' => $g['org_name'], 'url' => $g['org_url'] ],
        ];
        $end = get_post_meta( $post->ID, '_gvseo_event_end', true );
        if ( $end ) { $s['endDate'] = date( 'Y-m-d\TH:i:s', strtotime( $end ) ) . $tzs; }
        $venue = get_post_meta( $post->ID, '_gvseo_venue', true );
        if ( $venue ) {
            $s['location'] = [ '@type' => 'Place', 'name' => $venue, 'address' => [
                '@type' => 'PostalAddress',
                'streetAddress'   => get_post_meta( $post->ID, '_gvseo_venue_address', true ),
                'addressLocality' => get_post_meta( $post->ID, '_gvseo_venue_city', true ),
                'addressCountry'  => get_post_meta( $post->ID, '_gvseo_venue_country', true ),
            ] ];
        }
        $img = self::post_image( $post->ID );
        if ( $img ) { $s['image'] = $img; }
        return $s;
    }

    /* ── Recipe ───────────────────────────────────── */
    private static function recipe( $post, $g ) {
        $imgs = self::post_images( $post->ID );
        if ( ! $imgs ) { return null; }
        $s = [
            '@context' => 'https://schema.org', '@type' => 'Recipe',
            'name'     => html_entity_decode( get_the_title( $post->ID ) ),
            'url'      => get_permalink( $post->ID ),
            'description' => wp_strip_all_tags( get_the_excerpt( $post->ID ) ),
            'image'    => count( $imgs ) > 1 ? $imgs : $imgs[0],
            'author'   => [ '@type' => 'Person', 'name' => get_the_author_meta( 'display_name', $post->post_author ) ],
            'datePublished' => get_the_date( 'c', $post->ID ),
        ];
        foreach ( [ 'prepTime' => '_gvseo_prep_time', 'cookTime' => '_gvseo_cook_time', 'totalTime' => '_gvseo_total_time' ] as $prop => $key ) {
            $v = get_post_meta( $post->ID, $key, true );
            if ( $v ) { $s[ $prop ] = $v; }
        }
        $yield = get_post_meta( $post->ID, '_gvseo_recipe_yield', true );
        if ( $yield ) { $s['recipeYield'] = $yield; }
        $raw_ing = get_post_meta( $post->ID, '_gvseo_ingredients', true );
        if ( $raw_ing ) { $s['recipeIngredient'] = array_values( array_filter( array_map( 'trim', explode( "\n", $raw_ing ) ) ) ); }
        $raw_steps = get_post_meta( $post->ID, '_gvseo_steps', true );
        $steps = json_decode( stripslashes( (string) $raw_steps ), true ) ?: [];
        if ( $steps ) {
            $inst = [];
            foreach ( $steps as $i => $step ) {
                if ( empty( $step['name'] ) ) { continue; }
                $inst[] = [ '@type' => 'HowToStep', 'name' => $step['name'], 'text' => $step['text'],
                             'url' => get_permalink( $post->ID ) . '#step-' . ( $i + 1 ) ];
            }
            if ( $inst ) { $s['recipeInstructions'] = $inst; }
        }
        $cal = get_post_meta( $post->ID, '_gvseo_calories', true );
        if ( $cal ) { $s['nutrition'] = [ '@type' => 'NutritionInformation', 'calories' => $cal . ' calories' ]; }
        $rating = get_post_meta( $post->ID, '_gvseo_rating', true );
        $rcount = get_post_meta( $post->ID, '_gvseo_rating_count', true );
        if ( $rating && $rcount ) {
            $s['aggregateRating'] = [ '@type' => 'AggregateRating',
                'ratingValue' => number_format( (float) $rating, 1 ),
                'ratingCount' => (int) $rcount, 'bestRating' => '5', 'worstRating' => '1' ];
        }
        return $s;
    }

    /* ── LocalBusiness ────────────────────────────── */
    private static function local_business( $post, $g ) {
        $url = trailingslashit( $g['org_url'] );
        $s   = [ '@context' => 'https://schema.org', '@type' => 'LocalBusiness',
                 '@id' => $url . '#localbusiness', 'name' => $g['org_name'], 'url' => $g['org_url'] ];
        if ( $g['org_email'] ) { $s['email'] = $g['org_email']; }
        $img = self::post_image( $post->ID );
        if ( $img ) { $s['image'] = $img; }
        return $s;
    }

    /* ── JobPosting ───────────────────────────────── */
    private static function job_posting( $post, $g ) {
        return [
            '@context'          => 'https://schema.org', '@type' => 'JobPosting',
            'title'             => html_entity_decode( get_the_title( $post->ID ) ),
            'description'       => wp_kses_post( $post->post_content ),
            'datePosted'        => get_the_date( 'Y-m-d', $post->ID ),
            'hiringOrganization'=> [ '@type' => 'Organization', 'name' => $g['org_name'], 'sameAs' => $g['org_url'] ],
            'jobLocation'       => [ '@type' => 'Place', 'address' => [ '@type' => 'PostalAddress', 'addressCountry' => get_post_meta( $post->ID, '_gvseo_venue_country', true ) ?: '' ] ],
        ];
    }

    /* ── Course ───────────────────────────────────── */
    private static function course( $post, $g ) {
        return [
            '@context'    => 'https://schema.org', '@type' => 'Course',
            'name'        => html_entity_decode( get_the_title( $post->ID ) ),
            'description' => wp_strip_all_tags( get_the_excerpt( $post->ID ) ),
            'provider'    => [ '@type' => 'Organization', 'name' => $g['org_name'], 'sameAs' => $g['org_url'] ],
            'url'         => get_permalink( $post->ID ),
        ];
    }

    /* ── SoftwareApplication ──────────────────────── */
    private static function software_app( $post, $g ) {
        $s = [
            '@context'        => 'https://schema.org', '@type' => 'SoftwareApplication',
            'name'            => html_entity_decode( get_the_title( $post->ID ) ),
            'url'             => get_permalink( $post->ID ),
            'applicationCategory' => 'WebApplication',
            'operatingSystem' => 'Web',
            'description'     => wp_strip_all_tags( get_the_excerpt( $post->ID ) ),
        ];
        $price = get_post_meta( $post->ID, '_gvseo_price', true );
        $s['offers'] = [ '@type' => 'Offer',
            'price' => $price ?: '0',
            'priceCurrency' => get_post_meta( $post->ID, '_gvseo_currency', true ) ?: 'USD',
        ];
        $rating = get_post_meta( $post->ID, '_gvseo_rating', true );
        $rcount = get_post_meta( $post->ID, '_gvseo_rating_count', true );
        if ( $rating && $rcount ) {
            $s['aggregateRating'] = [ '@type' => 'AggregateRating',
                'ratingValue' => number_format( (float) $rating, 1 ), 'ratingCount' => (int) $rcount,
                'bestRating' => '5', 'worstRating' => '1' ];
        }
        return $s;
    }

    /* ── VideoObject ──────────────────────────────── */
    private static function video_object( $post, $g ) {
        $img = self::post_image( $post->ID );
        $s = [
            '@context'     => 'https://schema.org', '@type' => 'VideoObject',
            'name'         => html_entity_decode( get_the_title( $post->ID ) ),
            'description'  => wp_strip_all_tags( get_the_excerpt( $post->ID ) ),
            'uploadDate'   => get_the_date( 'c', $post->ID ),
            'url'          => get_permalink( $post->ID ),
            'publisher'    => [ '@type' => 'Organization', 'name' => $g['org_name'] ],
        ];
        if ( $img ) { $s['thumbnailUrl'] = $img['url']; }
        return $s;
    }

    /* ── WebPage fallback ─────────────────────────── */
    private static function webpage( $post, $g ) {
        $url = trailingslashit( $g['org_url'] );
        return [
            '@context'      => 'https://schema.org', '@type' => 'WebPage',
            '@id'           => get_permalink( $post->ID ) . '#webpage',
            'name'          => html_entity_decode( get_the_title( $post->ID ) ),
            'url'           => get_permalink( $post->ID ),
            'datePublished' => get_the_date( 'c', $post->ID ),
            'dateModified'  => get_the_modified_date( 'c', $post->ID ),
            'description'   => wp_strip_all_tags( get_the_excerpt( $post->ID ) ),
            'isPartOf'      => [ '@type' => 'WebSite', '@id' => $url . '#website' ],
            'publisher'     => [ '@type' => 'Organization', '@id' => $url . '#organization' ],
        ];
    }

    /* ── Image helpers ────────────────────────────── */
    private static function post_image( $post_id ) {
        if ( ! has_post_thumbnail( $post_id ) ) { return null; }
        $tid = get_post_thumbnail_id( $post_id );
        $src = wp_get_attachment_image_src( $tid, 'large' );
        return $src ? [ '@type' => 'ImageObject', 'url' => $src[0], 'width' => $src[1], 'height' => $src[2] ] : null;
    }
    private static function post_images( $post_id ) {
        if ( ! has_post_thumbnail( $post_id ) ) { return []; }
        $tid  = get_post_thumbnail_id( $post_id );
        $imgs = [];
        foreach ( [ 'full', 'medium_large', 'thumbnail' ] as $size ) {
            $src = wp_get_attachment_image_src( $tid, $size );
            if ( $src ) { $imgs[] = [ '@type' => 'ImageObject', 'url' => $src[0], 'width' => $src[1], 'height' => $src[2] ]; }
        }
        return array_unique( $imgs, SORT_REGULAR );
    }
    private static function image_dims( $url ) {
        $id = attachment_url_to_postid( $url );
        if ( ! $id ) { return null; }
        $m  = wp_get_attachment_metadata( $id );
        return ( $m && isset( $m['width'] ) ) ? [ $m['width'], $m['height'] ] : null;
    }
}
GVSEO_Frontend::init();
