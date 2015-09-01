<?php
/*
Plugin Name: Bean Instagram
Plugin URI: http://themebeans.com/plugins/bean-instagram
Description: Enables an Instagram feed widget. You must register an <a href="http://instagram.com/developer/" target="_blank">Instagram App</a> to retrieve your client ID and secret. <a href="http://themebeans.com/tutorials/retrieving-your-instagram-id-secret/">Learn More</a>
Version: 2.1
Author: ThemeBeans
Author URI: http://themebeans.com
*/

// DON'T CALL ANYTHING
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

define('BEAN_INSTAGRAM_PATH', plugin_dir_url( __FILE__ ));
define('BEAN_INSTAGRAM_PLUGIN_FILE', __FILE__ );


/*===================================================================*/
/*
/* PLUGIN FEATURES SETUP
/*
/*===================================================================*/
$bean_plugin_features[ plugin_basename( __FILE__ ) ] = array(
        "updates"  => false // Whether to utilize plugin updates feature or not
    );


if ( ! function_exists( 'bean_plugin_supports' ) ) 
{
    function bean_plugin_supports( $plugin_basename, $feature ) 
    {
        global $bean_plugin_features;

        $setup = $bean_plugin_features;

        if( isset( $setup[$plugin_basename][$feature] ) && $setup[$plugin_basename][$feature] )
            return true;
        else
            return false;
    }
}




/*===================================================================*/
/*
/* PLUGIN UPDATER FUNCTIONALITY
/*
/*===================================================================*/
define( 'EDD_BEANINSTAGRAM_TB_URL', 'http://themebeans.com' );
define( 'EDD_BEANINSTAGRAM_NAME', 'Bean Instagram' );

if ( bean_plugin_supports ( plugin_basename( __FILE__ ), 'updates' ) ) : // check to see if updates are allowed; only import if so

//LOAD UPDATER CLASS
if( !class_exists( 'EDD_SL_Plugin_Updater' ) ) 
{
    include( dirname( __FILE__ ) . '/updates/EDD_SL_Plugin_Updater.php' );
}
//INCLUDE UPDATER SETUP
include( dirname( __FILE__ ) . '/updates/EDD_SL_Activation.php' );


endif; // END if ( bean_plugin_supports ( plugin_basename( __FILE__ ), 'updates' ) )




/*===================================================================*/
/* UPDATER SETUP
/*===================================================================*/
function beaninstagram_license_setup() 
{
	add_option( 'edd_beaninstagram_activate_license', 'BEANINSTAGRAM' );
	add_option( 'edd_beaninstagram_license_status' );
}
add_action( 'init', 'beaninstagram_license_setup' );

function edd_beaninstagram_plugin_updater() 
{
    // check to see if updates are allowed; don't do anything if not
    if ( ! bean_plugin_supports ( plugin_basename( __FILE__ ), 'updates' ) ) return;

	//RETRIEVE LICENSE KEY
	$license_key = trim( get_option( 'edd_beaninstagram_activate_license' ) );

	$edd_updater = new EDD_SL_Plugin_Updater( EDD_BEANINSTAGRAM_TB_URL, __FILE__, array( 
			'version' => '2.0',
			'license' => $license_key,
			'item_name' => EDD_BEANINSTAGRAM_NAME,
			'author' 	=> 'ThemeBeans'
		)
	);
}
add_action( 'admin_init', 'edd_beaninstagram_plugin_updater' );




/*===================================================================*/
/* ADD SETTINGS LINK TO PLUGINS PAGE
/*===================================================================*/
define( 'BEANINSTAGRAM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

add_filter( 'plugin_action_links', 'beaninstagram_plugin_action_links', 10, 2 );

function beaninstagram_plugin_action_links( $links, $file ) {
	if ( $file != BEANINSTAGRAM_PLUGIN_BASENAME )
		return $links;

	$settings_link = '<a href="' . menu_page_url( 'bean-instagram', false ) . '">'
		. esc_html( __( 'Settings', 'bean-instagram' ) ) . '</a>';

	array_unshift( $links, $settings_link );

	return $links;
}




/*===================================================================*/
/* DEACTIVATION HOOK - REMOVE OPTION
/*===================================================================*/
function beaninstagram_deactivate() 
{
	delete_option( 'edd_beaninstagram_activate_license' );
	delete_option( 'edd_beaninstagram_license_status' );
}
register_deactivation_hook( __FILE__, 'beaninstagram_deactivate' );




/*===================================================================*/
/* BEGIN BEAN INSTAGRAM PLUGIN
/*===================================================================*/
require_once('bean-instagram-widget.php');
?>