<?php

namespace ProvidusWooCommerce;

class Init {
    
    public static function init() {
        \ProvidusWooCommerce\WooCommerce\Connect::get_instance();
    }
}