=== Future Revisions ===

Contributors: wordpressdotorg
Tested up to: 6.9
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tags: revisions, editorial, publishing

Public historical revisions and future-revision fork/merge for WordPress.

== Description ==

This plugin lets editors mark a WordPress revision as public and serve it at `/post-slug/revision/{id}/`. It also lets editors fork a published post into a draft and merge that draft back on publish.

Each feature is optional per post type:

* `public-revisions` turns on the public flag, public URL, and Public UI.
* `future-revisions` turns on fork, merge, and fork UI.

`post` and `page` get both features. Other types opt in with `add_post_type_support()`.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate **Future Revisions**.
3. Flush permalinks if public revision URLs 404.

== Changelog ==

= 0.1.0 =

* Initial release.
