<?php

namespace ProvidusWooCommerce\WooCommerce;

class Payment extends \WC_Payment_Gateway {
    
    public $client_id;
    
    public $customer_phone;
    
    public $payment_page;
    
    public $msg;
    
    public function __construct()
    {
        $this->id                 = 'providus_woocommerce';
        $this->icon               = apply_filters('woocommerce_providus_woocommerce_payment_icon', '');
        $this->has_fields         = true;
        $this->enabled            = 'no';
        $this->method_title       = __( 'Providus Bank', 'providus-woocommerce' );
        $this->method_description = __( 'Providus Bank Payment Gateway provide merchants with the tools and services needed to accept online payments from local and international customers using Mastercard, Visa, Verve Cards and Bank Accounts', 'providus-woocommerce' );
    
        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();
    
        // Define user set variables
        $this->title            = $this->get_option( 'title' );
        $this->description      = $this->get_option( 'description' );
        $this->instructions     = $this->get_option( 'instructions', $this->description );
        $this->client_id        = $this->get_option('client_id');
        $this->customer_phone   = $this->get_option('customer_phone');
        $this->payment_page     = $this->get_option( 'payment_page' );
    
        add_action('woocommerce_update_options_payment_gateways_'.$this->id, [$this, 'process_admin_options']);
    
        add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
    
        add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );
        
        add_action( 'woocommerce_api_providus_woocommerce_gateway', array( $this, 'verify_providus_woocommerce_transaction' ) );
    
        // Check if the gateway can be used.
        if ( ! $this->is_valid_for_use() ) {
            $this->enabled = false;
        }
    }
    
    /**
     * Check if this gateway is enabled and available in the user's country.
     */
    public function is_valid_for_use() {
        
        if ( ! in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_providus_bank_supported_currencies', array( 'NGN') ) ) ) {
            
            $this->msg = sprintf( __( 'Providus Bank does not support your store currency. Kindly set it to either NGN (&#8358) <a href="%s">here</a>', 'providus-woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=general' ) );
            
            return false;
            
        }
        
        return true;
        
    }
    
    public function verify_providus_woocommerce_transaction() {
        if(isset($_REQUEST['providus_reference'])) {
          $providus_reference = sanitize_text_field( $_REQUEST['providus_reference'] );
        } else {
            $providus_reference = false;
        }
    
        @ob_clean();
        
        if($providus_reference) {
          $order_details = explode( '_', $providus_reference );
    
          $order_id = (int) $order_details[0];
          
         $order = wc_get_order($order_id);
          
          if(!empty($order_id)) {
              if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ) ) ) {
                  wp_redirect( $this->get_return_url( $order ) );
                  exit;
              }
    
              $order->payment_complete( $providus_reference );
              $order->add_order_note( sprintf( __( 'Payment via Providus Bank was successful (Transaction Reference: %s)', 'providus-woocommerce' ), $providus_reference ) );
    
              if ( $this->is_autocomplete_order_enabled( $order ) ) {
                  $order->update_status( 'completed' );
              }
          }
    
          wp_redirect( $this->get_return_url( $order ) );
          exit;
          
        }
    }
    
    protected function is_autocomplete_order_enabled( $order ) {
        $autocomplete_order = false;
        
        $payment_method = $order->get_payment_method();
        
        $providus_settings = get_option('woocommerce_' . $payment_method . '_settings');
        
        if ( isset( $providus_settings['autocomplete_order'] ) && 'yes' === $providus_settings['autocomplete_order'] ) {
            $autocomplete_order = true;
        }
        
        return $autocomplete_order;
    }
    
    /**
     * Check if Providus merchant details is filled.
     */
    public function admin_notices() {
        
        if ( $this->enabled == 'no' ) {
            return;
        }
        
        // Check required fields.
        if (empty($this->client_id) ) {
            echo '<div class="error"><p>' . sprintf( __( 'Please enter your Providus merchant details <a href="%s">here</a> to be able to use the Providus WooCommerce plugin.', 'proovidus-woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=providus_woocommerce' ) ) . '</p></div>';
            return;
        }
        
    }
    
    /**
     * Check if Providus gateway is enabled.
     *
     * @return bool
     */
    public function is_available() {
        
        if ( 'yes' == $this->enabled ) {
            
            if (empty($this->client_id)) {
                return false;
            }
            return true;
        }
        
        return false;
        
    }
    
    /**
     * Admin Panel Options.
     */
    public function admin_options() {
        
        ?>
        
        <h2><?php _e( 'Providus', 'providus-woocommerce' ); ?>
            <?php
                if ( function_exists( 'wc_back_link' ) ) {
                    wc_back_link( __( 'Return to payments', 'providus-woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) );
                }
            ?>
        </h2>
        
        <?php
        
        if ( $this->is_valid_for_use() ) {
            
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
            
        } else {
            ?>
            <div class="inline error"><p><strong><?php _e( 'Providus Bank Payment Gateway Disabled', 'providus-woocommerce' ); ?></strong>: <?php echo $this->msg; ?></p></div>
            
            <?php
        }
        
    }
    
    public function init_form_fields() {
        $this->form_fields = apply_filters('wc_providus_payment_form_fields', [
            'enabled' => [
                'title'   => __( 'Enable/Disable', 'providus-woocommerce' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Providus Payment', 'providus-woocommerce' ),
                'default' => 'no'
            ],
            'title' => [
                'title'       => __( 'Title', 'providus-woocommerce' ),
                'type'        => 'text',
                'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'providus-woocommerce' ),
                'default'     => __( 'Pay with Providus Bank', 'providus-woocommerce' ),
                'desc_tip'    => true,
            ],
            'description' =>    [
                'title'       => __( 'Description', 'providus-woocommerce' ),
                'type'        => 'textarea',
                'description' => __( 'This controls the payment method description which the user sees during checkout.', 'providus-woocommerce' ),
                'default'     => __( 'Make payment via bank transfer', 'providus-woocommerce' ),
                'desc_tip'    => true,
            ],
            'payment_page' => [
                'title'         => __( 'Payment Option', 'providus-woocommerce' ),
                'type'          => 'select',
                'description'   => __( 'Popup shows the payment popup on the page while Redirect will redirect the customer to Providus to make payment.', 'providus-woocommerce' ),
                'options'       => [
                    ''          => __('Select one', 'providus-woocommerce'),
                    'popup'     => __('Popup', 'providus-woocommerce'),
                ],
                'default'       => 'popup',
                'desc_tip'      => true,
            ],
            'client_id' => [
                'title'       => __( 'Client ID', 'providus-woocommerce' ),
                'type'        => 'text',
                'description' => __( 'Enter your Client ID here.', 'providus-woocommerce' ),
                'desc_tip'    => true,
            ],
            'autocomplete_order' => [
                'title'         => __( 'Autocomplete Order', 'providus-woocommerce' ),
                'type'          => 'checkbox',
                'label'         => __( 'If enabled, the order will be marked as complete after successful payment', 'providus-woocommerce' ),
                'default'       => 'no',
                'desc_tip'      => true,
            ],
            'customer_phone' => [
                'title'       => __( 'Customer\'s Phone', 'providus-woocommerce' ),
                'type'        => 'checkbox',
                'label'       => __( 'Send the Customer\'s Phone.', 'providus-woocommerce' ),
                'default'       => 'no',
            ],
        ]);
    }
    
    
    public function process_payment($order_id)
    {
        $order = wc_get_order( $order_id );
    
        return array(
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url( true ),
        );
    }
    
    
    public function payment_scripts() {
        if ( ! is_checkout_pay_page() ) {
            return;
        }
    
        if ( $this->enabled == 'no' ) {
            return;
        }
    
        $order_key = urldecode( $_GET['key'] );
        $order_id  = absint( get_query_var( 'order-pay' ) );
    
        $order = wc_get_order( $order_id );
    
        $payment_method = method_exists( $order, 'get_payment_method' ) ? $order->get_payment_method() : $order->payment_method;
    
        if ( $this->id !== $payment_method ) {
            return;
        }
        
        if(empty($this->client_id)) {
            return;
        }
    
        wp_enqueue_script( 'jquery' );
    
        wp_enqueue_script( 'providus-bank', 'http://commerce.staging.hersimi.com/js/pay.js', array( 'jquery' ), PROVIDUS_WOOCOMMERCE_VERSION_NUMBER, false );
    
        wp_enqueue_script( 'wc_providus_bank', plugins_url( 'assets/js/providus.js', PROVIDUS_WOOCOMMERCE_SYSTEM_FILE_PATH ), array( 'jquery', 'providus-bank' ), PROVIDUS_WOOCOMMERCE_VERSION_NUMBER, true );
    
        $providus_params = array(
            'client_id'   => $this->client_id,
            'order_id'    => $order_id
        );
    
        if ( is_checkout_pay_page() && get_query_var( 'order-pay' ) ) {
            $current_user = wp_get_current_user();
    
            $email = method_exists( $order, 'get_billing_email' ) ? $order->get_billing_email() : $order->billing_email;
    
            $amount = (int) $order->get_total();
    
            $txnref = $order_id . '_' . time();
    
            $the_order_id  = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
            $the_order_key = method_exists( $order, 'get_order_key' ) ? $order->get_order_key() : $order->order_key;
            
            $billing_phone = method_exists( $order, 'get_billing_phone' ) ? $order->get_billing_phone() : $order->billing_phone;
    
            if ( $the_order_id == $order_id && $the_order_key == $order_key ) {
    
                $providus_params['email']    = $email;
                $providus_params['amount']   = $amount;
                $providus_params['reference']   = $txnref;
            }
            
            if($this->customer_phone !== 'no') {
                $providus_params['phoneNumber']   = $billing_phone;
            }
    
            update_post_meta( $order_id, '_providus_bank_woo_txn_ref', $txnref );
            
        }
    
        wp_localize_script( 'wc_providus_bank', 'wc_providus_bank_params', $providus_params );
        
    }
    
    public function append_button_footer() {
    
    }
    
    /**
     * Displays the payment page.
     *
     * @param $order_id
     */
    public function receipt_page( $order_id ) {
        
        $order = wc_get_order( $order_id );
        
        echo '<div id="providus-woocommerce-form">';
        
        echo '<p>' . __( 'Thank you for your order, please click the button below to pay with Providus.', 'providus-woocommerce' ) . '</p>';
        
        echo '<div id="providus_form"><form id="order_review" method="post" action="' . WC()->api_request_url('Providus_Woocommerce_Gateway') . '"></form><button class="button" id="providus-payment-button">' . __( 'Pay Now', 'providus-woocommerce' ) . '</button></div>';
        
        echo '</div>';
        
    }
    
    
    /**
     * @return Payment|null
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