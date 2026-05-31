<?php
/**
 * WooCommerce Data Bridge — Grapevine SEO
 *
 * Pulls live WooCommerce product data (price, SKU, stock, ratings, images,
 * variants) and maps it to the schema.org Product/Offer structure.
 * Only loads when WooCommerce is active.
 *
 * @package GrapevineSEO
 * @since   2.1.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class GVSEO_Woo_Bridge {

    /**
     * Check if WooCommerce is active.
     */
    public static function is_active() {
        return class_exists( 'WooCommerce' );
    }

    /**
     * Build a complete schema.org Product array from a WooCommerce product.
     * Called by GVSEO_Frontend when post_type = 'product'.
     *
     * @param  int   $post_id  WC product post ID.
     * @param  array $g        Global RAS settings.
     * @return array|null      JSON-LD schema array, or null on failure.
     */
    public static function product_schema( $post_id, $g ) {
        if ( ! self::is_active() ) {
            return null;
        }

        $product = wc_get_product( $post_id );
        if ( ! $product ) {
            return null;
        }

        $org_url = trailingslashit( $g['org_url'] );

        /* ── Core ─────────────────────────────────── */
        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            '@id'         => get_permalink( $post_id ) . '#product',
            'name'        => $product->get_name(),
            'url'         => get_permalink( $post_id ),
            'description' => wp_strip_all_tags( $product->get_description() ?: $product->get_short_description() ),
            'sku'         => $product->get_sku() ?: null,
            'brand'       => [
                '@type' => 'Brand',
                'name'  => $g['org_name'],
            ],
        ];

        /* ── GTIN / MPN (if stored as meta) ─────── */
        $gtin = get_post_meta( $post_id, '_gtin', true ) ?: get_post_meta( $post_id, '_global_unique_id', true );
        if ( $gtin ) {
            $schema['gtin'] = $gtin;
        }
        $mpn = get_post_meta( $post_id, '_mpn', true );
        if ( $mpn ) {
            $schema['mpn'] = $mpn;
        }

        /* ── Images ──────────────────────────────── */
        $images = self::get_product_images( $product );
        if ( $images ) {
            $schema['image'] = count( $images ) > 1 ? $images : $images[0];
        }

        /* ── Offers ──────────────────────────────── */
        if ( $product->is_type( 'variable' ) ) {
            // Variable product → AggregateOffer with price range.
            $min = $product->get_variation_price( 'min', true );
            $max = $product->get_variation_price( 'max', true );

            $schema['offers'] = [
                '@type'         => 'AggregateOffer',
                'lowPrice'      => wc_format_decimal( $min, 2 ),
                'highPrice'     => wc_format_decimal( $max, 2 ),
                'priceCurrency' => get_woocommerce_currency(),
                'offerCount'    => count( $product->get_visible_children() ),
                'availability'  => self::availability_uri( $product ),
                'url'           => get_permalink( $post_id ),
                'seller'        => [
                    '@type' => 'Organization',
                    'name'  => $g['org_name'],
                    'url'   => $g['org_url'],
                ],
            ];
        } elseif ( $product->is_type( 'grouped' ) ) {
            // Grouped product → multiple Offer objects.
            $offers = [];
            foreach ( $product->get_children() as $child_id ) {
                $child = wc_get_product( $child_id );
                if ( ! $child ) { continue; }
                $offers[] = self::single_offer( $child, $g );
            }
            $schema['offers'] = $offers ?: null;
        } else {
            // Simple / external / downloadable.
            $schema['offers'] = self::single_offer( $product, $g );
        }

        /* ── Aggregate Rating ────────────────────── */
        if ( $product->get_review_count() > 0 ) {
            $schema['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => number_format( (float) $product->get_average_rating(), 1 ),
                'reviewCount' => (int) $product->get_review_count(),
                'bestRating'  => '5',
                'worstRating' => '1',
            ];
        }

        /* ── Individual Reviews (first 5) ───────── */
        $reviews = self::get_product_reviews( $post_id );
        if ( $reviews ) {
            $schema['review'] = $reviews;
        }

        /* ── Product attributes as additionalProperty ── */
        $attrs = self::get_product_attributes( $product );
        if ( $attrs ) {
            $schema['additionalProperty'] = $attrs;
        }

        /* ── Category mapping (WC category → schema category) ── */
        $terms = get_the_terms( $post_id, 'product_cat' );
        if ( $terms && ! is_wp_error( $terms ) ) {
            $schema['category'] = implode( ' > ', array_map( fn( $t ) => $t->name, $terms ) );
        }

        return array_filter( $schema, fn( $v ) => $v !== null && $v !== '' && $v !== [] );
    }

    /* ── Single Offer helper ─────────────────────── */
    private static function single_offer( $product, $g ) {
        $price      = $product->get_price();
        $sale_price = $product->get_sale_price();
        $reg_price  = $product->get_regular_price();

        $offer = [
            '@type'         => 'Offer',
            'price'         => wc_format_decimal( $price, 2 ),
            'priceCurrency' => get_woocommerce_currency(),
            'availability'  => self::availability_uri( $product ),
            'url'           => get_permalink( $product->get_id() ),
            'itemCondition' => 'https://schema.org/NewCondition',
            'seller'        => [
                '@type' => 'Organization',
                'name'  => $g['org_name'],
                'url'   => $g['org_url'],
            ],
        ];

        // Sale price: add priceValidUntil when a sale end date exists.
        if ( $product->is_on_sale() && $sale_end = $product->get_date_on_sale_to() ) {
            $offer['priceValidUntil'] = $sale_end->date( 'Y-m-d' );
        }

        // If no sale: priceValidUntil defaults to +1 year.
        if ( empty( $offer['priceValidUntil'] ) ) {
            $offer['priceValidUntil'] = gmdate( 'Y-m-d', strtotime( '+1 year' ) );
        }

        return $offer;
    }

    /* ── Availability mapping ────────────────────── */
    private static function availability_uri( $product ) {
        if ( $product->is_in_stock() ) {
            return 'https://schema.org/InStock';
        }
        if ( $product->is_type( 'simple' ) && $product->get_stock_status() === 'onbackorder' ) {
            return 'https://schema.org/BackOrder';
        }
        return 'https://schema.org/OutOfStock';
    }

    /* ── Product images ──────────────────────────── */
    private static function get_product_images( $product ) {
        $images    = [];
        $thumb_id  = $product->get_image_id();
        $gallery   = $product->get_gallery_image_ids();
        $all_ids   = array_filter( array_merge( [ $thumb_id ], $gallery ) );

        foreach ( array_slice( $all_ids, 0, 5 ) as $img_id ) {
            foreach ( [ 'full', 'woocommerce_single', 'large' ] as $size ) {
                $src = wp_get_attachment_image_src( $img_id, $size );
                if ( $src ) {
                    $images[] = [
                        '@type'  => 'ImageObject',
                        'url'    => $src[0],
                        'width'  => $src[1],
                        'height' => $src[2],
                    ];
                    break;
                }
            }
        }
        return $images;
    }

    /* ── Product reviews ─────────────────────────── */
    private static function get_product_reviews( $post_id ) {
        $comments = get_comments( [
            'post_id' => $post_id,
            'status'  => 'approve',
            'type'    => 'review',
            'number'  => 5,
        ] );

        $reviews = [];
        foreach ( $comments as $c ) {
            $rating = get_comment_meta( $c->comment_ID, 'rating', true );
            if ( ! $rating ) { continue; }
            $reviews[] = [
                '@type'       => 'Review',
                'reviewRating'=> [
                    '@type'       => 'Rating',
                    'ratingValue' => (string) $rating,
                    'bestRating'  => '5',
                    'worstRating' => '1',
                ],
                'author'      => [ '@type' => 'Person', 'name' => $c->comment_author ],
                'datePublished'=> gmdate( 'Y-m-d', strtotime( $c->comment_date_gmt ) ),
                'reviewBody'  => wp_strip_all_tags( $c->comment_content ),
            ];
        }
        return $reviews;
    }

    /* ── Product attributes → additionalProperty ─── */
    private static function get_product_attributes( $product ) {
        $attrs  = $product->get_attributes();
        $result = [];
        foreach ( $attrs as $attr ) {
            if ( ! $attr->get_visible() ) { continue; }
            $name   = wc_attribute_label( $attr->get_name() );
            $values = $attr->is_taxonomy()
                ? implode( ', ', wp_get_post_terms( $product->get_id(), $attr->get_name(), [ 'fields' => 'names' ] ) )
                : implode( ', ', $attr->get_options() );
            if ( $name && $values ) {
                $result[] = [
                    '@type'       => 'PropertyValue',
                    'name'        => $name,
                    'value'       => $values,
                ];
            }
        }
        return $result;
    }

    /* ── SEO Analyzer integration ─────────────────── */
    /**
     * Extra SEO checks specific to WooCommerce products.
     * Returns array of check result arrays merged into the analyzer's results.
     */
    public static function seo_checks( $post_id ) {
        if ( ! self::is_active() ) { return []; }
        $product = wc_get_product( $post_id );
        if ( ! $product ) { return []; }

        $results = [];

        // Price set check.
        $results['woo_price'] = [
            'status'  => $product->get_price() ? 'pass' : 'fail',
            'cat'     => 'product',
            'label'   => 'Product Price',
            'message' => $product->get_price() ? 'Price is set: ' . wc_price( $product->get_price() ) : 'No price set.',
            'fix'     => $product->get_price() ? '' : 'Set a product price in WooCommerce → Products.',
        ];

        // SKU check.
        $results['woo_sku'] = [
            'status'  => $product->get_sku() ? 'pass' : 'warn',
            'cat'     => 'product',
            'label'   => 'Product SKU',
            'message' => $product->get_sku() ? 'SKU: ' . $product->get_sku() : 'No SKU set.',
            'fix'     => $product->get_sku() ? '' : 'Add a SKU in WooCommerce → Products → Inventory.',
        ];

        // Gallery check.
        $gallery_count = count( $product->get_gallery_image_ids() );
        $results['woo_gallery'] = [
            'status'  => $gallery_count >= 2 ? 'pass' : ( $gallery_count >= 1 ? 'warn' : 'fail' ),
            'cat'     => 'product',
            'label'   => 'Product Gallery',
            'message' => $gallery_count . ' gallery image' . ( $gallery_count !== 1 ? 's' : '' ) . ' added.',
            'fix'     => $gallery_count < 2 ? 'Add at least 2–3 gallery images for rich results.' : '',
        ];

        // Reviews check.
        $review_count = $product->get_review_count();
        $results['woo_reviews'] = [
            'status'  => $review_count >= 1 ? 'pass' : 'warn',
            'cat'     => 'product',
            'label'   => 'Customer Reviews',
            'message' => $review_count . ' approved review' . ( $review_count !== 1 ? 's' : '' ) . '.',
            'fix'     => $review_count === 0 ? 'Reviews enable aggregateRating in schema — encourage customers to leave reviews.' : '',
        ];

        // Short description.
        $results['woo_short_desc'] = [
            'status'  => $product->get_short_description() ? 'pass' : 'warn',
            'cat'     => 'product',
            'label'   => 'Short Description',
            'message' => $product->get_short_description() ? 'Short description set.' : 'No short description.',
            'fix'     => 'Add a short description — used in schema description and Google Shopping.',
        ];

        return $results;
    }
}
