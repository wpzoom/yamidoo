<?php
/**
 * Uninstall cleanup: remove the plugin's stored option.
 *
 * @package Yamidoo
 */

// Exit if not called by WordPress during uninstall.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'yamidoo_settings' );

// Multisite: clean the option on every site.
if ( is_multisite() ) {
	$yamidoo_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( (array) $yamidoo_site_ids as $blog_id ) {
		switch_to_blog( $blog_id );
		delete_option( 'yamidoo_settings' );
		restore_current_blog();
	}
}
