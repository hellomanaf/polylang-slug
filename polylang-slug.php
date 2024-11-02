<?php
/**
Plugin Name: Polylang Slug
Plugin URI: https://manaf.in/
Description: Polylang Slug Addon
Author: Abdul Manaf M
Author URI: https://manaf.in/
Text Domain: polylang-slug
Version: 1.0.0
Since: 1.0.0
Requires WordPress Version at least: 5.6
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
**/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {	
	exit;
}

add_action( 'admin_notices', 'pre_check_before_installing_polylang' );
include_once(ABSPATH.'wp-admin/includes/plugin.php');
function pre_check_before_installing_polylang() 
{
	if ( !is_plugin_active( 'polylang/polylang.php') ) 
	{
		global $pagenow;
    	if( $pagenow == 'plugins.php' )
    	{
           echo '<div id="error" class="error notice is-dismissible"><p>';
           echo __( 'Polylang is require to use Polylang Slug Addon' , 'wp-event-manager-zoom');
           echo '</p></div>';	
    	}
    	return true;
	}
}

/**
 * Polylang_Slug class.
 */
class Polylang_Slug 
{
	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.0.0
	 */
	private static $_instance = null;

	/**
	 * Main TC_CF7_Addon Instance.
	 *
	 * Ensures only one instance of TC_CF7_Addon is loaded or can be loaded.
	 *
	 * @since  1.0.0
	 * @static
	 * @see TC_CF7_Addon()
	 * @return self Main instance.
	 */
	public static function instance() 
	{
		if ( is_null( self::$_instance ) ) 
		{
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor - get the plugin hooked in and ready
	 */
	public function __construct() 
	{
		include_once 'includes/polylang-post-slug.php';
		include_once 'includes/polylang-term-slug.php';
		include_once 'includes/polylang-media-sync.php';
	}
	
}

$GLOBALS['polylang_slug'] =  Polylang_Slug::instance();