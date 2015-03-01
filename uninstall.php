<?php 
if( !defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN') ) 
	exit;

function cer_uninstall(){
	global $wpdb;
	$wpdb->query( "DROP TABLE ". $wpdb->prefix . "custom_error_responses;" );
	delete_option( 'cer_db_version' );
} cer_uninstall();
?>