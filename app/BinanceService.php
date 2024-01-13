<?php

namespace BeycanPress\BinancePay;

use Exception;
use BeycanPress\CurrencyConverter;
use BeycanPress\BinancePay\Gateway;

class BinanceService
{
	/**
     * @var string
     */
    private $apiKey;
    
    /**
     * @var string
     */
    private $apiSecret;

    /**
     * @var string
     */
    private $certSerial;
    
    /**
     * @var string
     */
    private $publicKey;

	/**
     * @var int
     */
	private $timestamp;

    /**
     * @var string
     */
	private $nonce;

	/**
     * @var string
     */
	private $payload;

    /**
     * @var string
     */
	private $signature;

    /**
     * @var array
     */
    private $headers;

	/**
	 * @var string
	 */
	private $apiUrl = 'https://bpay.binanceapi.com/binancepay/openapi/';

    public function __construct()
    {
		$this->timestamp = (time() * 1000);
		$this->nonce = bin2hex(random_bytes(16));
        $this->apiKey = Gateway::getOption('api_key');
        $this->apiSecret = Gateway::getOption('api_secret');
        $this->certSerial = Gateway::getOption('certserial');
        $this->publicKey = Gateway::getOption('certpublic');

        $this->addHeaders([
            'Content-Type' => 'application/json',
            'BinancePay-Nonce' => $this->getNonce(),
            'BinancePay-Timestamp' => $this->getTimestamp(),
            'BinancePay-Certificate-SN' => $this->getApiKey(),
        ]);
    }
    /**
     * @param \WC_Order $order
     * @param string $paymentCurrency
     * @return object|null
     * @throws Exception
     */
    public function createOrder(\WC_Order $order, string $paymentCurrency) : ?object
    {
		$orderId = $order->get_id();

		$currency = $order->get_currency();
		$amount = (float) $order->get_total();

        $converter = new CurrencyConverter('CryptoCompare');

        if (
            $converter->isStableCoin($currency, $paymentCurrency) || 
            $converter->isSameCurrency($currency, $paymentCurrency)
        ) {
            $paymentAmount = floatval($amount);
        } else {
            $paymentAmount = $converter->convert($currency, $paymentCurrency, $amount);
        }

        $paymentAmount = floatval(number_format($paymentAmount, 18, '.', ""));
        
        $returnUrl = $this->createCallbackUrl($orderId, 'return');
        $cancelUrl = $this->createCallbackUrl($orderId, 'cancel');
        
        $merchantTradeNo = 'wc' . $orderId . 'r' . mt_rand(1,9999);

		$data = [
			'env' => [
				'terminalType' => 'WEB'
			],
			'goods' => [ 
				'goodsType' => '01',
				'goodsCategory' => '0000',
				'referenceGoodsId' => $orderId,
				'goodsName' => 'Order ID - ' . $orderId ,
			],
			'returnUrl' => $returnUrl,
			'cancelUrl' => $cancelUrl,
			'currency' => $paymentCurrency,
			'orderAmount' => $paymentAmount,
			'merchantTradeNo' => $merchantTradeNo, 
		];
        
        $order->update_meta_data('Merchant Reference ID', $merchantTradeNo);
        $order->update_meta_data('Amount Received', $paymentAmount . " " .  $paymentCurrency);
        $order->save();

		$jsonData = json_encode($data, JSON_THROW_ON_ERROR);
		$this->signTransaction($jsonData);

		return $this->request($this->getApiUrl('v2/order'), $jsonData);
    }

    /**
     * @param \WC_Order $order
     * @return object
     */
    public function queryOrder(\WC_Order $order) : object 
    {
		$prepayId = $order->get_meta('binance_order_id');
		$jsonData = json_encode([
            'prepayId' => $prepayId
        ], JSON_THROW_ON_ERROR);
        $this->signTransaction($jsonData);
		
        return $this->request($this->getApiUrl('v2/order/query'), $jsonData);
    }

    /**
     * @return object
     */
    public function getSertificate() : object
    {
		$jsonData = json_encode([], JSON_THROW_ON_ERROR);
        $this->signTransaction($jsonData);
		$result = $this->request($this->getApiUrl('certificates'), $jsonData);

        $certSerial = $result[0]->certSerial ?? null;
        $certPublic = $result[0]->certPublic ?? null;

        return (object) compact('certSerial', 'certPublic');
    }

    /**
     * @param array $headers
     * @param string $requestData
     * @return boolean
     */
    public function validWebhookRequest(array $headers, string $requestData) : bool 
    {
		$allowedHeaders = [
			'binancepay-certificate-sn',
			'binancepay-nonce',
			'binancepay-timestamp',
			'binancepay-signature'
		];

		$neededHeaders = [];
		foreach ($headers as $key => $value) {
			if (in_array(strtolower($key), $allowedHeaders)) {
				$neededHeaders[strtolower($key)] = $value;
			}
		}
        
		if (!empty(array_diff($allowedHeaders, array_keys($neededHeaders)))) {
			return false;
		}

		if ($this->certSerial !== $neededHeaders['binancepay-certificate-sn']) {
			return false;
		}

		$payload = $neededHeaders['binancepay-timestamp'] . "\n" . $neededHeaders['binancepay-nonce'] . "\n" . $requestData . "\n";
		$decodedSignature = base64_decode($neededHeaders['binancepay-signature']);

		$result = openssl_verify($payload, $decodedSignature, $this->publicKey, OPENSSL_ALGO_SHA256);

		if ($result === 1) {
			return true;
		}

		return false;
	}

    /**
     * @param integer $orderId
     * @param string $action
     * @return string
     */
    private function createCallbackUrl(int $orderId,  string $action) : string
    {
        $token = base64_encode(http_build_query([
            'action' => $action,
            'order_id' => $orderId,
        ]));
        return home_url('wc-api/binance-pay-gateway/callback?token='.$token);
    }

    /**
     * @param string $jsonData
     * @return void
     */
	private function signTransaction(string $jsonData): void 
    {
		$this->payload = $this->getTimestamp() . "\n" . $this->getNonce() . "\n" . $jsonData . "\n";
		$this->signature = strtoupper(hash_hmac('SHA512', $this->payload, $this->getApiSecret()));
        $this->addHeader('BinancePay-Signature', $this->getSignature());
	}

    /**
     * @param string $url
     * @param mixed $data
     * @return mixed
     * @throws Exception
     */
    private function request(string $url, $data)
    {
        $curl = curl_init($url);

		curl_setopt_array($curl, [
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_HTTPHEADER => $this->getHeaders(),
			CURLOPT_POSTFIELDS => $data,
			CURLOPT_RETURNTRANSFER => 1,
		]);

		$response = json_decode(curl_exec($curl));

		if ($response->status == 'SUCCESS') {
            return $response->data;
        } else {
            throw new Exception($response->errorMessage ?? $response->msg, $response->code);
        }

		curl_close($curl); 

        return null;
    }

    /**
     * @param array $headers
     * @return void
     */
    private function addHeaders(array $headers) : void
    {
        foreach ($headers as $key => $value) {
            $this->addHeader($key, $value);
        }
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return void
     */
    private function addHeader(string $key, $value) : void
    {
        $this->headers[] = $key . ': ' . $value;
    }

    /**
     * @param string $apiPath
     * @return string
     */
    public function getApiUrl(string $apiPath) : string
    {
        return $this->apiUrl . ltrim($apiPath, '/');
    }

    /**
     * @return string|null
     */
    public function getApiKey() : ?string
    {
        return $this->apiKey;
    }

    /**
     * @return string
     */
    public function getApiSecret() : string
    {
        return $this->apiSecret;
    }

    /**
     * @return int
     */
    public function getTimestamp() : int
    {
        return $this->timestamp;
    }

    /**
     * @return string
     */
    public function getNonce() : string
    {
        return $this->nonce;
    }

    /**
     * @return string
     */
    public function getPayload() : string
    {
        return $this->payload;
    }

    /**
     * @return string
     */
    public function getSignature() : string
    {
        return $this->signature;
    }
    
    /**
     * @return array
     */
    public function getHeaders() : array
    {
        return $this->headers;
    }
}