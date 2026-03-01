<?php
/**
 * PayzCore API Library for OpenCart 3.x
 *
 * Non-custodial blockchain transaction monitoring API client.
 * Watches blockchain addresses, detects incoming stablecoin transfers
 * (USDT, USDC) on multiple networks (TRC20, BEP20, ERC20, Polygon, Arbitrum),
 * and sends webhook notifications. Does not hold or transmit funds.
 *
 * @package    PayzCore
 * @version    1.0.0
 * @license    MIT
 * @link       https://payzcore.com
 */
class Payzcore {
    /**
     * @var string API key for authentication
     */
    private $api_key;

    /**
     * @var string Base URL for the PayzCore API
     */
    private $api_url;

    /**
     * @var int Request timeout in seconds
     */
    private $timeout = 30;

    /**
     * @var string User-Agent header
     */
    private $user_agent = 'PayzCore-OpenCart/1.0.0';

    /**
     * Constructor.
     *
     * @param string $api_key API key (pk_live_xxx or pk_test_xxx)
     * @param string $api_url Base API URL (default: https://api.payzcore.com)
     */
    public function __construct($api_key, $api_url = 'https://api.payzcore.com') {
        $this->api_key = $api_key;
        $this->api_url = rtrim($api_url, '/');
    }

    /**
     * Create a payment monitoring request.
     *
     * @param array $params Payment parameters:
     *   - amount (float):       Required. Amount in USD.
     *   - network (string):     Required. "TRC20", "BEP20", "ERC20", "POLYGON", or "ARBITRUM".
     *   - token (string):       Optional. "USDT" (default) or "USDC".
     *   - external_ref (string): Optional. Customer reference.
     *   - external_order_id (string): Optional. Order reference.
     *   - expires_in (int):     Optional. Expiry in seconds (default: 3600).
     *   - metadata (array):     Optional. Additional metadata.
     *
     * @return array Parsed API response.
     * @throws \Exception On API or network error.
     */
    public function createPayment(array $params) {
        return $this->request('POST', '/v1/payments', $params);
    }

    /**
     * Get payment status from the monitoring API.
     *
     * @param string $payment_id Payment UUID.
     *
     * @return array Parsed API response.
     * @throws \Exception On API or network error.
     */
    public function getPayment($payment_id) {
        $payment_id = preg_replace('/[^a-f0-9\-]/i', '', $payment_id);

        return $this->request('GET', '/v1/payments/' . $payment_id);
    }

    /**
     * Confirm a payment by submitting a transaction hash.
     *
     * Used with static wallet mode where the customer must provide
     * their transaction hash for verification.
     *
     * @param string $endpoint Confirm endpoint path (from API response).
     * @param string $tx_hash  Transaction hash submitted by customer.
     *
     * @return array Parsed API response.
     * @throws \Exception On API or network error.
     */
    public function confirmPayment($endpoint, $tx_hash) {
        return $this->request('POST', $endpoint, array('tx_hash' => $tx_hash));
    }

    /**
     * Verify a webhook signature.
     *
     * The signature covers `timestamp + "." + body` to bind the timestamp
     * to the payload and prevent replay attacks with modified timestamps.
     *
     * @param string $payload    Raw request body.
     * @param string $signature  Value of X-PayzCore-Signature header.
     * @param string $secret     Webhook secret (whsec_xxx).
     * @param string $timestamp  Value of X-PayzCore-Timestamp header.
     *
     * @return bool True if signature is valid.
     */
    public static function verifyWebhookSignature($payload, $signature, $secret, $timestamp = '') {
        if (empty($payload) || empty($signature) || empty($secret) || empty($timestamp)) {
            return false;
        }

        // Signature covers timestamp + body
        $message = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $message, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Send an HTTP request to the PayzCore API.
     *
     * @param string     $method HTTP method (GET, POST).
     * @param string     $path   API endpoint path.
     * @param array|null $data   Request body for POST requests.
     *
     * @return array Parsed JSON response.
     * @throws \Exception On cURL or API error.
     */
    private function request($method, $path, $data = null) {
        $url = $this->api_url . $path;

        $ch = curl_init();

        $headers = array(
            'x-api-key: ' . $this->api_key,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: ' . $this->user_agent,
        );

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);

        curl_close($ch);

        if ($curl_errno) {
            throw new \Exception('PayzCore API connection error: ' . $curl_error, $curl_errno);
        }

        $decoded = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(
                'PayzCore API returned invalid JSON (HTTP ' . $http_code . '): ' . substr($response, 0, 200)
            );
        }

        if ($http_code >= 400) {
            $error_msg = isset($decoded['error']) ? $decoded['error'] : 'Unknown error';
            throw new \Exception('PayzCore API error (HTTP ' . $http_code . '): ' . $error_msg, $http_code);
        }

        if (isset($decoded['success']) && $decoded['success'] === false) {
            $error_msg = isset($decoded['error']) ? $decoded['error'] : 'Request failed';
            throw new \Exception('PayzCore API error: ' . $error_msg);
        }

        return $decoded;
    }
}
