=== DFX Random User IDs ===
Contributors: DaveFX
Donate link: https://paypal.me/davefx
Tags: security, user ids
Requires at least: 4.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 20261121
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
This WordPress plugin randomizes the user_id for the user created on WordPress setup, removing one potential attack factor from the site.

== How it works ==

Once activated, the plugin will immediately replace the ID for the default admin user (with user ID 1). By default, the plugin will use random user IDs between 1 and 4503599627370495 (to ensure compatibility with Javascript code using the user ID).

All newly created users from that moment will be generated with a random user ID in the defined range.

== Settings page ==

Settings → Random User IDs (Network Admin → Settings on multisite, because users are shared by the whole network) shows:

* The site's status: whether a user with ID 1 exists, how many users have IDs below 1,000, and how many have an ID that follows another user's. These are the IDs an attacker can guess.
* The range for new user IDs, which you can change there. The range must hold at least ten IDs for every user, so that finding a free random ID stays quick. Existing users keep their IDs.
* A "More plugins" tab with the author's other plugins.

== How to customize the range for new user IDs ==

The easiest way is the settings page. You can also customize the range with the WordPress filters dfx_random_user_id_max_id and dfx_random_user_id_min_id, which take precedence over the settings page.

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

== External services ==

= Author's plugin catalog (davefx.com) =

The "More plugins" tab of the settings page lists the author's other plugins. When an administrator opens that tab, the plugin downloads the list from https://davefx.com/plugins.json, at most once a week. Nothing about the site or its users is sent: the request identifies itself only as DFX-Plugin-Showcase and its version, although, as with any web request, davefx.com's server receives the IP address of the server making it. If the file cannot be reached, a copy bundled with the plugin is shown. Nothing else in the plugin connects to any external service.

* Privacy Policy: https://davefx.com/en/privacy-policy/

== Changelog ==

= 20261121 =

* New settings page (Settings → Random User IDs): the site's status, the range for new user IDs, and the author's other plugins
* The ID chosen for the first user on activation now respects the minimum ID too
* Security: the activation notice's "Dismiss" link now needs a nonce and the right capability, and the notice escapes the user's nickname
* The activation notice is shown only to the administrators who manage the plugin (in Network Admin on multisite)
* Code cleaned up to the WordPress coding standards

= 20260601 =

* Tested up to WordPress 7.0
* Declared the minimum required PHP version (7.4)
* Added an automated test suite and continuous integration against current WordPress and PHP versions

= 20201115 =

* Adding new filter dfx_random_user_id_min_id to customize the minimum allowed user ID number

= 20190125 =

* Adding new filter dfx_random_user_id_max_id to customize the maximum allowed user ID number

= 20170720 =

* Updating documentation to reflect changes incoming with WordPress version 4.9.

= 20170526 =

* Now the maximum generated ID is Javascript's MAX_SAFE_INTEGER / 2, so we shouldn't generate problems with the Javascript layer.



