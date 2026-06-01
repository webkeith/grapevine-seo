<?php
/**
 * SEO Plugin Compatibility — Grapevine SEO
 *
 * Detects Yoast SEO and Rank Math and reads their stored values so the
 * SEO Analyzer scores against what those plugins actually output — not
 * just what Grapevine stored.
 *
 * This class is READ-ONLY. It never overrides or modifies the other
 * plugin's data. It simply provides a unified way to read keyword,
 * meta description, OG tags, and noindex values from whichever source
 * has them set.
 *
 * @package GrapevineSEO
 * @since   2.5.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class GVSEO_Compat {

    /* ── Detection ────────────────────────────────────────────────── */

    public static function has_yoast() {
        return defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Frontend' );
    }

    public static function has_rankmath() {
        return defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' );
    }

    public static function has_seo_plugin() {
        return self::has_yoast() || self::has_rankmath();
    }

    public static function active_plugin_name() {
        if ( self::has_yoast() )    { return 'Yoast SEO'; }
        if ( self::has_rankmath() ) { return 'Rank Math'; }
        return '';
    }

    /* ── Unified meta readers ─────────────────────────────────────── */

    /**
     * Focus keyword.
     * Yoast:     _yoast_wpseo_focuskw
     * Rank Math: rank_math_focus_keyword (first of comma-separated list)
     * Fallback:  Grapevine's own _gvseo_focus_kw
     */
    public static function get_focus_keyword( $post_id ) {
        if ( self::has_yoast() ) {
            $v = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
            if ( $v ) { return strtolower( trim( $v ) ); }
        }
        if ( self::has_rankmath() ) {
            $v = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
            if ( $v ) {
                $parts = explode( ',', $v );
                return strtolower( trim( $parts[0] ) );
            }
        }
        return strtolower( trim( (string) get_post_meta( $post_id, '_gvseo_focus_kw', true ) ) );
    }

    /**
     * Meta description.
     * Yoast:     _yoast_wpseo_metadesc
     * Rank Math: rank_math_description
     * Fallback:  _gvseo_meta_desc
     */
    public static function get_meta_description( $post_id ) {
        if ( self::has_yoast() ) {
            $v = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
            if ( $v ) { return trim( $v ); }
        }
        if ( self::has_rankmath() ) {
            $v = get_post_meta( $post_id, 'rank_math_description', true );
            if ( $v ) { return trim( $v ); }
        }
        return trim( (string) get_post_meta( $post_id, '_gvseo_meta_desc', true ) );
    }

    /**
     * OG Title.
     * Yoast:     _yoast_wpseo_opengraph-title
     * Rank Math: rank_math_facebook_title
     */
    public static function get_og_title( $post_id ) {
        if ( self::has_yoast() ) {
            $v = get_post_meta( $post_id, '_yoast_wpseo_opengraph-title', true );
            if ( $v ) { return $v; }
        }
        if ( self::has_rankmath() ) {
            $v = get_post_meta( $post_id, 'rank_math_facebook_title', true );
            if ( $v ) { return $v; }
        }
        return get_post_meta( $post_id, '_gvseo_og_title', true );
    }

    /**
     * OG Description.
     * Yoast:     _yoast_wpseo_opengraph-description
     * Rank Math: rank_math_facebook_description
     */
    public static function get_og_description( $post_id ) {
        if ( self::has_yoast() ) {
            $v = get_post_meta( $post_id, '_yoast_wpseo_opengraph-description', true );
            if ( $v ) { return $v; }
        }
        if ( self::has_rankmath() ) {
            $v = get_post_meta( $post_id, 'rank_math_facebook_description', true );
            if ( $v ) { return $v; }
        }
        return get_post_meta( $post_id, '_gvseo_og_desc', true );
    }

    /**
     * OG / Social Image URL.
     * Yoast:     _yoast_wpseo_opengraph-image
     * Rank Math: rank_math_facebook_image
     */
    public static function get_og_image( $post_id ) {
        if ( self::has_yoast() ) {
            $v = get_post_meta( $post_id, '_yoast_wpseo_opengraph-image', true );
            if ( $v ) { return $v; }
        }
        if ( self::has_rankmath() ) {
            $v = get_post_meta( $post_id, 'rank_math_facebook_image', true );
            if ( $v ) { return $v; }
        }
        return get_post_meta( $post_id, '_gvseo_og_image', true );
    }

    /**
     * Noindex.
     * Yoast:     _yoast_wpseo_meta-robots-noindex = '1'
     * Rank Math: rank_math_robots contains 'noindex'
     * Fallback:  _gvseo_noindex = '1'
     */
    public static function is_noindex( $post_id ) {
        if ( self::has_yoast() ) {
            if ( get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true ) === '1' ) {
                return true;
            }
        }
        if ( self::has_rankmath() ) {
            $robots = get_post_meta( $post_id, 'rank_math_robots', true );
            if ( ( is_array( $robots ) && in_array( 'noindex', $robots, true ) ) ||
                 ( is_string( $robots ) && strpos( $robots, 'noindex' ) !== false ) ) {
                return true;
            }
        }
        return get_post_meta( $post_id, '_gvseo_noindex', true ) === '1';
    }
}
