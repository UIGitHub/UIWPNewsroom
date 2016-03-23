<?php
/*
Plugin Name:       UI Newsroom
Plugin URI:        http://www.urbanimmersive.com
Description:       UI Newsroom automatically sync your Urbanimmersive Newsrooms to your WordPress.
Version:           0.9.1
Author:            Urbanimmersive
Author URI:        http://www.urbanimmersive.com
Text Domain:       ui-newsroom
Domain Path:       /languages
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}


define( 'UINEWSROOM_VERSION', '0.9.1' );
define( 'UINEWSROOM__MINIMUM_WP_VERSION', '4.0' );
define( 'UINEWSROOM__PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'UINEWSROOM__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require 'plugin-update-checker/plugin-update-checker.php';

$className = PucFactory::getLatestClassVersion('PucGitHubChecker');
$myUpdateChecker = new $className(
    'https://github.com/UIGitHub/UIWPNewsroom/',
    __FILE__,
    'master'
);

require_once( UINEWSROOM__PLUGIN_DIR . 'includes/class.ui-newsroom.php' );

$uinewsroom = new UiNewsroom();

register_activation_hook( __FILE__, array( $uinewsroom, 'plugin_activation' ) );
register_deactivation_hook( __FILE__, array( $uinewsroom, 'plugin_deactivation' ) );

add_action( 'init', array( $uinewsroom, 'init' ) );
add_action( 'plugins_loaded', array( $uinewsroom, 'load_textdomain'));

if ( is_admin() ) {
    require_once( UINEWSROOM__PLUGIN_DIR . 'admin/class.ui-newsroom-admin.php' );
    $uinewsroom_admin = new UiNewsroomAdmin();
    add_action( 'init', array( $uinewsroom_admin, 'init' ) );
}