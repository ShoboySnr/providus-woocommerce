<?php

namespace ProvidusWooCommerce\WooCommerce;


class PaymentButton {
  
    public $button_id;
  
    public $amount;
    
    public $reference;
    
    public $payment_shortcode_arrays = [];
  
    public function __construct()
    {
        add_shortcode('providus-payment-button', [$this, 'providus_payment_button']);
        
        if(!is_admin()) {
            add_action('wp_footer', [$this, 'add_popup_footer']);
        }
    
        add_action('wp_ajax_providus_custom_payment_handler', [$this, 'providus_custom_payment_handler']);
        add_action('wp_ajax_nopriv_providus_custom_payment_handler', [$this, 'providus_custom_payment_handler']);
    }
    
    public function providus_custom_payment_handler() {
        $button_id = $_POST['button_id'];
        
        if ( !wp_verify_nonce($_POST['nonce'], 'providus-woocommerce-payment-'.$button_id)) {
            $result['status'] = false;
            $result['message'] = __('Payment initialization failed','providus-woocommerce');
            
            echo json_encode($result);
            wp_die();
        }
        
        if(!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $result['status'] = false;
            $result['message'] = __('Email address not valid', 'providus-woocommerce');
    
            echo json_encode($result);
            wp_die();
        }
    
        $result['status'] = true;
        $result['message'] = __('Payment initialization successful', 'providus-woocommerce');
        $result['data'] = $_POST;
    
        echo json_encode($result);
        wp_die();
    }
    
    public function providus_payment_button($atts, $content) {
        
        //set up parameters
        extract(shortcode_atts(array(
            'type' => 'primary',
            'amount' => '',
        ), $atts));
        
        if(empty($content)) {
          $content = __('Pay Now', 'providus-woocommerce');
        }
    
        $providus_settings = get_option('woocommerce_providus_woocommerce_settings');
    
        if(empty($providus_settings['client_id'])) {
            if(current_user_can( 'manage_options' )) {
                return '<span style="color: red;">'. __('Client ID is missing, please add client ID', 'providus-woocommerce') . '</span>';
            } else {
              return '';
            }
        }
    
        if(empty($amount)) {
            if(current_user_can( 'manage_options' )) {
                return '<span style="color: red;">'. __('No amount found, please specify the amount', 'providus-woocommerce') . '</span>';
            } else {
                return '';
            }
        }
        
        if(empty($reference)) {
            $reference = 'P_'.$this->generate_transaction_ref();
        }
        
        ob_start();
        
        $button_id = $this->generate_transaction_ref();
        
        $this->button_id = $button_id;
        $this->amount = $amount;
        $this->reference = $reference;
        
        array_push($this->payment_shortcode_arrays, ['button_id' => $button_id, 'amount' => $amount]);
    
        wp_enqueue_script( 'jquery' );
    
        wp_enqueue_script( 'providus-bank', 'http://commerce.staging.hersimi.com/js/pay.js', array( 'jquery' ), PROVIDUS_WOOCOMMERCE_VERSION_NUMBER, false );
    
        wp_enqueue_script( 'wc_providus_bank', plugins_url( 'assets/js/providus.js', PROVIDUS_WOOCOMMERCE_SYSTEM_FILE_PATH ), [ 'jquery', 'providus-bank' ], PROVIDUS_WOOCOMMERCE_VERSION_NUMBER, true );
    
        wp_enqueue_style( 'wc_providus_bank', plugins_url( 'assets/css/providus.css', PROVIDUS_WOOCOMMERCE_SYSTEM_FILE_PATH ), [], PROVIDUS_WOOCOMMERCE_VERSION_NUMBER);
    
        $providus_params = [
            'ajax_url'  => admin_url('admin-ajax.php'),
            'client_id' => $providus_settings['client_id']
        ];
        
        wp_localize_script( 'wc_providus_bank', 'wc_providus_bank_params', $providus_params );
        
        
        ?>
        <button id="providus_custom_payment_button-<?php echo $button_id ?>" type="button" class="providus-custom-payment-button <?php echo $type ?>" data-custom-popup="<?php echo 'pw_'.$button_id; ?>" style="display: none"><?php echo $content; ?></button>
        <?php
        return ob_get_clean();
    }
    
    public function add_popup_footer()
    {
      ob_start();
      
      foreach($this->payment_shortcode_arrays as $key => $value) {
          $button_id = $value['button_id'];
          $amount = $value['amount'];
          $formatted_amount = $this->format_amount($amount);
    
          $nonce = wp_create_nonce("providus-woocommerce-payment-".$button_id);
      ?>
      <section class="providus_custom_payment_container <?php echo $button_id; ?>" id="<?php echo 'pw_'.$button_id; ?>" style="display: none">
        <div class="pw-providus-container">
          <button class="pw-close-button" type="button">X</button>
          <div class="pw-title">
            <h4><?php echo __('Pay with Providus - '. $formatted_amount, 'providus-woocommerce') ?></h4>
          </div>
          <hr />
          <form action="" id="form-<?php echo $button_id; ?>" method="post" class="pw-payment-form" data-form-reference="<?php echo $this->reference ?>">
              <div class="input-group">
                <input name="pw-email" type="email" required placeholder="<?= __('Enter Your Email', 'providus-woocommerce') ?>" class="input-control" value="" />
              </div>
              <div class="input-group">
                <input name="pw-phone-number" type="tel" placeholder="<?= __('Enter Your Phone Number (e.g 0812345678)', 'providus-woocommerce') ?>" class="input-control" />
              </div>
              <div class="input-group">
                <input type="submit" value="<?= __('Proceed', 'providus-woocommerce') ?>" class="input-submit pw-submit" />
              </div>
              <input name="pw-nonce" type="hidden" value="<?= $nonce ?>" />
              <input name="pw-button-id" type="hidden" value="<?= $button_id ?>" />
              <input name="pw-amount" type="hidden" value="<?= $amount ?>" />
            </form>
          <div class="notices-alert error"></div>
          <div class="notices-alert success"></div>
        </div>
      </section>
      <?php
      }
      
      echo ob_get_clean();
    }
    
    public function format_amount($amount) {
      return '&#x20A6;'.number_format( intval($amount), 2, '.', ',');
    }
    
    public function generate_transaction_ref($length = 10) {
        $random = "";
        srand((double)microtime()*1000000);
        $char_list = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $char_list .= "abcdefghijklmnopqrstuvwxyz";
        $char_list .= "1234567890";
        
        for($i = 0; $i < $length; $i++)
        {
            $random .= substr($char_list,(rand()%(strlen($char_list))), 1);
        }
        $prefix = "PB-";
        $random = $prefix . $random . '-'.time();
        
        return $random;
    }
    
    /**
     * @return PaymentButton|null
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