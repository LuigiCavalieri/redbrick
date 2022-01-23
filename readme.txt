=== RedBrick ===
Author URI: https://luigicavalieri.com/
Plugin URI: https://wordpress.org/plugins/redbrick/
Contributors: _luigi
Tags: anti-spam, spam, comments
Requires at least: 5.5
Tested up to: 5.9
Requires PHP: 7.0
Stable tag: 1.0.4
License: GPLv3
License URI: https://opensource.org/licenses/GPL-3.0

Simple anti-spam plugin for WordPress blogs.


== Description ==

RedBrick is able to block most of the automated spam comments a WordPress blog receives daily.

It is based upon a score system that ensures spam comments are blocked as soon as possible, so as to minimise the number of checks each comment must go through. When in doubt, RedBrick always holds the comment in the moderation queue. Comments identified as spam, however, never reach the database.

As a last resource in discerning the kind of comment under examination, RedBrick takes account also of the comment author's history.

**The plugin is designed to work on low-to-medium traffic blogs.**


== Installation ==

Upload in the 'plugins' folder, and activate.

= To Uninstal =

* Deactivate RedBrick from the 'Plugins' screen.
* Click on 'Delete'.


== Screenshots ==

1. Short summary displayed in the WordPress Dashboard.


== Upgrade Notice ==

= 1.0 =

RedBrick is born.


== Changelog ==

= 1.0.4 (9 August 2021) =

Fixed a bug where the ID of the last processed comment shown in the dashboard didn't reset when a spam comment was processed.


= 1.0.3 (2 August 2021) =

If available, RedBrick shows in the WordPress Dashboard also the ID of the last processed comment.


= 1.0.2 (22 July 2021) =

Fixed a PHP error that could arise when spam was detected.


= 1.0.1 (20 July 2021) =

Fixed a PHP notice and improved the detection of hyperlinks with multiple attributes.


= 1.0 (19 July 2021) =

RedBrick is born.