=== TBF Network Media Index ===
Contributors: sherikatrottbailey, kimroybailey, davidluis123
Tags: multisite, media library, network media, photofall gallery, cross-site images
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 6.2.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Browse, search, insert media from every site in a multisite network. Features the "Photofall" masonry gallery, BuddyPress/Vikinger frontend sync.

== Description ==

**TBF Network Media Index** is the ultimate multisite media solution. It allows administrators and users of a WordPress Multisite network to access, search, and utilize media from every single site through one unified **Network Media** tab—without duplicating or copying files across your server.

This plugin was born out of necessity and purpose. It was built to manage over **10,000 images, videos, and audio files** documenting the road to bringing **AgriGames** to life. 

### See the Power in Action
Experience the sheer scale of the Network Media Index and the Photofall Gallery in a live environment:
**[View the AgriGames Journey (1Drop Photo Gallery)](https://trottbaileyfamily.com/1drop/photo)**

Learn more about the plugin on our official landing page:
**[TBF Network Media Index Official Page](https://trottbaileyfamily.com/tbf-network-media-index)**

### 🚀 Powerful Core Features
* **Global Network Indexer:** Scans and indexes images, videos, and audio across thousands of subsites in real-time, using safe chunked-processing to prevent server timeouts.
* **The "Photofall" Gallery:** A gorgeous, Pinterest-style frontend masonry grid that renders your entire network’s media. Includes advanced sorting, live search, and filtering by year, media type, and origin site.
* **Vikinger & BuddyPress Bridge:** Automatically syncs frontend member uploads from custom theme directories directly into the WordPress Media Library for network-wide availability. Enforces strict Admin/Super Admin rules for security.
* **Zero-Duplication Proxy System:** Insert images or set Gutenberg Featured Images from any site. The plugin creates a lightweight, DB-only proxy rather than copying the physical file, saving massive amounts of server storage.
* **Universal Lightbox:** A custom-built, responsive frontend lightbox that natively supports looping video, audio tracks, and high-res imagery with keyboard navigation.

== The Vision & Philosophy ==

This plugin is a product of **woman-powered development, led by our matriarch and Lead Developer, Sherika Trott Bailey**, under the Trott Bailey Family Group. 

Technology should serve a greater purpose. For us, this plugin is the archival engine for **AgriGames** and the Trott Bailey Family Kingdom. 

The heart of all AgriGames and Trott Bailey Family Kingdom is for families to enjoy themselves in a free environment that they never have to think about price for anything. AgriGames venues are unique places chosen for their beauty or utility. No two AgriGames are exactly alike. The heart of AgriGames never changes though; it's a place where each member of the family has things to do that absolutely delights them, from the babies to the young kids, teens, and the parents. 

AgriGames combines fun, fashion, unique architecture, play, and freedom of travel between different venues all for free. Visitors can camp and stay for free, and enjoy all the experiences for free. This plugin organizes the tens of thousands of media files documenting the road to making this money-free world a reality.

== Installation ==

1. Upload the `tbf-network-media-index` folder to the `/wp-content/plugins/` directory.
2. Network Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **Network Admin -> TBF Network Media**.
4. Run the **Full Network Index** to build your initial database.
5. If using the Vikinger theme, click **Sync Vikinger Frontend Uploads** to bridge your custom directories.
6. Visit any post or page editor and click **Add Media** to see the new Network tab!

== Frequently Asked Questions ==

= Does this copy images between sites? =
No. It creates a database-only "proxy" record that points to the original image URL, keeping your server storage highly optimized.

= Does this work with Gutenberg Featured Images? =
Yes! Version 6.x fully supports the Block Editor Featured Image panel using our zero-duplication proxy system.

= Does this work with Elementor? =
Yes! Version 6.x fully supports the Elementor add image from any site across your network to your elementor post, page or project.

= What happens to frontend uploads from users? =
The plugin features a robust integration with BuddyPress and the Vikinger theme. Administrators can easily sync custom frontend upload folders into the central media index.

= Is it safe for massive networks? =
Absolutely. The indexer and the frontend "Load More" AJAX features are built with chunked processing, meaning they safely handle tens of thousands of files without crashing your server.

== Changelog ==

= 6.1.1 =
* MAJOR: 100% WordPress Directory Compliance update (standardized all prefixes to `tbfnmi_` and aligned text-domains strictly to `tbf-network-media-index`).
* FEATURE: Deep Vikinger Theme Bridge. Automatically scans custom `/vikinger/` frontend upload directories to bridge orphaned media into the native WordPress database.
* FEATURE: Strict Admin-Only Enforcer. The Vikinger bridge actively extracts User IDs from file paths and securely rejects any uploads not belonging to an Administrator or Super Admin.
* FEATURE: Native Audio Support. The global indexer, masonry grid, and universal lightbox now fully parse and play `.mp3`, `.wav`, and other audio formats, complete with dynamic fallback WP icons.
* FEATURE: True Database Filtering. Replaced legacy CSS-hiding with a true database-driven query engine. Pagination now works flawlessly across 10,000+ images.
* FEATURE: Smart Photofall Toolbar. Advanced form that auto-submits upon dropdown changes, allowing users to cross-filter by Media Type, Upload Year, and Origin Site.
* FEATURE: Real-Time Terminal Indexer UI. Rebuilt the Dashboard importer with a visual progress bar, active-site tracker, and live terminal log.
* ENHANCEMENT: Chunked Background Processing. Both the Global Indexer (100-file chunks) and the Vikinger Sync (10-file chunks) now use offset-looping to guarantee the server never times out or crashes during heavy operations like thumbnail generation.
* ENHANCEMENT: "Load More" Filter Memory. The AJAX pagination engine now seamlessly remembers active search and dropdown parameters when fetching the next page of results.
* ENHANCEMENT: Unique Site Permalinks. Completely restructured the URL router (e.g., `/photo/image/3-67899/`) to include the Origin Site ID, mathematically eliminating any chance of cross-site ID collisions.
* ENHANCEMENT: Universal Lightbox Overhaul. Lightbox engine rewritten to safely pause, hide, and swap between Video tags, Audio tags, and standard Image tags dynamically without DOM overlap.
* FIX: Cross-Site Single Page 404s. Bypassed native `get_post()` on Single Views to query the custom `tbfnmi_index` directly, allowing users to view cross-network media seamlessly.
* FIX: Infinite Loop Proxy Duplication. Taught the auto-indexer and batch-scanner to actively look for `_tbfnmi_proxy` meta-flags. Proxy attachments created for Featured Images are now securely ignored, keeping the global index perfectly clean.
* FIX: Missing Video Thumbnails. The query engine now actively extracts custom `poster_url` metadata and supplies high-quality native WP fallback icons if a custom video cover is missing.
* FIX: AJAX "Server Connection Lost". Injected the Dashboard module loader into `wp_doing_ajax()` calls to prevent 400 Bad Request errors during background scans.

= 6.0.0 =
* FEATURE: Introduced the initial Photofall Gallery masonry layout.
* FEATURE: Base architecture for the custom Global Network Media database table.
* ENHANCEMENT: Unified cross-site WordPress Media Library modal tab ("Network Media").

= 1.0.27 =
* Introduced real placeholder attachment strategy.
* Gutenberg Featured Image works flawlessly.
* Fully stable cross-network virtual media library proxy engine.