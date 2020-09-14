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


require __DIR__ . '/vendor/autoload.php';

/**
 * Register API with Wordpress
 */
add_action( 'rest_api_init', 'nrb_gear_register_routes' );


/**
 * Register routes with Wordpress
 */
function nrb_gear_register_routes() {
    register_rest_route( 'nrb_gear', 'survey', array(
        'methods'  => WP_REST_Server::EDITABLE,
        'callback' => 'nrb_gear_server_route_survey',
    ) );
    register_rest_route( 'nrb_gear', 'age', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'nrb_gear_serve_route_age',
    ) );
  }


/**
 * Actual Logic for routes
 */

function nrb_gear_server_route_survey( WP_REST_Request $request ) {
  global $wpdb;
  $result = [];

  /* read POST vars */
  $row = array_map('stripslashes_deep',json_decode( file_get_contents( 'php://input' ), true ));
  /*
      list($first_name, $last_name, $phone_home, $phone_work, $email_address,
          $mailing_address, $mailing_city, $mailing_state, $mailing_zip) = array_values($row);
   */
  /* check to see if user exists */
  $row1 = (array) $wpdb->get_row($wpdb->prepare(
      "SELECT id, user_email, user_login, meta_value " .
      "FROM wp_users " .
      "LEFT JOIN wp_usermeta ON id = user_id " .
      "WHERE user_email = %s" .
      "AND meta_key = 'wp_capabilities'", $row['email_address']
  ));
	if ($wpdb->num_rows and (stripos($row1['meta_value'], 'customer') != false)) {
      /*  user found use their id, login, but NO password */
			$id = $row1['id'];
			$login = $row1['user_login'];
      $password = '';

	} else {
      /* create id, login and password */
      $id = '0';
			$username = strtolower(preg_replace('/[^a-zA-Z0-9]/','', $row['first_name'] . $row['last_name']));
			$counter = 0;
			$uname = $username;
			$row1 = (array) $wpdb->get_row($wpdb->prepare("SELECT user_login FROM wp_users WHERE user_login = %s", $uname));
			while($wpdb->num_rows) {
					$counter += 1;
					$uname = $username . strval($counter);
					$row1 = (array) $wpdb->get_row($wpdb->prepare("SELECT user_login FROM wp_users WHERE user_login = %s", $uname));
      }
      $login = $uname;

      $g = new \Kieranajp\Generator\Generator();
      $g->setFormat(["word", "num", "symbol"]);
			$password = $g->generate()[0];

      /* add new woocomerce user by creating user and adding meta data */
      $id = wp_create_user( (string) $uname, (string) $password, (string) $email = $row['email_address'] );

      /* set contact information for user */
      update_user_meta( $id, "first_name", $row['first_name'] );
      update_user_meta( $id, "last_name", $row['last_name']);
      update_user_meta( $id, "billing_first_name", $row['first_name'] );
      update_user_meta( $id, "billing_last_name", $row['last_name']);
      update_user_meta( $id, "billing_company", '' );
      update_user_meta( $id, "billing_email", $row['email_address'] );
      update_user_meta( $id, "billing_address_1", $row['mailing_address']);
      update_user_meta( $id, "billing_address_2", '' );
      update_user_meta( $id, "billing_city", $row['mailing_city']);
      update_user_meta( $id, "billing_postcode", $row['mailing_zip'] );
      update_user_meta( $id, "billing_country", state2country($row['mailing_state']) );
      update_user_meta( $id, "billing_state", $row['mailing_state'] );
      update_user_meta( $id, "billing_phone", $row['phone_home'] );
      update_user_meta( $id, "shipping_first_name", $row['first_name'] );
      update_user_meta( $id, "shipping_last_name", $row['last_name']);
      update_user_meta( $id, "shipping_company", '' );
      update_user_meta( $id, "shipping_address_1", $row['mailing_address']);
      update_user_meta( $id, "shipping_address_2", '' );
      update_user_meta( $id, "shipping_city", $row['mailing_city']);
      update_user_meta( $id, "shipping_postcode", $row['mailing_zip'] );
      update_user_meta( $id, "shipping_country", state2country($row['mailing_state']) );
      update_user_meta( $id, "shipping_state", $row['mailing_state'] );
  }

  /* for current user set them as Survey User with date 45 days in the future */
  update_user_meta( $id, "wp_capabilities", array('survey_customer' => true));
  update_user_meta( $id, "survey_date", date('Y-m-d', strtotime(date('Y-m-d'). ' + 46 days')) );

  $result['login'] = $login;
  $result['password'] = $password;
  $result['expires'] = date('l F jS Y', strtotime(date('Y-m-d'). ' + 45 days') );
  return $result;
}

/* to be run as CRONJOB via * /15 * * * *  wget -q -O - https://gear.northriverboats.com/wp-json/nrb_gear//age */
function nrb_gear_serve_route_age( WP_REST_Request $request ) {
  global $wpdb;
  $response = [];

  if (!whitelisted()) { $response['error'] = 'not allowed'; return $response; }

  /* check to see if user exists */
  $rows = (array) $wpdb->get_results($wpdb->prepare(
    "SELECT user_id  " .
    "FROM wp_usermeta " .
    "WHERE meta_key = 'survey_date' " .
    "AND meta_value < curdate()"
  ));
  foreach ($rows as $row) {
    $id = $row->user_id;
    update_user_meta( $id, "wp_capabilities", array('customer' => true) );
    delete_user_meta( $id, "survey_date" );
  }
  /* $response['status'] = 'ok'; */

  return;
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
    return (strpos("|x|100.42.163.5|138.68.220.85|47.42.172.99|47.42.164.3|", "|".get_the_user_ip()."|") > 0);
}

function state2country($state) {
  if (strpos("|x|New Brunswick|Newfoundland and Labrador|Nova Scotia|Nunavut|Northwest Territories|Prince Edward Island|Quebec|Saskatchewa|Yukon". $state) > 0) {
    return "CA";
  }
  return "US";
}
