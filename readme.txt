=== Grapevine SEO ===
Contributors: keithquinones
Tags: schema, seo, structured data, rich results, json-ld
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: 2.12.0
Author: Keith Quinones
Author URI: https://github.com/webkeith

JSON-LD Schema markup + full SEO analysis engine with per-page scoring, site-wide reports, and rich results compliance.

== Description ==

Grapevine SEO is a dual-purpose plugin combining Google Rich Results-compliant JSON-LD schema output with a comprehensive SEO analysis engine — built for Grapevine clients.

= Schema Markup =
* 16 schema types: Article, BlogPosting, NewsArticle, WebPage, FAQPage, HowTo, Product, Event, Recipe, Person, LocalBusiness, JobPosting, Course, SoftwareApplication, VideoObject, Custom JSON-LD
* Google Rich Results compliant — headline ≤110 chars, Event requires eventStatus/eventAttendanceMode, Product uses ratingCount, Recipe requires image
* WooCommerce bridge: pulls live price, SKU, stock, ratings, and gallery automatically
* Per-CPT default schema type; exclusion system hides Elementor templates, builder layouts, and WordPress system types
* Organization schema with primary and additional address, phone, and sameAs social profiles (Facebook, X/Twitter, Instagram, LinkedIn, YouTube, TikTok)
* Multi-location LocalBusiness support with stable, slug-based @id per location, linked to the Organization via department/parentOrganization
* BreadcrumbList auto-built from post hierarchy
* WebSite + Sitelinks Searchbox on homepage

= SEO Analysis Engine =
* 60+ checks across 9 categories: Title Tag, Meta Description, URL/Slug, Headings, Content, Image SEO, Links, Technical SEO, Focus Keyword
* DOM-based detection — fetches the live rendered page to check what Google actually sees, regardless of which SEO plugin set it (Yoast, Rank Math, or Grapevine)
* Compatibility detection for Yoast SEO and Rank Math — reads their stored keyword and meta values
* Transition words check (aims for 30%+ of sentences)
* Subheading distribution (max 300 words between headings)
* Multiple focus keywords with secondary keyword coverage check
* Passive voice detection, Flesch Reading Ease score
* Content freshness warning (flags pages not updated in 18+ months)
* Orphan page detection via database query
* Duplicate title and meta description detection across the site
* Image SEO: alt text, short alt text, duplicate alt, WebP format, lazy loading
* External link rel="noopener" check
* HTTPS, canonical, noindex detection

= Site-wide SEO Dashboard =
* Chart.js gauge and distribution charts
* Per-page analyzer with dropdown selector and category check cards
* Analyze Entire Site — scans all pages in one click
* All-pages table with filter by grade (Excellent / Good / Needs Work / Poor)

= XML Sitemap =
* Sitemap index at /sitemap.xml with per-post-type sub-sitemaps
* Image sitemap support (featured image + content images)
* Respects all exclusion rules; noindexed posts are skipped
* Linked automatically in robots.txt

= Version Control =
* Database migration engine with full upgrade history log
* Version Control admin page with changelog, migration status table, and release guide

== Features ==
* Per-page schema override with 16 schema types
* Two-tab post editor meta box: Schema | SEO Analysis
* Focus keyword, meta description with character counter, OG tags, no-index toggle
* Toggle to disable Grapevine's own meta description/Open Graph output when another SEO plugin (Yoast, Rank Math) already handles it
* WooCommerce data bridge (price, SKU, stock, ratings, gallery, variants)
* CPT-specific default schema types
* Exclusion system for builder templates, Elementor library, and custom post types
* GitHub-based auto-updates via Plugin Update Checker v5.3
* Light theme admin interface

== Installation ==
1. Upload the plugin files to /wp-content/plugins/grapevine-seo/
2. Activate the plugin through the Plugins menu in WordPress
3. Go to Grapevine SEO → Global Settings and enter your organization details

== Frequently Asked Questions ==

= Does this work with Yoast SEO or Rank Math? =
Yes. Grapevine SEO detects if Yoast SEO or Rank Math is active and reads their stored keyword and meta values for SEO analysis scoring. Schema output is independent of those plugins. If Yoast or Rank Math is already outputting meta description/Open Graph tags, disable Grapevine's own copy under Global Settings → Meta & Social Tags to avoid duplicate tags.

= Does this generate an XML sitemap? =
Yes. A sitemap index is available at https://grapevinepr.com.au/sitemap.xml. It is linked in your robots.txt automatically.

= Does this support WooCommerce? =
Yes. The WooCommerce bridge pulls live price, SKU, stock status, ratings, and product gallery images directly from WooCommerce.

= How does multi-location LocalBusiness schema work? =
Add each location under Global Settings → LocalBusiness Locations. Each location gets its own PostalAddress, phone, opening hours, and a persistent URL slug that sets its schema @id (format: https://yoursite.com/[slug]/#business). The slug is generated once from the location's name and never changes automatically afterward, even if you reorder or remove other locations — this keeps each location's schema identity stable over time. All enabled locations are linked to the Organization schema via department/parentOrganization references.

== Changelog ==

= 2.12.0 =
* Added toggle to disable Grapevine SEO's own XML sitemap (Global Settings → XML Sitemap) for sites already running another plugin's sitemap (e.g. Yoast SEO)
* Auto-detects Yoast SEO / Rank Math and shows a notice recommending only one sitemap be submitted in Google Search Console
* Rewrite rules now flush automatically when this toggle changes, so /sitemap.xml starts or stops resolving immediately

= 2.11.1 =
* Added the simple `openingHours` string property (e.g. "Mo-Fr 09:00-17:00") to LocalBusiness output, alongside the existing structured `openingHoursSpecification` — some validators flag `openingHours` as missing even when the structured form is present
* No change to `geo` output — GeoCoordinates were already correctly emitted whenever a location has latitude/longitude set in Global Settings; if a validator flags `geo` as missing, add coordinates for that location under Global Settings → LocalBusiness Locations

= 2.11.0 =
* Fixed: LocalBusiness @id was derived from array index (#localbusiness, #localbusiness-1) and silently changed if a location was reordered or an earlier location removed
* Added persistent, admin-editable URL slug per LocalBusiness location; @id is now stable and slug-scoped (https://[domain]/[slug]/#business)
* Migration backfills a slug for every existing location on update — each location's live @id changes once, from the old index-based form to the new slug-based form
* Consolidated @id generation into a single shared method (local_business_id) used by both the standalone LocalBusiness JSON-LD blocks and the Organization's department references, removing a previous duplicate-logic bug risk
* No changes to Service, Review, Organization identity, or FAQ schema

= 2.10.0 =
* Added toggle to disable Grapevine SEO's own meta description/Open Graph tag output (Global Settings → Meta & Social Tags)
* Auto-detects Yoast SEO / Rank Math and shows a warning when both plugins would output competing OG tags
* Fixes duplicate/conflicting og:title, og:description, og:url, og:type tags on sites also running Yoast or Rank Math

= 2.9.0 =
* Added Person schema type for staff/team bio pages, with Job Title, Works For, Email, and Same As fields
* Name pulled from page title, image from featured image, description from excerpt

= 2.8.0 =
* Redesigned Dashboard and Global Settings admin UI: warmer palette, Dashicons instead of emoji, softened copy on Organization/Address/Social sections

= 2.7.1 =
* Fixed BreadcrumbList missing intermediate ancestor pages for hierarchical Pages — breadcrumbs now walk the full post_parent chain instead of relying on post-type archive links (which regular Pages don't have)

= 2.5.0 =
* Added TikTok to social profiles (sameAs)
* Re-added compatibility detection for Yoast SEO and Rank Math (GVSEO_Compat)
* Fixed fatal error — rewrite rules now register safely on the init hook
* SEO Analyzer reads focus keyword from Yoast/RankMath when Grapevine's own field is empty

= 2.4.0 =
* Added XML sitemap (sitemap index + per-post-type sitemaps with image support)
* Added transition words check (30%+ target)
* Added subheading distribution check (max 300 words per section)
* Added multiple / secondary focus keywords
* SEO Analyzer now fetches live page HTML to detect meta tags regardless of which plugin set them

= 2.3.0 =
* Added primary address (PostalAddress) to Organization schema
* Added optional additional / branch address (hasPOS)
* Added contact phone number (E.164 format)
* Added Schema & SEO Exclusions: per-post-type checkbox grid and individual post ID exclusions
* Builder templates (Elementor, Beaver Builder, Divi, Bricks, Oxygen) excluded automatically

= 2.2.0 =
* Expanded SEO Analyzer to 60+ checks across 9 categories
* Added passive voice detection (Flesch Reading Ease)
* Added content freshness warning
* Added orphan page detection via DB query
* Added duplicate title/meta detection
* Added image SEO checks (WebP, lazy loading, alt quality)
* Added external link rel attribute check

= 2.1.0 =
* Added WooCommerce Data Bridge
* Added CPT-specific default schema types in Settings
* Added support for JobPosting, Course, SoftwareApplication, VideoObject schema types
* CPT-aware breadcrumbs

= 2.0.0 =
* Initial release of Grapevine SEO
* Full prefix rename: RAS_ → GVSEO_, _ras_ → _gvseo_
* GitHub Update Checker integration (Plugin Update Checker v5.3)
* SEO Analysis Engine with 22 initial checks
* Light theme admin interface

== Upgrade Notice ==

= 2.11.0 =
LocalBusiness @id is now slug-based instead of index-based — each location's schema @id will change once on update. Re-validate each location page in Google's Rich Results Test after updating. See the FAQ for how location slugs work.

= 2.10.0 =
Adds a toggle to prevent duplicate meta/OG tags on sites running Yoast SEO or Rank Math. If you have another SEO plugin active, visit Global Settings → Meta & Social Tags and turn Grapevine's own meta/OG output off.
