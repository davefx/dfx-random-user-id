=== Random User IDs ===
Contributors: DaveFX
Donate link: https://paypal.me/davefx
Tags: security, user ids
Requires at least: 3.1
Tested up to: 6.4.2
Stable tag: 20231215.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

This WordPress plugin randomizes the user_id for the user created on WordPress setup, removing one potential attack factor from the site.

Since WordPress version 4.9, it also randomizes the ID for all other users created after its setup.
In WordPress versions prior to 4.9, this feature requires installing the patch from WP Issue https://core.trac.wordpress.org/ticket/40545

== How it works ==

Once activated, the plugin will immediately replace the ID for the default admin user (with user ID 1). By default, the plugin will use random user IDs between 1 and 4503599627370495 (to ensure compatibility with Javascript code using the user ID).

All newly created users from that moment will be generated with a random user ID in the defined range.

== How to customize the range for new user IDs ==

You can customize the range used by the plugin for the random generated user IDs by using the WordPress filters dfx_random_user_id_max_id and dfx_random_user_id_min_id.

For example, if you want to have all your user IDs between 1000 and 9999 you can add the following lines to your theme’s functions.php file:

`function set_dfx_max_user_id( $default_max_id ) {`
`    return 9999;`
`}`
`add_filter( 'dfx_random_user_id_max_id', 'set_dfx_max_user_id' );`
` `
`function set_dfx_min_user_id( $default_max_id ) {`
`    return 1000;`
`}`
`add_filter( 'dfx_random_user_id_min_id', 'set_dfx_min_user_id' );`


Probably, you’ll want to add these lines to your code before activating the plugin, so your new random main admin user ID is inside your desired range.

== Changelog ==

= 20231215.1 =

* No actual changes. Just bumping to confirm it's still active

= 20201115 =

* Adding new filter dfx_random_user_id_min_id to customize the minimum allowed user ID number

= 20190125 =

* Adding new filter dfx_random_user_id_max_id to customize the maximum allowed user ID number

= 20170720 =

* Updating documentation to reflect changes incoming with WordPress version 4.9.

= 20170526 =

* Now the maximum generated ID is Javascript's MAX_SAFE_INTEGER / 2, so we shouldn't generate problems with the Javascript layer.



