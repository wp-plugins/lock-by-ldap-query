=== Plugin Name ===
Contributors: george_michael
Donate link: 
Tags: 
Requires at least: 3.5.2
Tested up to: 4.3.0
Stable tag: 1.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Lock a page down so that it can only be viewed by certain LDAP groups

== Description ==

With this plugin, you can set an LDAP query on a page or post so that only users returned from that query can see the page/post.

If users aren't in the query results, they will see a basic message and you can also set a custom message that will also be displayed. This custom message can contain html and is set on a per page basis, so different pages can have different messages.

Admin users aren't affected by the query results and the blocking is done during the_content, so if a users that can edit the post can still do so.

This was originally created because we had a contact form that we wanted only certain AD users to access. It seemed easier to limit it this way than give out a password to only certain people, hoping they never shared it.

== Installation ==

1. Upload `lock-by-ldap-query.php` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==


== Screenshots ==

1. The main settings page.
2. The meta box when editing a post.

== Changelog ==

= 1.0.1 =
* Removed PHP4 constructor from class as WP 4.3 deprecated that type of constructor.

= 1.0.0 =
* Initial version.

== Upgrade Notice ==

= 1.0.1 =
* Required upgrade if using WP 4.3.