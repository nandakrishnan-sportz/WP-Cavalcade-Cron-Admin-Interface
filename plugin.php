<?php
/*
Plugin Name: Cavalcade Jobs List
Plugin URI: https://www.wordpress.org
Description: Cavalcade List Admin Interface
Version: 1.0
Author: Nandakrishnan U
Author URI:  https://sportzinteractive.net
*/

if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
 }
require __DIR__ . '/cavalcade-plugin.php';
require __DIR__ . '/cavalcade-utilities.php';
require __DIR__ . '/cavalcade-jobs-list.php';
/**
 * Plugin Loaded Hook
 */
add_action( 'plugins_loaded', function () {
    if( HM\Cavalcade\Plugin\is_installed() ) {
        global $cavalcade_utilities;
        $cavalcade_utilities = new Cavalcade_Utilities();
        Cavalcade_List_Plugin::get_instance();
    }
} );
