<?php 

namespace BeycanPress\BinancePay;

class Callback
{   
    /**
     * @var object
     */
    private $order;

    /**
     * @var BinanceService
     */
    private $binanceService;

    public function __construct()
    {
        $this->binanceService = new BinanceService();
    }
    /**
     * @return void
     */
    public function init()
    {
        $token = isset($_GET['token']) ? $_GET['token'] : null;

        if (!$token) {
            exit(wp_redirect(home_url()));
        }

        parse_str(base64_decode($token), $output);
        $action = (string) $output['action'];
        $orderId = (int) $output['order_id'];
        
        if (!$this->order = wc_get_order($orderId)) {
            exit(wp_redirect(home_url()));
        }

        $orderStatus = $this->order->get_status();
        $binanceOrderId = $this->order->get_meta('binance_order_id');

        if (in_array($orderStatus, ['pending', 'failed']) && $binanceOrderId) {
            if ($action == 'cancel') {
                $this->updateOrderAsFail();
            }

            try {
                $orderResult = $this->binanceService->queryOrder($this->order);

                if ($orderResult->status == 'PAID') {
                    $this->updateOrderAsComplete();
                } elseif (in_array($orderResult->status, ['ERROR', 'EXPIRED', 'CANCELED'])) {
                    $this->updateOrderAsFail();
                }

            } catch (\Throwable $th) {
                $this->updateOrderAsFail();
            }

        } else {
            if (in_array($orderStatus, ['completed', 'processing'])) {
                exit(wp_redirect($this->order->get_checkout_order_received_url()));
            }  else {
                exit(wp_redirect($this->order->get_view_order_url()));
            }
        }
    }

    /**
     * @return void
     */
    public function webhook() : void
    {
		if ($rawData = file_get_contents("php://input")) {

            if (!$this->binanceService->validWebhookRequest(getallheaders(), $rawData)) {
				wp_die('Webhook request validation failed.');
			}

			try {
				$postData = json_decode($rawData, false, 512, JSON_THROW_ON_ERROR);
                if (is_string($postData->data)) {
                    $postData->data = json_decode($postData->data, false, 512, JSON_THROW_ON_ERROR);
                }

                if ($postData->bizType !== 'PAY') {
                    wp_die('Webhook event received but ignored, wrong type: ' . $postData->bizType);
                }

                $merchantTradeNo = $postData->data->merchantTradeNo;
                preg_match_all('/wc(.*?)r/m', $merchantTradeNo, $matches, PREG_SET_ORDER, 0);
                $orderId = $matches[0][1] ?? 0;

				if (!$this->order = new \WC_Order($orderId)) {
					wp_die('No order found for this payment transaction.', '', ['response' => 404]);
				}

                $orderStatus = $this->order->get_status();
                
                if (in_array($orderStatus, ['pending', 'failed'])) {
                    if ($postData->bizStatus == 'PAY_SUCCESS') {
                        $this->updateOrderAsComplete();
                    } elseif ($postData->bizStatus == 'PAY_CLOSED') {
                        $this->updateOrderAsFail();
                    }
                }

				wp_die('Webhook process successfully finished');
			} catch (\Throwable $e) {
				wp_die($e->getMessage());
			}
        }
    }

    /**
     * @return void
     */
    public function updateOrderAsComplete() : void
    {
        global $woocommerce;

        $completeStatus = Gateway::getOption('payment_complete_order_status');
        if ($completeStatus == 'wc-completed') {
            $note = esc_html__('Your order is complete.', 'binance_pay_gateway');
        } else {
            $note = esc_html__('Your order is processing.', 'binance_pay_gateway');
        }
        
        $this->order->payment_complete();

        $this->order->update_status($completeStatus, $note);
        
        // Remove cart
        $woocommerce->cart->empty_cart();

        exit(wp_redirect($this->order->get_checkout_order_received_url()));
    }

    /**
     * @return void
     */
    public function updateOrderAsFail() : void
    {
        $this->order->update_status('wc-failed', esc_html__('Payment is failed!', 'binance_pay_gateway'));
        
		wc_add_notice(esc_html__('Payment is failed!', 'binance_pay_gateway'), 'error');

        exit(wp_redirect(wc_get_checkout_url()));
    }
}
