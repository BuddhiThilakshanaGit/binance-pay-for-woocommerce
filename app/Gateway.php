<?php 

namespace BeycanPress\BinancePay;

class Gateway extends \WC_Payment_Gateway
{   
    /**
     * @var string
     */
    public static $gateway = 'binance_pay_gateway';

    /**
     * @return void
     */
    public function __construct()
    {
        $this->id = self::$gateway;
        $this->method_title = esc_html__('Binance Pay', 'binance_pay_gateway');
        $this->method_description = esc_html__('Binance Pay Payment Gateway', 'binance_pay_gateway');

        $this->supports = ['products'];

        $this->init_form_fields();

        $this->init_settings();
        $this->title = $this->get_option('title');
        $this->enabled = $this->get_option('enabled');
        $this->description = $this->get_option('description');
        $this->order_button_text = $this->get_option('order_button_text');

        $callback = new Callback();
		add_action('woocommerce_api_binance-pay-gateway/callback', [$callback, 'init']);
		add_action('woocommerce_api_binance-pay-gateway/webhook', [$callback, 'webhook']);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        add_filter( 'woocommerce_form_field_multiselect', [$this, 'addMultiSelect'], 10, 4 );
    }

    public function addMultiSelect( $field, $key, $args, $value ) : string
    {
        $options = '';
    
        if ( ! empty( $args['options'] ) ) {
            foreach ( $args['options'] as $optionKey => $optionText ) {
                $options .= '<option value="' . esc_attr($optionKey) . '" '. selected( $value, $optionText, false ) . '>' . esc_html($optionText) .'</option>';
            }
    
            if ($args['required']) {
                $args['class'][] = 'validate-required';
                $required = '&nbsp;<abbr class="required" title="' . esc_attr__('required', 'woocommerce') . '">*</abbr>';
            }
            else {
                $required = '&nbsp;<span class="optional">(' . esc_html__('optional', 'woocommerce') . ')</span>';
            }
    
            $field = '<p class="form-row ' . implode( ' ', $args['class'] ) .'" id="' . esc_attr($key) . '_field">
                <label for="' . esc_attr($key) . '" class="' . implode( ' ', $args['label_class'] ) .'">' . esc_html($args['label']). esc_html($required) . '</label>
                <select name="' . esc_attr($key) . '" id="' . esc_attr($key) . '" class="select" multiple="multiple">
                    ' . wp_kses_post($options) . '
                </select>
            </p>' . wp_kses_post($args['after']);
        }
    
        return $field;
    }

    /**
     * @return void
     */
    public function init_form_fields() : void
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => esc_html__('Enable/Disable', 'binance_pay_gateway'),
                'label'       => esc_html__('Enable', 'binance_pay_gateway'),
                'type'        => 'checkbox',
                'default'     => 'no'
            ),
            'title' => array(
                'title'       => esc_html__('Title', 'binance_pay_gateway'),
                'type'        => 'text',
                'description' => esc_html__('This controls the title which the user sees during checkout.', 'binance_pay_gateway'),
                'default'     => esc_html__('Pay with Binance Pay', 'binance_pay_gateway')
            ),
            'description' => array(
                'title'       => esc_html__('Description', 'binance_pay_gateway'),
                'type'        => 'textarea',
                'description' => esc_html__('This controls the description which the user sees during checkout.', 'binance_pay_gateway'),
                'default'     => esc_html__('Pay with Binance Pay', 'binance_pay_gateway'),
            ),
            'order_button_text' => array(
                'title'       => esc_html__('Order button text', 'binance_pay_gateway'),
                'type'        => 'text',
                'description' => esc_html__('Pay button on the checkout page', 'binance_pay_gateway'),
                'default'     => esc_html__('Pay with Binance Pay', 'binance_pay_gateway'),
            ),
            'accepted_currencies' => array(
                'title'       => esc_html__('Accepted currencies', 'binance_pay_gateway'),
                'type'          => 'multiselect',
                'class'         => array('my-field-class form-row-wide'),
                'description' => esc_html__('Choose multiple currency with CTRL + Click', 'binance_pay_gateway'),
                'options'       => array(
                    'USDT' => 'USDT',
                    'USDC' => 'USDC',
                    'TUSD' => 'TUSD',
                    'BTC' => 'BTC',
                    'ETH' => 'ETH',
                    'BNB' => 'BNB',
                    'ADA' => 'ADA',
                    'ATOM' => 'ATOM',
                    'AVA' => 'AVA',
                    'BCH' => 'BCH',
                    'CTSI' => 'CTSI',
                    'DASH' => 'DASH',
                    'DOGE' => 'DOGE',
                    'DOT' => 'DOT',
                    'EGLD' => 'EGLD',
                    'EOS' => 'EOS',
                    'ETC' => 'ETC',
                    'FIL' => 'FIL',
                    'FRONT' => 'FRONT',
                    'FTM' => 'FTM',
                    'GRS' => 'GRS',
                    'HBAR' => 'HBAR',
                    'IOTX' => 'IOTX',
                    'LINK' => 'LINK',
                    'LTC' => 'LTC',
                    'MANA' => 'MANA',
                    'MATIC' => 'MATIC',
                    'NEO' => 'NEO',
                    'OM' => 'OM',
                    'ONE' => 'ONE',
                    'PAX' => 'PAX',
                    'QTUM' => 'QTUM',
                    'STRAX' => 'STRAX',
                    'SXP' => 'SXP',
                    'TRX' => 'TRX',
                    'UNI' => 'UNI',
                    'VAI' => 'VAI',
                    'VET' => 'VET',
                    'WRX' => 'WRX',
                    'XLM' => 'XLM',
                    'XMR' => 'XMR',
                    'XRP' => 'XRP',
                    'XTZ' => 'XTZ',
                    'XVS' => 'XVS',
                    'ZEC' => 'ZEC',
                    'ZIL' => 'ZIL',
                )
            ),
            'payment_complete_order_status' => array(
                'title'   => esc_html__('Payment complete order status', 'binance_pay_gateway'),
                'type'    => 'select',
                'help'    => esc_html__('The status to apply for order after payment is complete.', 'binance_pay_gateway'),
                'options' => [
                    'wc-completed' => esc_html__('Completed', 'binance_pay_gateway'),
                    'wc-processing' => esc_html__('Processing', 'binance_pay_gateway')
                ],
                'default' => 'wc-completed',
            ),
			'api_key' => array(
				'title'       => esc_html__('API Key', 'binance_pay_gateway'),
				'type'        => 'text',
				'description' => esc_html__('Provide the merchant API key from your Binance Pay merchant account.', 'binance_pay_gateway'),
				'default'     => null,
				'desc_tip'    => true,
            ),
			'api_secret' => array(
				'title'       => esc_html__('API Secret', 'binance_pay_gateway'),
				'type'        => 'password',
				'description' => esc_html__('Provide the merchant API secret from your Binance Pay merchant account.', 'binance_pay_gateway'),
				'default'     => null,
				'desc_tip'    => true,
            ),
            'location_desc' => array(
                'title'       => esc_html__('Eligibility Information', 'binance_pay_gateway'),
                'type'        => 'title',
                'description' => esc_html__('If you receive the following warning, it means that Binance Pay is not supported in your location. For more information, please refer to the Binance Pay terms and API pages.', 'binance_pay_gateway') . '<br><br>' . esc_html__('Service unavailable from a restricted location according to \'b. Eligibility\' in https://www.binance.com/en/terms. Please contact customer service if you believe you received this message in error.', 'binance_pay_gateway'),
            ),
            'binance_webhook_url' => array(
                'title'       => esc_html__('Webhook URL: ', 'binance_pay_gateway') . home_url('wc-api/binance-pay-gateway/webhook'),
                'type'        => 'title',
                'description' => esc_html__('Binance "merchant_callback_url" webhook endpoint', 'binance_pay_gateway'),
            ),
        );
    }

    public function process_admin_options() {
		parent::process_admin_options();

		try {
            $binanceService = new BinanceService();
			$sertifcates = $binanceService->getSertificate();

			if (!isset($sertifcates->certSerial, $sertifcates->certPublic)) {
				Notice::addNotice('error', 'No certificate (for validating webhooks) returned from Binance.');
			}

			$this->update_option('certserial', $sertifcates->certSerial);
			$this->update_option('certpublic', $sertifcates->certPublic);

			Notice::addNotice('success', 'Successfully fetched certificate (for validating webhooks) from Binance.');

		} catch (\Throwable $e) {
			Notice::addNotice('error', 'Error fetching certificate from Binance. Because of reason: ' . $e->getMessage());
		}

	}

    /**
     * @param string $key
     * @return string|null
     */
    public static function get_option_custom(string $key) : ?string
    {
        $options = get_option('woocommerce_'.self::$gateway.'_settings');
        return isset($options[$key]) ? $options[$key] : null;
    }

    /**
     * @return mixed
     */
    public function get_icon() : string
    {
        return '<img src="'.plugins_url('assets/images/logo.png', dirname(__FILE__)).'" alt="Binance Pay" />';
    }

    /**
     * @return void
     */
    public function payment_fields() : void
    {
        ?>
            <div id="binance-pay-container">
                <label for="bp-currency">
                    <?php esc_html_e('Choose currency:', 'binance_pay_gateway'); ?>
                </label>
                <select name="bp-currency" id="bp-currency">
                    <?php 
                        $accepted_currencies = (array) $this->get_option('accepted_currencies');
                        if (empty($accepted_currencies)) {
                            $accepted_currencies = ['USDT'];
                        }
                        foreach ($accepted_currencies as $currency) {
                            echo '<option value="'.esc_attr($currency).'">'.esc_html($currency).'</option>';
                        }
                    ?>
                </select>
                <br>
                <br>
                <div class="bp-footer">
                    <span class="powered-by">
                        Powered by
                    </span>
                    <a href="https://beycanpress.com/cryptopay?utm_source=binance_pay_plugin&amp;utm_medium=powered_by" target="_blank">CryptoPay</a>
                </div>
            </div>
        <?php
    }

    /**
     * @param int $orderId
     * @return array
     */
    public function process_payment($orderId) : array
    {
        $order = new \WC_Order($orderId);

		try {
            
            $paymentCurrency = isset($_POST['bp-currency']) ? sanitize_text_field($_POST['bp-currency']) : null;

            if (!$paymentCurrency) {
                throw new \Exception('Payment currency is not set.');                
            }

            $binanceService = new BinanceService();
			$result = $binanceService->createOrder($order, $paymentCurrency);
        
			$order->update_meta_data('binance_order_id', $result->prepayId);
			$order->save();

            $order->update_status('wc-pending', esc_html__('Payment is awaited.', 'binance_pay_gateway'));

            $order->add_order_note(esc_html__('Customer has chosen Binance Pay payment method, payment is pending.', 'binance_pay_gateway'));
    
			return [
				'result'   => 'success',
				'redirect' => $result->universalUrl,
				'orderId' => $order->get_id(),
			];

		} catch (\Exception $e) {
			wc_add_notice($e->getMessage(), 'error');
		}

		return [
			'result' => false
		];
    }    

	/**
     * @param string $key
     * @return string|null
     */
    public static function getOption(string $key) : ?string
    {
        $options = get_option('woocommerce_'.self::$gateway.'_settings');
        return isset($options[$key]) ? $options[$key] : null;
    }
}