=== TBF Big King Media: WordPress Multisite Shared Media Library + Photofall ===
Contributors: sherikatrottbailey, kimroy-bailey, trottbaileyfamily
Tags: multisite media library, shared media library, network media library, wordpress multisite media, masonry gallery
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 7.0.3.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A WordPress Multisite shared media library plugin for browsing, searching, inserting, and displaying network media without duplication, 100% FREE!

== Description ==

**TBF Big King Media** is a powerful **WordPress Multisite shared media library plugin** that lets administrators and users browse, search, insert, and manage media from across an entire multisite network through one unified interface.

If you need a **shared media library for WordPress Multisite**, a **network media library**, or a way to **use one media library across multiple WordPress subsites**, this plugin is built for that exact purpose.

Instead of copying physical files between subsites, TBF Big King Media uses a lightweight **zero-duplication proxy system** that preserves storage, reduces inode waste, and keeps your network media workflow efficient.

It also includes **Photofall**, a high-performance Pinterest-style masonry gallery for displaying images, video, and audio across your WordPress network.

### Best for

* WordPress Multisite shared media libraries
* Network-wide media browsing and search
* Cross-site featured images
* Shared image, video, and audio access across subsites
* Pinterest-style masonry galleries
* Frontend media uploads
* Elementor media workflows
* Media SEO and internal interlinking
* Large multisite networks with thousands of files

### Core Features

* **WordPress Multisite Shared Media Library**
  Browse and search images, video, and audio from across your entire network in one central media experience.

* **Zero-Duplication Media Proxies**
  Insert cross-site media and Gutenberg featured images without physically copying files between subsites.

* **Photofall Masonry Gallery**
  Display your network media in a fast Pinterest-style gallery with live search, filtering, sorting, and responsive layout support.

* **Cross-Site Featured Image Support**
  Set featured images from media hosted on another subsite using the plugin's proxy architecture.

* **Frontend Media Uploads**
  Allow approved users to upload media from the frontend without entering the WordPress dashboard.

* **Elementor Support**
  Use cross-site media inside Elementor-powered posts, pages, and projects.

* **Audio Player and Playlist Tools**
  Build rich audio experiences with floating player modes, playlists, and synchronized slideshows.

* **Media SEO Tools**
  Improve internal media discoverability with usage mapping, deep interlinking, sitemap support, and "Featured In" style relationships.

* **Vikinger / BuddyPress Integration**
  Sync supported frontend member upload locations into your main WordPress media workflow.

* **Built for Large Networks**
  Chunked indexing and AJAX-powered loading help the plugin scale safely across large WordPress multisite environments.

### Why use this plugin?

Many WordPress multisite administrators struggle with the same problems:

* Media is trapped inside individual subsites
* Teams keep uploading duplicate copies of the same files
* Featured images cannot be reused across the network
* Media discovery becomes slow and disorganized at scale
* Frontend media workflows are fragmented
* Existing gallery plugins do not understand multisite architecture

**TBF Big King Media** solves those problems with a true **network media library for WordPress Multisite**.

### Live Demo and Official Page

See the plugin in action:

* [View the AgriGames Journey (1Drop Photo Gallery)](https://trottbaileyfamily.com/1drop/photo)
* [Official TBF Big King Media Landing Page](https://trottbaileyfamily.com/tbf-big-king-media)

### Ideal Use Cases

This plugin is ideal for:

* schools and universities running WordPress Multisite
* media-rich communities
* multisite membership platforms
* BuddyPress and social community networks
* agencies managing many subsites
* publishers using one central media workflow
* organizations that need a shared WordPress media library
* creators who want a Pinterest-style gallery on multisite

### Single Site Support

Starting with version 7.x, the plugin also supports **single-site WordPress installations**. Single-site admins can use Photofall, audio tools, and media SEO features without multisite enabled.

== The Story Behind the Plugin ==

TBF Big King Media was developed from real-world need while organizing a very large archive of images, videos, and audio documenting the development of AgriGames and the Trott Bailey Family Kingdom project. The plugin has been actively refined over multiple years before entering the WordPress repository.

That long private development cycle is why the public repository version began at a mature release line rather than version 1.0.0.

* **Interested in our other Plugins:** Install the [Trott Bailey Family Smart TV Feed](https://wordpress.org/plugins/trott-bailey-family-smart-tv-feed/) and turn your WordPress website into a Smart TV Channel. The Trott Bailey Family Kingdom uses this tool to stream Oasis Videos (https://trottbaileyfamily.com/oasis) to billions of viewers. 
* **TBF Bulk Feedback Manager: User Generations & Bulk Comments** is an indispensable toolkit for administrators who need to execute WordPress user management at scale. Whether you’re setting up a new community, creating a realistic test environment, or digitizing offline interactions, [The TBF Bulk Feedback Manager](https://wordpress.org/plugins/tbf-bulk-feedback-manager/) provides a suite of powerful, centralized tools to perform complex tasks in minutes, not hours.

== External Services ==

This plugin may connect to the following external services to facilitate SEO features:

* **Google & Bing Pinging**
  * **Service:** Google Search Console / Bing Webmaster Tools
  * **Data Sent:** The URL of your generated XML sitemap
  * **When:** Only when "Notify Search Engines" is clicked in the admin dashboard or when settings are saved if enabled
  * **Terms:** [Google Terms](https://policies.google.com/terms), [Bing Terms](https://www.microsoft.com/en-us/legal/intellectualproperty/copyright/default.aspx)

== Installation ==

1. Upload the `tbf-big-king-media` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin.
3. On WordPress Multisite, Network Activate the plugin through Network Admin.
4. Go to **Network Admin -> TBF Network Media**.
5. Run the **Full Network Index** to build the media index.
6. If needed, configure Photofall, upload permissions, and SEO settings.
7. Open any editor and use the shared network media tools.

== Frequently Asked Questions ==

= What is a WordPress Multisite shared media library? =

A WordPress Multisite shared media library lets administrators and editors browse and use media from across multiple subsites instead of being limited to one site's media library. TBF Big King Media provides that functionality through a unified network media interface.

= Does this plugin work on WordPress Multisite? =

Yes. This plugin is built specifically for **WordPress Multisite** and is designed to act as a **shared media library across your network**.

= Does this plugin support single-site WordPress? =

Yes. Starting with version 7.x, the plugin also supports single-site WordPress installations.

= Does this plugin copy files between subsites? =

No. It uses a **zero-duplication proxy system**. That means it can reference and insert media across subsites without physically duplicating the original file.

= Can I use cross-site featured images? =

Yes. You can set Gutenberg featured images using media from another subsite through the plugin's proxy architecture.

= Does this work with Elementor? =

Yes. The plugin supports cross-site media insertion for Elementor-powered content.

= Can users upload media from the frontend? =

Yes. Authorized users can upload media from the frontend without entering wp-admin.

= What does Photofall do? =

Photofall is the plugin's Pinterest-style masonry gallery for displaying network media. It supports images, video, and audio with filtering, search, and responsive layout behavior.

= Is this plugin good for large multisite networks? =

Yes. The indexer and frontend media tools are designed with chunked processing and AJAX loading to help handle large networks with thousands of files.

= The "Network Media" tab is empty. What should I do? =

Run the indexer at least once from **Network Admin -> TBF Network Media**. If needed, reduce the batch size.

= Images show as broken icons in the grid. =

This is usually a mixed-content issue. Make sure all sites in your network use HTTPS.

= Frontend uploader says "Permission Denied". =

Check the upload permission settings and verify that the user's role is allowed.

= "Featured In" links are missing. =

The crawler runs on `save_post`. To build links for older content, run a full batch index so historical posts are scanned.

= What happens to frontend uploads from BuddyPress or Vikinger areas? =

The plugin can sync supported frontend upload locations into the central media workflow for network-wide availability.

== Screenshots ==

1. **Shared Media Library Dashboard** - Central command area for managing your WordPress Multisite shared media library.
2. **Network Media Indexer** - AJAX-powered index builder for scanning media across subsites.
3. **Photofall Gallery** - Pinterest-style masonry gallery for displaying images, video, and audio.
4. **Media SEO Panel** - Tools for interlinking, metadata, and media visibility improvements.
5. **Network Settings** - Configure media indexing, permissions, and gallery behavior across the network.
6. **Frontend Uploader** - Upload media from the frontend without opening the WordPress dashboard.
7. **Princess Keilah Studio** - Floating audio player with rich playlist and slideshow features.
8. **Playlist Builder** - Create audio playlists and synchronized slideshow experiences.
9. **Shortcode Tools** - Add players, galleries, and uploaders anywhere with shortcodes.
10. **Network Override Settings** - Control subsite behavior from a master admin panel.
11. **Responsive Masonry Grid** - High-speed shared media gallery layout.
12. **Media Lightbox** - Open images, video, and audio in an interactive lightbox.
13. **Integrated Studio Mode** - Combined audio player and slideshow mode.
14. **Audio-Focused Mode** - Minimal UI mode for music and audio listening.
15. **Micro-Player Mode** - Compact floating play button for lightweight playback.
16. **Frontend Media Management** - Hide or delete media directly from the frontend if authorized.
17. **Direct Shared Library Uploads** - Upload assets into the multisite media workflow.
18. **Shared Media Modal** - Network-wide modal with filters, shuffling, and search tools.
19. **Thumbnail and Metadata Tools** - Assign thumbnails and manage metadata for audio and video assets.

https://vimeo.com/1169118276?share=copy&fl=sv&fe=ci

== Changelog ==

= 7.0.3 =
* Architecture Update: Completely severed the Network Dashboard from single-site installations to permanently eliminate duplicate menu items and conflicting settings.
* UI/UX: Consolidated all single-site configurations (Permissions, Insertion Mode, Indexer) into a unified tab interface exclusively under the "Media > Big King Media" menu.
* Database Fix: Eliminated fragile `dbDelta()` operations. The Big King Indexer now forces table creation using raw MySQL to guarantee database generation on fresh installations.
* Database Fix: Resolved critical MySQL Strict Mode crashes by updating default timestamp values from `0000-00-00` to `2000-01-01` and changing length-capped varchars to TEXT fields.
* Bug Fix: Destroyed the "silent failure" anti-pattern in the indexer. SQL insertion rejections are now accurately caught and immediately surfaced in red text within the UI terminal.
* Bug Fix: Patched the "Error loading" and "No items found" Backbone.js crashes in the Elementor and Gutenberg media modals by strictly wrapping AJAX payloads in `wp_send_json_success()`.
* Bug Fix: Added `ob_clean()` buffer wiping to all AJAX endpoints to guarantee that rogue PHP notices from third-party plugins cannot corrupt the Big King Media JSON responses.
* Bug Fix: Added missing `poster_url` field mapping to the indexer schema to prevent database payload rejection.

= 7.0.1.17 =
* Architecture update: separated Princess Keilah Studio CSS and JS into dedicated asset files for better caching and resilience.

= 7.0.1.16 =
* Added `sessionStorage` state persistence for player minimization.
* Converted playlist view to a glass-style overlay.
* Adjusted slideshow arrow `z-index` behavior.
* Added custom desktop playlist scrollbars.

= 7.0.1.15 =
* Added audio playback persistence across page loads.
* Added customizable player background opacity.
* Made slideshow images clickable and linked to media pages.
* Removed forced blue play-button background.

= 7.0.1.14 =
* Added vertical panning animation for tall slideshow images.
* Improved player scaling for mobile landscape.

= 7.0.1.13 =
* Redesigned Princess Keilah Studio into a full-width bottom-docked app UI.
* Added custom SVG progress ring.
* Added title bar overlays and fluid minimize/maximize controls.

= 7.0.1.10 =
* Added master-site control logic on subsite admin panels.
* Added summary blocks to subsite setting tabs.
* Moved Kaleeyon Media SEO ping control into the admin dashboard.

= 7.0.1.6 =
* Refactored codebase to meet WordPress plugin standards.
* Added shortcode support for Princess Keilah Studio.
* Added Elementor support for music, playlists, and slideshow blocks.
* Added Kaleeyon Media SEO support for single-site installs.

= 6.9.6.0 =
* Rebranded to Big King Media.
* Moved network menu to Settings for admin compliance.
* Implemented stricter late escaping on frontend templates.
* Switched to `wp_add_inline_script` for safer data passing.

= 6.6.18 =
* Added backend caption toggle in the media modal.
* Added frontend caption toggle in Photofall.
* Improved masonry CSS for natural image ratios.
* Hardened icon CSS for video and audio items.

= 6.6.14 =
* Added proxy-aware SEO crawler.
* Added custom post type indexing.
* Improved lightbox source-post visibility for SEO.

= 6.6.10 =
* Added frontend hide and delete controls for admins.
* Added frontend uploader for authorized users.
* Added live and hidden tabs in Photofall.

= 6.6.2 =
* Introduced `tbfbkm_usage_map` database table for fast SEO queries.
* Added real-time `save_post` crawler for media usage tracking.

= 6.5.0 =
* Improved frontend and dashboard security escaping.
* Replaced legacy inline scripts with safer WordPress methods.
* Updated Gutenberg React architecture.
* Fixed server 500 issues during heavy image uploads.
* Synced versioning and text domain support.

= 6.1.1 =
* Standardized WordPress directory compliance updates.
* Added Vikinger frontend upload bridge.
* Added strict admin-only upload enforcement.
* Added native audio support for `.mp3` and `.wav`.
* Replaced CSS-only filtering with database-driven queries.
* Added smart Photofall toolbar behavior.
* Rebuilt dashboard importer with visual progress tracking.

= 6.0.0 =
* Introduced the first Photofall masonry gallery layout.
* Added the initial global network media database architecture.
* Added a unified cross-site WordPress media modal tab.