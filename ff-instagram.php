<?php
/**
 * Plugin Name: FF Instagram
 * Plugin URI: https://www.fivebyfive.com.au/
 * Description: Instagram feed
 * Version: 3.2.1
 * Author: Five by Five
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) die(); 

define( 'FF_INSTA', [
	'app_id' => get_option('ff_instagram_app_id'),
	'app_secret' => get_option('ff_instagram_app_secret'),
	'webhook_verify_token' => get_option('ff_instagram_webhook_verify_token'),
	'redirect_uri' => get_option('ff_instagram_redirect_uri'),
	'access_token' => get_option('ff_instagram_initial_access_token'),
]);

include 'class-ff-instagram.php';