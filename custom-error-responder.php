<?php
/*
Plugin Name: Custom Error Responder
Plugin URI: 
Description: Allows you to specify a custom HTTP response for any 404 status request
Version: 0.0.1
Author: Rae Arnold
Author URI: raearnold.com
License: GPL2
*/


// Security check
defined( 'ABSPATH' ) or die('410? How about a 403!');

class Custom_Error_Responder {
	const version = 1;
	private static $tableName;
	private static $default_errors = array(
		'410'=>'Sorry, the content you requested has been permanently deleted from this website.'
	);
	
	function __construct() {
		self::$tableName = $GLOBALS['wpdb']->prefix . 'custom_error_responses';
		
		register_activation_hook( __FILE__, array( 'Custom_Error_Responder', 'cer_install' ) );
		
		if( is_admin() ) add_action( 'admin_menu', array( 'Custom_Error_Responder', 'load_menu' ) );
		else add_action( 'template_redirect', array( 'Custom_Error_Responder', 'handle_error' ) );
		
		// Auto 410 deleted posts
		add_action( 'trashed_post', array( 'Custom_Error_Responder', 'auto_add_410' ) );
		add_action( 'untrashed_post', array( 'Custom_Error_Responder', 'auto_remove_uri' ) );
	}
	
	public static function cer_install() {
		global $wpdb;
		error_log('Installing Custom Error Responder',0);

		if($wpdb->get_var("SHOW TABLES LIKE '".self::$tableName."'") != self::$tableName) {
			$sql = "CREATE TABLE `".self::$tableName."` (
				cer_id BIGINT(20) unsigned NOT NULL AUTO_INCREMENT,
				cer_uri VARCHAR(512) NOT NULL,
				cer_response SMALLINT unsigned NOT NULL DEFAULT 410,
				is_auto BOOLEAN NOT NULL DEFAULT 1,
				PRIMARY KEY  (cer_id),
				KEY cer_response (cer_response)
			);";
	
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
	
			add_option( 'cer_db_version', self::version );
		}
	}
	
	
	function handle_error() {
		if( !is_404() ) return;
			
		$full_request_uri = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		$full_request_uri = rawurldecode( $full_request_uri );
		
		if ( $matches = self::get_uri($full_request_uri) ) {
			// Lazy handling to get the first response (even though there should only ever be 1)
			$match = reset($matches);
			
			status_header( $match->cer_response );
			do_action( 'respond_with_custom_error' );
			if( ! locate_template( "{$match->cer_response}.php", true ) ) {
				echo self::$default_errors[$match->cer_response];
			}
			exit;
		}
		
		// TODO: else log 404?
		return;
	}	
	
	
	private static function add_uri( $uri, $status, $auto = true ){	// just supply the link
		global $wpdb;
		
		// Don't allow duplicates
		if( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `".self::$tableName."` WHERE `cer_uri` = %s", $uri ) ) ) {
			return false;
		}
		
		$wpdb->insert( self::$tableName, array( 'cer_uri' => $uri, 'cer_response' => $status, 'is_auto' => $auto ) );
		
		return true;
	}	
	
	public static function auto_add_410( $post_id ) {		
		if ( ( $permalink = get_permalink( $post_id ) ) && get_post_type( $post_id ) != 'revision' ) {
			return self::add_uri( $permalink, 410, true );
		}
		return false;
	}
	
	private static function remove_uri( $uri ) {
		global $wpdb;
		return $wpdb->query( $wpdb->prepare( "DELETE FROM `".self::$tableName."` WHERE `cer_uri` = %s", $uri  ) );
	}
	
	public static function auto_remove_uri( $post_id ) {		
		if ( ( $permalink = get_permalink( $post_id ) ) ) {
			return self::remove_uri( $permalink );
		}
		return false;
	}	
	
	private static function get_uris(){
		global $wpdb;
		return $wpdb->get_results( "SELECT * FROM `".self::$tableName."`", OBJECT_K );
	}
	
	private static function get_uri( $uri ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT `cer_uri`, `cer_response` FROM `".self::$tableName."` WHERE  `cer_uri` = %s", $uri  ), OBJECT_K );		
	}
	
	//////////////////////////////////////////////////
	// ADMIN
	//////////////////////////////////////////////////
	public static function load_menu() {
		add_options_page( 
			'Custom Error Responder', 
			'Custom Error Responses', 
			'manage_options', 
			'custom-error-responder', 
			array( 'Custom_Error_Responder', 'display_page'));
	}
	
	public static function display_page() {

		if ( isset( $_POST['cer_add_link_field'] ) ) {
			check_admin_referer( 'cer-settings' );
			$added_success = array();
			$added_error = array();
			foreach( preg_split ('/\R/', $_POST['cer_add_link_field']) as $link ) {
				$link_data = str_getcsv($link);
				$uri = trim( stripslashes( $link_data[1] ) );
				$status = trim( $link_data[0] );
				
				// TODO: check validity of URL against permalink scheme?
				if ( $uri && $status && self::add_uri( $uri, $status, false ) ) {
					$added_success[] = $status . ": " . htmlspecialchars( $uri );
				} else {
					$added_error[] = $link;
				}
			}
		}

?>

    <div class="wrap">
        <h2>Custom Error Responder Settings</h2>
        
        <?php
	    if (count($added_success)): ?>
	    	<div class="updated">
		    	<p><strong><?php echo count($added_success); ?></strong> URIs were successfully added!</p>
		    	<p><code><?php 
			    	echo implode( '<br>', $added_success );
		    	?></code></p>
	    	</div>	
	    <?php
	    endif;
	    if (count($added_error)): ?>
	    	<div class="error">
		    	<p>There was an error adding <strong><?php echo count($added_error); ?></strong> URIs. 
		    		They are likely already in the list, or invalid input.</p>
		    	<p><code><?php 
			    	echo implode( '<br>', $added_error );
		    	?></code></p>
	    	</div>		      
	    <?php endif; ?>
        
        <p>404s are so passé. Custom Error Responder v.0.0.1 pays attention to when you move a post or page to the trash, and returns a 410 error instead, letting your users know that the content they requested has been removed.</p>
        
        <p>Stay tuned for updates that will allow you to easily remove these auto-tracked URIs and add your own, handle other statuses, and much more.</p>
        
        <?php
	      $links = self::get_uris();
	      $links_length = count($links);
	      if ( $links_length ):
        ?>
		<ul class="subsubsub">
			<li class="title">Configured URIs |</li>
			<li class="all"><a href="#" class="current">All <span class="count">(<?php echo $links_length; ?>)</span></a></li>
		</ul>
		<table class="wp-list-table widefat fixed">
			<thead>
				<tr>
					<th scope="col" id="cer-uri" class="manage-column column-uri">URI</th>
					<th scope="col" id="cer-response" class="manage-column column-response">Response</th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th scope="col" id="cer-uri" class="manage-column column-uri">URI</th>
					<th scope="col" id="cer-response" class="manage-column column-response">Response</th>
				</tr>
			</tfoot>
			<tbody id="the-list">
				<?php 
				$i = 1;
				foreach ($links as $link) {
					printf('<tr id="cer-%1$d" class="%2$s%3$s"><td>%4$s</td><td>%2$s</td></tr>', 
						$link->cer_id, 
						$link->cer_response,
						(++$i % 2 > 0 ? " alternate" : ""),
						$link->cer_uri 
						);
					
				} 
				?>
			</tbody>
		</table> 
		<?php else: ?>
		<div class="error"><p>No URIs are currently configured to use custom responses. Either manually add them below, or trash one of your posts to get started.</p></div>
		<?php endif; ?>

		<h3>Manually Add URIs</h3>
		<form action="" method="post">
			<p>Add one entry per line in the format: <code>[status],[fully qualified URI]</code></p>
			
			<fieldset>
				<textarea name="cer_add_link_field" id="field-cer_add_link_field" rows="8" cols="80" 
					placeholder="410,http://<?php echo $_SERVER['HTTP_HOST'];?>/news/hello-world"></textarea>
					
				<?php wp_nonce_field( 'cer-settings' ); ?>
				<p>
					<input class="button button-primary" type="submit" id="button-cer_add_link_submit" 
						name="cer_add_link_submit" value="Add Custom Response URIs" />
				</p>
			</fieldset>
		</form>

		<h3>Default Messages</h3>
		
		<p>The plugin will look in your theme for a template named after the response code (e.g. <code>410.php</code>). If it doesn't find one, a plain page will appear with a default message.</p>
		<h4>Current Theme Settings</h4>
		<dl>
		<?php
		foreach ( self::$default_errors as $k=>$v ) {
		?>
			<dt><?php echo $k; ?></dt>
			<?php
			if( locate_template( "$k.php" ) ) { ?>
				<dd>Your theme contains a <code><?php echo $k; ?>.php</code> template file.</dd>
			<?php 
			} else { ?>
				<dd><?php echo $v; ?><small> (default message)</small></dd>
			<?php
			}
			?>
		<?php	
		}	
		?>
		</dl>
    </div>

<?php
		
	}
		
	
} new Custom_Error_Responder();


/*  Copyright 2015  Rachael Arnold  (email : rae.arnold@gmail.com )

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
?>