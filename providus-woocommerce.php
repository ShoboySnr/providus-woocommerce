<?php

/*
Plugin Name: Providus Bank Payment
Plugin URI: https://providusbank.com
Description: Providus Bank Payment with WooCommerce.
Version: 1.0.0
Author: Providus Bank
Contributors: Providus Bank
Author URI: https://providusbank.com
License: GPL2
*/
require __DIR__ . '/vendor/autoload.php';

define('PROVIDUS_WOOCOMMERCE_SYSTEM_FILE_PATH', __FILE__);
define('PROVIDUS_WOOCOMMERCE_VERSION_NUMBER', '1.0.0');

add_action( 'plugins_loaded', 'wc_gateway_init', 11);

function wc_gateway_init() {
    \ProvidusWooCommerce\Init::init();
}