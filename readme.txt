=== Random User IDs ===
Contributors: DaveFX
Donate link: https://paypal.me/davefx
Tags: security, user ids
Requires at least: 3.1
Tested up to: 4.9
Stable tag: 20170720
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

This WordPress plugin randomizes the user_id for the user created on WordPress setup, removing one potential attack factor from the site.

Since WordPress version 4.9, it also randomizes the ID for all other users created after its setup.
In WordPress versions prior to 4.9, this feature requires installing the patch from WP Issue https://core.trac.wordpress.org/ticket/40545

== Changelog ==

= 20170720 =

* Updating documentation to reflect changes incoming with WordPress version 4.9.

= 20170526 =

* Now the maximum generated ID is Javascript's MAX_SAFE_INTEGER / 2, so we shouldn't generate problems with the Javascript layer.



