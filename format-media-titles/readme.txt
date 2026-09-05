=== SEO Media Manager ===
Contributors: dgwyer, wpgoplugins
Tags: media, images, alt text, seo, metadata
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically format media titles and optionally copy the result to alternative text, captions, and descriptions.

== Description ==

SEO Media Manager gives site owners consistent, configurable media metadata without manually editing every attachment.

The Free edition includes:

* Format metadata automatically when media is uploaded.
* Replace hyphens, underscores, periods, tildes, and plus signs with spaces.
* Control letter case and capitalisation.
* Keep selected whole words such as `SEO`, `PDF`, or `UK` uppercase.
* Preview the title and copied metadata while changing rules, before saving.
* Copy the formatted title to alternative text, captions, and descriptions.
* Filter the default settings, formatted title, and alternative text in custom code.

SEO Media Manager Pro adds:

* Batch-process the complete Media Library in durable 20-item background jobs.
* Limit a batch job to images, SVG, PDF, documents, audio, or video.
* Select a custom set of attachments to process.
* Leave the settings screen while a job continues, then return to its saved progress.
* Cancel an active job safely after its current group finishes.
* Use original filenames or a fixed title as the source.
* Replace more punctuation, numbers, and custom phrases.
* Split titles at capital letters or number groups.
* Receive paid updates and priority support through Freemius.

== Installation ==

1. Install SEO Media Manager from the WordPress plugin directory, or upload a Pro ZIP from your WPGO Plugins account.
2. Activate SEO Media Manager.
3. Open SEO Media Manager > Settings and review the formatting rules.
4. Pro users can activate a licence from the Account screen to enable the paid features, updates, and support.

Back up the site database before batch-changing existing media metadata.

== Frequently Asked Questions ==

= Does batch processing change the original media files? =

No. It updates WordPress attachment metadata only. The stored file is not renamed or modified.

= Can I process only selected attachments? =

Yes, with Pro. Open the Batch processing tab, choose Custom selection, select the attachments, and start the background job.

= Can I process only one kind of media? =

Yes. The Pro batch screen can limit either the complete library or a custom selection to images, SVG, PDF, documents, audio, or video. The chosen filter is saved with the job.

= Do I need to keep the settings screen open? =

No. Action Scheduler processes the library in small background groups and saves progress between requests. You can leave the screen and return later. As with other WordPress scheduled tasks, a site with WP-Cron disabled needs a working server-side cron runner.

= What happens when I deactivate a Pro licence? =

The plugin stays active and shows the Free interface. Your paid settings remain stored, but paid rules and batch processing are unavailable until a valid licence is activated again.

= Will existing Format Media Titles settings be kept? =

Yes. Version 1.3.0 imports the established `fmt_options` settings and keeps a compatibility copy for a safe rollback. The WordPress.org slug remains `format-media-titles`, so existing sites continue to receive updates from the same listing.

= Are developer filters available? =

Yes. The established seo_media_manager_defaults, seo_media_manager_title, and seo_media_manager_alt filters are preserved.

== Screenshots ==

1. Free formatting settings with the complete four-step workflow and editable live preview.
2. A newly uploaded Media Library item before its generated title and metadata are cleaned up.
3. The same Media Library item after Free formatting has populated the chosen fields.
4. Pro version: the product Home with quick-start guidance, Pro tools, support, and other WPGO plugins.
5. Pro version: the full formatting workspace with original-filename rules and the live transformation preview.
6. Pro version: source, phrase-removal, parsing, and extended cleanup controls shown in detail.
7. Pro version: background-processing scope and the file-type filter for a selected media set.
8. Pro version: a running background job with saved progress, recent results, and safe cancellation.
9. Pro version: a completed background job with final totals and its retained recent results.

== Changelog ==

= 1.3.0 =
* Merge the former Free and Pro codebases into one SEO Media Manager source and Freemius product while keeping the `format-media-titles` WordPress.org slug and the `seo-media-manager` Pro folder.
* Add a compatibility loader that keeps existing Free installations active during the main-file transition and preserves their saved settings.
* Make an unlicensed Pro package fall back to the Free interface; activating or deactivating the licence now switches the available feature set without replacing the plugin.
* Keep Pro settings stored while the licence is inactive so they return after reactivation.
* Add the redesigned four-step settings screen and editable live preview to Free.
* Add durable server-side background processing for large existing Media Libraries, powered by Action Scheduler.
* Save progress between 20-item groups so work can continue after the settings screen is closed or refreshed.
* Add a Pro file-type filter for images, SVG, PDF, documents, audio, and video.
* Add safe cancellation, duplicate-worker protection, and a clear saved progress summary.
* Keep each job consistent by using the selected settings and library boundary captured when it starts.
* Add a top-level Home, Settings, and New Features experience with clearer first-run guidance and a privacy-safe support summary.
* Add an outcome-led Free-to-Pro comparison on Home with a real background-processing preview and transparent annual pricing, while keeping the Pro Home focused on its available tools.
* Add the shared More from WPGO Plugins companion section to the bottom of Home.
* Redesign the formatting-rules screen and add a live filename preview that follows the current unsaved settings.
* Show a short Settings saved confirmation without keeping a timestamp or repeating it after refresh.
* Keep notices raised by this or another plugin out of the header across every SEO Media Manager-owned admin screen.
* Show the discounted first-year price and regular annual renewal price together on the in-plugin upgrade screen, and apply the matching licence-tier coupon at checkout.
* Show the SEO Media Manager product mark in the in-plugin pricing header.
* Clarify when a new upload uses its WordPress attachment title or original filename, and confirm compatibility with WordPress 7.1.

= 1.2.0 =
* Add a protected uppercase-word rule for abbreviations such as SEO, PDF, and UK.
* Import the uppercase-word list from Format Media Titles 1.1.0 during a free-to-Pro migration.
* Apply the new rule to automatic uploads and safe existing-library batches.

= 1.1.2 =
* Keep the installed premium functionality running after a subscription expires while updates and support remain licence-controlled.
* Reject malformed batch scopes and attachment IDs before any media is changed.
* Prevent formatting rules from overwriting attachment metadata with an empty title.
* Avoid a partial alternative-text update when the attachment post update fails.
* Improve handling of the plugin folder and main file during Freemius updates.

= 1.1.1 =
* Import existing Format Media Titles settings without deleting the free plugin configuration.
* Prevent both Free and Pro upload callbacks from formatting the same attachment.

= 1.1.0 =
* Process existing media in sequential, bounded server-side batches instead of loading the complete library and firing concurrent requests.
* Require administrator capability and a valid nonce for batch mutation requests.
* Return structured JSON results with error handling and accessible progress feedback.
* Add Unicode-aware title parsing, casing, capitalization, and whitespace normalization.
* Prevent duplicate image and gallery title attributes.
* Modernize the settings screen and update WPGO Plugins account and support links.
* Raise the supported PHP baseline to 7.4 for current WordPress compatibility.

= 1.0.6 =
* Correct the Pro plugin folder name for Freemius installation and updates.

= 1.0.4 =
* Gate the premium-only runtime with Freemius licensing so the deployment processor can generate distinct free and paid artifacts.

= 1.0.0 =
* Migrate licensing and updates from the legacy EDD integration to Freemius.
* Preserve the established settings and formatting behavior.
* Harden batch processing, reset actions, input validation, and output escaping.
