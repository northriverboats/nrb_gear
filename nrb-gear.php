<?php
/**
 * * Plugin Name: NRB Gear Functions
 * * Plugin URI: https://www.northriverboats.com/
 * * Description: Additional API functions for gear store
 * * Version: 1.0
 * * Author: Fredrick W. Warren
 * * Author URI: http://home.elder-geek.net
 * **/

if ( ! defined( 'ABSPATH' ) ) wp_die( 'restricted access' );


// require __DIR__ . '/vendor/autoload.php';

/**
 * Register API with Wordpress
 */
add_action( 'rest_api_init', 'nrb_gear_register_routes' );


/**
 * Register routes with Wordpress
 */
function nrb_gear_register_routes() {
    register_rest_route( 'nrb_gear', 'foo', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'nrb_gear_serve_route_foo',
    ) );
}


/**
 * Actual Logic for routes
 */

function nrb_gear_serve_route_foo( WP_REST_Request $request ) {
    global $wpdb;
    $response = [];
    if (!whitelisted()) { $response['error'] = 'not allowed'; return $response; }  
    $response['ip'] = get_the_user_ip();
    return $response;
}


/**
 * Aux Functions
 */
function get_the_user_ip() {
    if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
        //check ip from share internet
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        //to check ip is pass from proxy
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

function whitelisted() {
    return (strpos("|x|100.42.163.5|138.68.220.85|47.42.172.99|", "|".get_the_user_ip()."|") > 0);
}
