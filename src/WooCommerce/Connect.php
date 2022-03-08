<?php

namespace ProvidusWooCommerce\WooCommerce;


class Connect {
    
    public function __construct()
    {
        if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
               $this->woocommerce_init();
        }
    }
    
    public function woocommerce_init() {
        add_filter( 'woocommerce_payment_gateways', [$this, 'wc_add_to_woocommerce_gateways'], 10, 1);
        add_filter( 'plugin_action_links_' . plugin_basename( PROVIDUS_WOOCOMMERCE_SYSTEM_FILE_PATH ), [$this, 'wc_gateway_plugin_links']);
        PaymentButton::get_instance();
        add_filter( 'script_loader_tag', [$this, 'defer_passing_js_loading'], 10 );
    }
    
    public function defer_passing_js_loading($url) {
        if ( strpos( $url, 'https://providus.s3.eu-west-2.amazonaws.com/pay.js' ) ) {
            return str_replace( ' src', ' defer src', $url );
        }
        
        return $url;
    }
    
    public function wc_gateway_plugin_links($links) {
        $plugin_links = array(
            '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=providus_woocommerce_gateway' ) . '">' . __( 'Configure', 'providus-woocommerce' ) . '</a>'
        );
    
        return array_merge( $plugin_links, $links );
    }
    
    public function wc_add_to_woocommerce_gateways($gateways) {
        $gateways[] = 'ProvidusWooCommerce\WooCommerce\Payment';
        return $gateways;
    }
    
    /**
     * @return Connect
     */
    public static function get_instance()
    {
        static $instance = null;
        
        if (is_null($instance)) {
            $instance = new self();
        }
        
        return $instance;
    }
}