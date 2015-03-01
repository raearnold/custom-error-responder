=== Custom Error Responder ===
Contributors: raearnold
Tags: error, gone, robots
Requires at least: 3.6
Tested up to: 4.1.1
Stable tag: 0.0.1
License: GPLv2 or later

A plugin that allows you to configure custom error responses to URIs that would otherwise trigger a 404 on your blog.


== Description ==

**Currently in alpha**

This plugin allows you to specify an error response to URIs that currently return as 404 on your site.

Current capabilities:
Auto-add URIs of posts that are moved to the trash, set as `410` response. It will remove the custom response if you untrash the post.
Manually add URIs via the settings page.

The only response code currently fully supported is `410`, for permanently removed content. 


== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin settings can be accessed via the 'Settings' menu in the administration area
 
