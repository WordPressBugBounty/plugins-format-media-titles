=== Format Media Titles ===
Contributors: dgwyer, wpgoplugins
Tags: media, title, alt text, images, metadata
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically clean up titles for new media uploads and copy the result to selected attachment fields.

== Description ==

Format Media Titles applies predictable formatting rules whenever a new attachment is added to the WordPress media library.

Use it to:

* Replace hyphens, underscores, periods, tildes, or plus signs with spaces.
* Capitalize every word, the first word, use upper/lowercase, or leave case unchanged.
* Keep selected words such as `SEO`, `PDF`, or `UK` uppercase.
* Copy the formatted title to alternative text, caption, and description fields.
* Preserve existing settings when updating from earlier releases.

The plugin changes WordPress attachment metadata only. It does not rename or modify the uploaded file.

= Free and Pro =

Format Media Titles handles new uploads. [SEO Media Manager](https://wpgoplugins.com/plugins/seo-media-manager/) adds more cleanup rules, original-filename sources, safe batch processing for existing media, automatic updates, and priority support.

SEO Media Manager imports Format Media Titles settings on first activation, including the uppercase-word list introduced in version 1.1.0.

== Installation ==

1. Install Format Media Titles from Plugins > Add New in WordPress.
2. Activate the plugin.
3. Open Settings > Format Media Titles.
4. Choose the formatting rules and optional metadata fields.
5. Upload a new media item to apply the saved rules.

== Frequently Asked Questions ==

= Does this rename the original file? =

No. It updates WordPress attachment metadata and leaves the uploaded file unchanged.

= Can it process media already in my library? =

The free plugin processes new uploads. SEO Media Manager adds safe batch processing for selected items or the complete existing library.

= How do uppercase words work? =

Enter comma-separated words such as `SEO, PDF, UK`. After the normal capitalization rule runs, matching whole words are restored to uppercase. Partial matches inside longer words are not changed.

= What happens to my settings when I update? =

Existing settings are retained. The new uppercase-word list starts empty until you add words.

== Screenshots ==

1. A default WordPress media upload before title formatting.
2. The same workflow with Format Media Titles applying saved rules.
3. Additional formatting controls available in SEO Media Manager.
4. SEO Media Manager batch processing for existing attachments.
5. SEO Media Manager account, documentation, and support links.

== Upgrade Notice ==

= 1.1.0 =

Adds protected uppercase words, modernizes the settings and update logic, and supports current WordPress and PHP releases while retaining existing settings.

== Changelog ==

= 1.1.0 - 2026-08-11 =

* Added a rule for keeping selected whole words such as SEO or PDF uppercase.
* Modernized the settings screen and sanitized every saved field.
* Added Unicode-aware capitalization with safe fallbacks.
* Prevented empty titles and partial metadata updates when an attachment update fails.
* Preserved the existing SEO Media Manager handover and settings schema.
* Added automated unit, compatibility, packaging, and WordPress smoke tests.
* Updated documentation and compatibility metadata for current WordPress releases.

= 1.0.0 - 2020-07-14 =

* Updated compatibility for WordPress 5.4.2.

= 0.54 =

* Updated the settings screen.

= 0.30 =

* Fixed unexpected character removal during formatting.

= 0.26 =

* Added optional caption and description output.

= 0.25 =

* Added optional alternative-text output and translation support.

= 0.1 =

* Initial release.
