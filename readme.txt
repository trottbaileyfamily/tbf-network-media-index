=== TBF Network Media Index ===
Contributors: kimroybailey, trottbaileyfamily, davidluis123
Tags: multisite, media library, network media, featured image, media modal, gutenberg, attachments
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.27
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Browse, search, and use media from every site in a WordPress multisite network as if all images lived in one media library — without copying or moving a single file.

== Description ==

TBF Network Media Index allows administrators of a WordPress Multisite network to access media from every site through a single **Network Media** tab inside the WordPress media modal.

You can:

- Search network media by title
- Filter by media type
- Filter by origin site
- Insert images into posts from any site
- Set Featured Images from any site (Gutenberg compatible)
- Do all of this **without duplicating files**

This plugin was created by the Trott Bailey Family to manage thousands of images and videos spread across multiple sites for:

- AgriGames
- Family media productions
- Kingdom of Iztolev archives
- Public and private multisite platforms
- Smart-screen and offline installations

WordPress treats each site’s media library as a silo. This plugin removes those silos.

== How It Works ==

1. AJAX scans attachments across network sites
2. Results render in a custom "Network Media" tab in the media modal
3. When you select an image, a **DB-only proxy attachment** is created locally
4. WordPress is safely instructed to treat that proxy as real media
5. Special filters ensure Featured Image previews and REST API calls work
6. No files are copied, moved, or synced

== Features ==

- Network-wide media search
- Media type filtering (images, videos, audio, documents)
- Origin site filtering
- Proper WordPress-style media tiles
- Blue selection UI identical to native media library
- Insert into posts from any site
- Set Featured Image from any site (Block Editor supported)
- Reuses existing proxies (no duplication)
- No file copying
- Deep integration with WordPress media internals

== Requirements ==

- WordPress Multisite
- Network activated
- Administrator permissions

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Network activate the plugin
3. Open any post or page editor
4. Click **Add Media** or **Set Featured Image**
5. Use the **Network Media** tab

== Frequently Asked Questions ==

= Does this copy images between sites? =

No. It creates database-only proxy records that point to the original image URL.

= Does this work with Gutenberg Featured Image? =

Yes. Version 1.0.27 fully supports the Block Editor Featured Image panel.

= Is this safe for large networks? =

Yes. It uses paginated AJAX loading and never performs heavy cross-site operations in a single request.

== Philosophy ==

This plugin reflects how the Trott Bailey Family builds technology:

Don’t duplicate.  
Don’t waste storage.  
Don’t fight WordPress — understand it deeply and work with it.  
Make complex systems feel simple to use.

== Changelog ==

= 1.0.27 =
* Introduced real placeholder attachment strategy
* Gutenberg Featured Image works flawlessly
* Fully stable cross-network virtual media library

= 1.0.26 =
* Fixed Gutenberg REST issue caused by placeholder id -1
* Real attachment selection synced into media frame

= 1.0.25 =
* Console debugging for Featured Image flow

= 1.0.24 =
* Added image_downsize and URL filters for proxy images

= 1.0.23 =
* Synced WordPress media frame selection to enable Insert/Featured buttons

= 1.0.22 =
* Stabilized toolbar and insertion flow

= 1.0.21 =
* Restored search bar, filters, and proper tile sizing

= 1.0.20 =
* Restored toolbar UI and items counter

= 1.0.19 =
* Improved AJAX diagnostics and error reporting

= 1.0.18 =
* Fixed tiny thumbnail sizing without breaking selection

= 1.0.17 =
* Selection overlay made robust against WP core CSS

= 1.0.16 =
* Reworked selection overlay using real DOM elements

= 1.0.15 =
* Improved CSS specificity for selection indicator

= 1.0.14 =
* First fully stable version with search, filters, selection, insertion

= 1.0.13 =
* Stabilized AJAX responses and multisite scanning

= 1.0.12 =
* Fixed media modal layout conflicts

= 1.0.11 =
* Added blue selection UI

= 1.0.10 =
* Insert into post working

= 1.0.9 =
* Introduced DB-only proxy attachment system

= 1.0.8 =
* Added origin site filter

= 1.0.7 =
* Added media type filter

= 1.0.6 =
* Added search support

= 1.0.5 =
* Added pagination and load-more

= 1.0.4 =
* Added thumbnail previews

= 1.0.3 =
* Added site switching and restoration

= 1.0.2 =
* Basic AJAX loading of attachments across sites

= 1.0.1 =
* Integrated into WordPress media modal as custom tab

= 1.0.0 =
* Initial concept: list network media in a custom admin page

== Credits ==

Created by the Trott Bailey Family for the AgriGames ecosystem and the Kingdom of Iztolev media archives.

TBF Network Media Index — turning a network of silos into one living media library.
