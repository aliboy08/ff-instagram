<?php
/**
 * Plugin Name: FF Instagram
 * Plugin URI: https://www.fivebyfive.com.au/
 * Description: Instagram feed
 * Version: 1.2.3
 * Author: Five by Five
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

define( 'FF_INSTA', [
	'app_id' => '1001361776949629',
	'app_secret' => '3099b4f474f269216c2ebadfb765a7fd',
	'redirect_uri' => 'https://bigdaddy2021.wpengine.com/instagram-auth/',
	'access_token' => '',
]);

include 'class-ff-instagram.php';