<?php
/**
 * PayzCore - Catalog Controller
 *
 * Handles the customer-facing checkout flow for the PayzCore extension.
 * Manages payment creation, status polling, and webhook callbacks.
 *
 * PayzCore is a non-custodial blockchain transaction monitoring service.
 * It does not hold, transmit, or custody any funds.
 *
 * @package    PayzCore OpenCart Extension
 * @version    1.0.0
 */
class ControllerExtensionPaymentPayzcore extends Controller {
    /**
     * Display the payment button in the checkout flow.
     *
     * Called by OpenCart's checkout when the customer selects this payment method.
     * Renders the network/token selector (if multiple networks enabled) and confirm button.
     *
     * @return string Rendered template HTML.
     */
    public function index() {
        $this->load->language('extension/payment/payzcore');
        $this->load->model('extension/payment/payzcore');

        $data['text_loading'] = $this->language->get('text_loading');
        $data['text_description'] = $this->language->get('text_description');
        $data['text_select_network'] = $this->language->get('text_select_network');
        $data['text_select_token'] = $this->language->get('text_select_token');
        $data['text_trc20_note'] = $this->language->get('text_trc20_note');

        $default_token = $this->model_extension_payment_payzcore->getDefaultToken();
        $data['default_token'] = $default_token;

        $enabled_networks = $this->model_extension_payment_payzcore->getEnabledNetworks();

        $network_labels = array(
            'TRC20'    => 'TRC20 (Tron) - Lowest fees',
            'BEP20'    => 'BEP20 (BSC) - Low fees',
            'ERC20'    => 'ERC20 (Ethereum)',
            'POLYGON'  => 'Polygon - Very low fees',
            'ARBITRUM' => 'Arbitrum - Low fees',
        );

        $networks = array();
        foreach ($enabled_networks as $c) {
            $networks[] = array(
                'code'  => $c,
                'label' => isset($network_labels[$c]) ? $network_labels[$c] : $c,
            );
        }

        $data['networks'] = $networks;

        // For single network: show specific token and network label
        if (count($enabled_networks) === 1) {
            $single_network = $enabled_networks[0];
            $token = ($single_network === 'TRC20') ? 'USDT' : $default_token;
            $data['network_label'] = $this->getNetworkLabel($single_network);
            $data['token_label'] = $token;
            $data['text_confirm_button'] = str_replace('USDT', $token, $this->language->get('text_confirm_button'));
        } else {
            $data['network_label'] = '';
            $data['token_label'] = '';
            $data['text_confirm_button'] = $this->language->get('text_confirm_button');
        }

        $data['action'] = $this->url->link('extension/payment/payzcore/confirm', '', true);

        return $this->load->view('extension/payment/payzcore', $data);
    }

    /**
     * Create a payment monitoring request and show payment instructions.
     *
     * Called when the customer clicks "Pay with USDT". This:
     * 1. Loads the current order from the session.
     * 2. Creates a payment monitoring request via the PayzCore API.
     * 3. Stores the payment record in the local database.
     * 4. Sets the order status to "pending".
     * 5. Renders the payment instructions page with address, QR, countdown.
     *
     * @return void
     */
    public function confirm() {
        $this->load->language('extension/payment/payzcore');
        $this->load->model('extension/payment/payzcore');
        $this->load->model('checkout/order');

        // Verify we have an order in session
        if (!isset($this->session->data['order_id'])) {
            $this->session->data['error'] = $this->language->get('error_order_not_found');
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
            return;
        }

        $order_id = (int)$this->session->data['order_id'];
        $order_info = $this->model_checkout_order->getOrder($order_id);

        if (!$order_info) {
            $this->session->data['error'] = $this->language->get('error_order_not_found');
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
            return;
        }

        // Check if we already have a pending payment for this order
        $existing = $this->model_extension_payment_payzcore->getPaymentByOrderId($order_id);

        if ($existing && in_array($existing['status'], array('pending', 'confirming', 'partial'), true)) {
            // Reuse existing payment - show the instructions page
            $this->renderPaymentPage($existing, $order_info);
            return;
        }

        // Create new payment monitoring request
        try {
            $payzcore = $this->getApiClient();

            // Get enabled networks for validation
            $enabled_networks = $this->model_extension_payment_payzcore->getEnabledNetworks();

            // Read network/token from POST (checkout form) or fall back to defaults
            $network = isset($this->request->post['payzcore_network']) ? $this->request->post['payzcore_network'] : '';
            $default_token = $this->model_extension_payment_payzcore->getDefaultToken();
            $token = isset($this->request->post['payzcore_token']) ? $this->request->post['payzcore_token'] : $default_token;

            // Validate network is in enabled list
            if (empty($network) || !in_array($network, $enabled_networks)) {
                $network = $enabled_networks[0];
            }

            // Validate token
            if (!in_array($token, array('USDT', 'USDC'))) {
                $token = 'USDT';
            }

            // TRC20 only supports USDT
            if ($network === 'TRC20') { $token = 'USDT'; }

            $expires_in = (int)$this->config->get('payment_payzcore_expires_in') ?: 3600;

            // Use store currency total converted to USD
            // OpenCart stores total in the store's default currency
            $amount = (float)$order_info['total'];

            // If store currency is not USD, convert using OpenCart's currency rates
            if ($order_info['currency_code'] !== 'USD') {
                $amount = $this->currency->convert(
                    $order_info['total'],
                    $order_info['currency_code'],
                    'USD'
                );

                // Safety check: if conversion failed or returned invalid amount
                if ($amount <= 0) {
                    $this->log->write('PayzCore: USD currency rate not configured. Cannot convert from ' . $order_info['currency_code']);
                    return $this->sendJsonResponse(400, array(
                        'error' => 'USD exchange rate is not configured. Please contact the store administrator.'
                    ));
                }
            }

            // Build external reference from customer email
            $external_ref = $order_info['email'];

            // Build external order ID
            $external_order_id = 'oc-' . $order_id;

            $params = array(
                'amount'            => round($amount, 2),
                'network'           => $network,
                'token'             => $token,
                'external_ref'      => $external_ref,
                'external_order_id' => $external_order_id,
                'expires_in'        => $expires_in,
                'metadata'          => array(
                    'source'     => 'opencart',
                    'version'    => '1.0.0',
                    'store'      => $this->config->get('config_name'),
                    'order_id'   => $order_id,
                    'customer'   => $order_info['firstname'] . ' ' . $order_info['lastname'],
                ),
            );

            // Optional: pass static wallet address if configured
            $static_address = $this->config->get('payment_payzcore_address');
            if (!empty($static_address)) {
                $params['address'] = trim($static_address);
            }

            $this->debugLog('Creating payment monitoring request for order #' . $order_id . ': ' . json_encode($params));

            $response = $payzcore->createPayment($params);

            if (!isset($response['payment'])) {
                throw new \Exception('Invalid API response: missing payment data');
            }

            $payment = $response['payment'];

            // Store payment record
            $payment_data = array(
                'payment_id'       => $payment['id'],
                'address'          => $payment['address'],
                'amount'           => $payment['amount'],
                'network'          => $payment['network'],
                'token'            => isset($payment['token']) ? $payment['token'] : $token,
                'status'           => $payment['status'],
                'qr_code'          => isset($payment['qr_code']) ? $payment['qr_code'] : '',
                'notice'           => isset($payment['notice']) ? $payment['notice'] : null,
                'requires_txid'    => isset($payment['requires_txid']) ? (int)$payment['requires_txid'] : 0,
                'confirm_endpoint' => isset($payment['confirm_endpoint']) ? $payment['confirm_endpoint'] : '',
                'expires_at'       => isset($payment['expires_at']) ? date('Y-m-d H:i:s', strtotime($payment['expires_at'])) : null,
            );

            $this->model_extension_payment_payzcore->addPayment($order_id, $payment_data);

            // Set order to pending status
            $pending_status_id = (int)$this->config->get('payment_payzcore_order_status_id');
            if (!$pending_status_id) {
                $pending_status_id = 1; // Default: Pending
            }

            $token_name = isset($payment['token']) ? $payment['token'] : $token;
            $this->model_checkout_order->addOrderHistory(
                $order_id,
                $pending_status_id,
                'Stablecoin payment created. Waiting for ' . $token_name . ' transfer on ' . $network . '.',
                false // Don't notify customer via email (they see the page)
            );

            $this->debugLog('Payment monitoring request created: ' . $payment['id'] . ' for order #' . $order_id);

            // Get the stored payment for rendering
            $stored = $this->model_extension_payment_payzcore->getPaymentByOrderId($order_id);
            $this->renderPaymentPage($stored, $order_info);

        } catch (\Exception $e) {
            $this->debugLog('ERROR creating payment for order #' . $order_id . ': ' . $e->getMessage());

            $this->session->data['error'] = $this->language->get('error_payment_failed');
            $this->response->redirect($this->url->link('checkout/checkout', '', true));
        }
    }

    /**
     * Handle incoming webhook notifications from PayzCore.
     *
     * Verifies the HMAC-SHA256 signature, identifies the order,
     * and updates the order status based on the event type.
     *
     * @return void
     */
    public function callback() {
        // Load the PayzCore library for signature verification
        require_once(DIR_SYSTEM . 'library/payzcore.php');

        // Read raw POST body
        $raw_body = file_get_contents('php://input');

        if (empty($raw_body)) {
            $this->sendJsonResponse(400, array('error' => 'Empty request body'));
            return;
        }

        // Get signature header
        $signature = isset($this->request->server['HTTP_X_PAYZCORE_SIGNATURE'])
            ? $this->request->server['HTTP_X_PAYZCORE_SIGNATURE']
            : '';

        if (empty($signature)) {
            $this->debugLog('Webhook rejected: missing signature header');
            $this->sendJsonResponse(401, array('error' => 'Missing signature'));
            return;
        }

        // Timestamp is required for signature verification
        $timestamp_header = isset($this->request->server['HTTP_X_PAYZCORE_TIMESTAMP'])
            ? $this->request->server['HTTP_X_PAYZCORE_TIMESTAMP']
            : '';
        if (empty($timestamp_header)) {
            $this->debugLog('Webhook rejected: missing timestamp header');
            $this->sendJsonResponse(401, array('error' => 'Missing timestamp'));
            return;
        }

        // Replay protection (±5 minutes)
        $ts = strtotime($timestamp_header);
        if ($ts === false || abs(time() - $ts) > 300) {
            $this->debugLog('Webhook rejected: timestamp expired');
            $this->sendJsonResponse(401, array('error' => 'Timestamp validation failed'));
            return;
        }

        // Verify HMAC signature (covers timestamp + body)
        $webhook_secret = $this->config->get('payment_payzcore_webhook_secret');

        if (!Payzcore::verifyWebhookSignature($raw_body, $signature, $webhook_secret, $timestamp_header)) {
            $this->debugLog('Webhook rejected: invalid signature');
            $this->sendJsonResponse(401, array('error' => 'Invalid signature'));
            return;
        }

        // Parse payload
        $payload = json_decode($raw_body, true);

        if (!$payload || !isset($payload['event'])) {
            $this->debugLog('Webhook rejected: invalid payload');
            $this->sendJsonResponse(400, array('error' => 'Invalid payload'));
            return;
        }

        $this->debugLog('Webhook received: ' . $payload['event'] . ' for payment ' . (isset($payload['payment_id']) ? $payload['payment_id'] : 'unknown'));

        $this->load->model('extension/payment/payzcore');
        $this->load->model('checkout/order');

        // Find the order by external_order_id or payment_id
        $payment_record = null;

        if (isset($payload['external_order_id'])) {
            $payment_record = $this->model_extension_payment_payzcore->getPaymentByExternalOrderId($payload['external_order_id']);
        }

        if (!$payment_record && isset($payload['payment_id'])) {
            $payment_record = $this->model_extension_payment_payzcore->getPaymentByPaymentId($payload['payment_id']);
        }

        if (!$payment_record) {
            $this->debugLog('Webhook: no matching order found for payment ' . (isset($payload['payment_id']) ? $payload['payment_id'] : 'unknown'));
            $this->sendJsonResponse(200, array('ok' => true, 'note' => 'Payment not tracked by this store'));
            return;
        }

        $order_id = (int)$payment_record['order_id'];
        $order_info = $this->model_checkout_order->getOrder($order_id);

        if (!$order_info) {
            $this->debugLog('Webhook: order #' . $order_id . ' not found in OpenCart');
            $this->sendJsonResponse(200, array('ok' => true, 'note' => 'Order not found in store'));
            return;
        }

        // Process event
        $event = $payload['event'];
        $tx_hash = isset($payload['tx_hash']) ? $payload['tx_hash'] : '';
        $paid_amount = isset($payload['paid_amount']) ? $payload['paid_amount'] : '';
        $status = isset($payload['status']) ? $payload['status'] : '';

        // Determine token name from payload or local record
        $token_name = 'USDT';
        if (isset($payload['token'])) {
            $token_name = $payload['token'];
        } elseif (isset($payment_record['token']) && !empty($payment_record['token'])) {
            $token_name = $payment_record['token'];
        }

        switch ($event) {
            case 'payment.completed':
            case 'payment.overpaid':
                $completed_status_id = (int)$this->config->get('payment_payzcore_completed_status_id');
                if (!$completed_status_id) {
                    $completed_status_id = 5; // Default: Complete
                }

                $comment = 'Transaction detected and confirmed on blockchain.';
                $comment .= ' Amount: ' . $paid_amount . ' ' . $token_name;
                if ($tx_hash) {
                    $comment .= ' | TX: ' . $tx_hash;
                }
                if ($event === 'payment.overpaid') {
                    $comment .= ' (Overpaid - expected: ' . (isset($payload['expected_amount']) ? $payload['expected_amount'] : '') . ')';
                }

                $this->model_checkout_order->addOrderHistory($order_id, $completed_status_id, $comment, true);
                $this->model_extension_payment_payzcore->updatePaymentStatus($order_id, 'paid', $tx_hash);

                $this->debugLog('Order #' . $order_id . ' marked as completed. TX: ' . $tx_hash);
                break;

            case 'payment.expired':
                $expired_status_id = (int)$this->config->get('payment_payzcore_expired_status_id');
                if (!$expired_status_id) {
                    $expired_status_id = 14; // Default: Expired
                }

                $comment = 'Payment monitoring expired without receiving funds.';
                $this->model_checkout_order->addOrderHistory($order_id, $expired_status_id, $comment, false);
                $this->model_extension_payment_payzcore->updatePaymentStatus($order_id, 'expired');

                $this->debugLog('Order #' . $order_id . ' marked as expired');
                break;

            case 'payment.partial':
                $comment = 'Partial transaction detected.';
                $comment .= ' Received: ' . $paid_amount . ' ' . $token_name;
                $comment .= ' (Expected: ' . (isset($payload['expected_amount']) ? $payload['expected_amount'] : '') . ')';
                if ($tx_hash) {
                    $comment .= ' | TX: ' . $tx_hash;
                }

                // Keep current status, just add comment
                $this->model_checkout_order->addOrderHistory($order_id, $order_info['order_status_id'], $comment, false);
                $this->model_extension_payment_payzcore->updatePaymentStatus($order_id, 'partial', $tx_hash);

                $this->debugLog('Order #' . $order_id . ' partial payment received: ' . $paid_amount . ' ' . $token_name);
                break;

            case 'payment.cancelled':
                // Uses expired status or OpenCart default "Canceled" (7)
                $cancelled_status_id = (int)$this->config->get('payment_payzcore_expired_status_id') ?: 7;

                $comment = 'Payment was cancelled by the merchant.';
                $this->model_checkout_order->addOrderHistory($order_id, $cancelled_status_id, $comment, false);
                $this->model_extension_payment_payzcore->updatePaymentStatus($order_id, 'cancelled');

                $this->debugLog('Order #' . $order_id . ' marked as cancelled');
                break;

            default:
                $this->debugLog('Webhook: unhandled event type: ' . $event);
                break;
        }

        $this->sendJsonResponse(200, array('success' => true));
    }

    /**
     * AJAX endpoint for payment status polling.
     *
     * Called by the payment instructions page JavaScript to check
     * if the payment has been detected on the blockchain.
     *
     * @return void
     */
    public function status() {
        $this->load->model('extension/payment/payzcore');

        $order_id = isset($this->request->get['order_id']) ? (int)$this->request->get['order_id'] : 0;

        if (!$order_id) {
            $this->sendJsonResponse(400, array('error' => 'Missing order_id'));
            return;
        }

        // Security: verify this order belongs to the current session
        $session_order_id = isset($this->session->data['order_id']) ? (int)$this->session->data['order_id'] : 0;

        if ($order_id !== $session_order_id) {
            $this->sendJsonResponse(403, array('error' => 'Unauthorized'));
            return;
        }

        $payment = $this->model_extension_payment_payzcore->getPaymentByOrderId($order_id);

        if (!$payment) {
            $this->sendJsonResponse(404, array('error' => 'Payment not found'));
            return;
        }

        // Also check real-time status from PayzCore API
        $api_status = $payment['status'];

        try {
            $payzcore = $this->getApiClient();
            $response = $payzcore->getPayment($payment['payment_id']);

            if (isset($response['payment']['status'])) {
                $api_status = $response['payment']['status'];

                // Update local record if status changed
                if ($api_status !== $payment['status']) {
                    $tx_hash = isset($response['payment']['tx_hash']) ? $response['payment']['tx_hash'] : '';
                    $this->model_extension_payment_payzcore->updatePaymentStatus($order_id, $api_status, $tx_hash);
                }
            }
        } catch (\Exception $e) {
            // If API check fails, use local status
            $this->debugLog('Status check API error for order #' . $order_id . ': ' . $e->getMessage());
        }

        $result = array(
            'status'     => $api_status,
            'payment_id' => $payment['payment_id'],
            'order_id'   => $order_id,
        );

        // If completed, include redirect URL
        if (in_array($api_status, array('paid', 'overpaid'))) {
            $result['redirect'] = $this->url->link('checkout/success', '', true);
        }

        $this->sendJsonResponse(200, $result);
    }

    /**
     * Handle transaction hash submission for static wallet payments.
     *
     * When requires_txid is true, the customer submits their tx hash
     * which is forwarded to the PayzCore API confirm endpoint.
     *
     * @return void
     */
    public function confirmtx() {
        $this->load->language('extension/payment/payzcore');
        $this->load->model('extension/payment/payzcore');

        $order_id = isset($this->request->post['order_id']) ? (int)$this->request->post['order_id'] : 0;
        $tx_hash = isset($this->request->post['tx_hash']) ? trim($this->request->post['tx_hash']) : '';

        if (!$order_id || empty($tx_hash)) {
            $this->sendJsonResponse(400, array('error' => $this->language->get('error_txid_required')));
            return;
        }

        // Validate tx_hash format (hex string, 10-128 chars)
        $clean_hash = preg_replace('/^0x/i', '', $tx_hash);
        if (!preg_match('/^[a-fA-F0-9]{10,128}$/', $clean_hash)) {
            $this->sendJsonResponse(400, array('error' => 'Invalid transaction hash format'));
            return;
        }

        // Security: verify this order belongs to the current session
        $session_order_id = isset($this->session->data['order_id']) ? (int)$this->session->data['order_id'] : 0;

        if ($order_id !== $session_order_id) {
            $this->sendJsonResponse(403, array('error' => 'Unauthorized'));
            return;
        }

        $payment = $this->model_extension_payment_payzcore->getPaymentByOrderId($order_id);

        if (!$payment || empty($payment['confirm_endpoint'])) {
            $this->sendJsonResponse(404, array('error' => 'Payment not found'));
            return;
        }

        try {
            $payzcore = $this->getApiClient();
            $response = $payzcore->confirmPayment($payment['confirm_endpoint'], $tx_hash);

            $this->debugLog('TX hash submitted for order #' . $order_id . ': ' . $tx_hash);

            $this->sendJsonResponse(200, array('success' => true, 'message' => $this->language->get('text_txid_submitted')));
        } catch (\Exception $e) {
            $this->debugLog('ERROR confirming tx for order #' . $order_id . ': ' . $e->getMessage());
            $this->sendJsonResponse(400, array('error' => $e->getMessage()));
        }
    }

    /**
     * Render the payment instructions page.
     *
     * @param array $payment    Local payment record from database.
     * @param array $order_info OpenCart order info.
     *
     * @return void
     */
    private function renderPaymentPage($payment, $order_info) {
        $this->load->language('extension/payment/payzcore');

        // Admin-editable text settings with language file fallbacks
        $textMap = array(
            'text_payment_title'    => 'payment_payzcore_text_payment_title',
            'text_payment_info'     => 'payment_payzcore_text_payment_info',
            'text_send_exactly'     => 'payment_payzcore_text_send_exactly',
            'text_to_address'       => 'payment_payzcore_text_to_address',
            'text_copy'             => 'payment_payzcore_text_copy',
            'text_copied'           => 'payment_payzcore_text_copied',
            'text_or_scan_qr'       => 'payment_payzcore_text_scan_qr',
            'text_time_remaining'   => 'payment_payzcore_text_time_remaining',
            'text_status_checking'  => 'payment_payzcore_text_status_checking',
            'text_payment_confirmed'=> 'payment_payzcore_text_status_confirmed',
            'text_payment_expired'  => 'payment_payzcore_text_status_expired',
            'text_payment_detected' => 'payment_payzcore_text_status_detected',
            'text_important'        => 'payment_payzcore_text_important',
            'text_warning_exact'    => 'payment_payzcore_text_warning_exact',
            'text_warning_network'  => 'payment_payzcore_text_warning_network',
            'text_warning_address'  => 'payment_payzcore_text_warning_address',
            'text_explorer_link'    => 'payment_payzcore_text_explorer_link',
        );

        $data = array();
        foreach ($textMap as $tplKey => $configKey) {
            $value = $this->config->get($configKey);
            $data[$tplKey] = !empty($value) ? $value : $this->language->get($tplKey);
        }

        // Set page title from the (possibly customized) payment title
        $this->document->setTitle($data['text_payment_title']);

        // Non-customizable language strings (loaded from language file only)
        $staticLangKeys = array(
            'text_network', 'text_waiting', 'text_order_id',
            'text_trc20', 'text_bep20', 'text_erc20', 'text_polygon', 'text_arbitrum',
            'text_redirect',
            'text_txid_label', 'text_txid_placeholder', 'text_txid_submit',
            'text_txid_submitted', 'text_txid_submitting', 'text_txid_invalid_hex',
            'text_partial_guidance', 'text_connection_issue',
            'text_copy_amount', 'text_copied_amount',
        );

        foreach ($staticLangKeys as $key) {
            $data[$key] = $this->language->get($key);
        }

        // Payment data
        $data['payment_id'] = $payment['payment_id'];
        $data['address'] = $payment['address'];
        $data['amount'] = $payment['amount'];
        $data['network'] = $payment['network'];
        $data['token'] = isset($payment['token']) && !empty($payment['token']) ? $payment['token'] : 'USDT';
        $data['qr_code'] = $payment['qr_code'];
        $data['order_id'] = $payment['order_id'];
        $data['status'] = $payment['status'];

        // Static wallet fields
        $data['notice'] = isset($payment['notice']) && !empty($payment['notice']) ? $payment['notice'] : '';
        $data['requires_txid'] = isset($payment['requires_txid']) ? (int)$payment['requires_txid'] : 0;

        if ($data['requires_txid']) {
            $data['confirmtx_url'] = $this->url->link(
                'extension/payment/payzcore/confirmtx',
                '',
                true
            );
        } else {
            $data['confirmtx_url'] = '';
        }

        // Network display
        $data['network_label'] = $this->getNetworkLabel($payment['network']);

        // Token display
        $data['token_label'] = $data['token'];

        // Calculate remaining time
        $data['expires_at'] = $payment['expires_at'];
        if ($payment['expires_at']) {
            $expires_ts = strtotime($payment['expires_at']);
            $now = time();
            $data['seconds_remaining'] = max(0, $expires_ts - $now);
        } else {
            $data['seconds_remaining'] = 3600;
        }

        // Status check URL
        $data['status_url'] = $this->url->link(
            'extension/payment/payzcore/status',
            'order_id=' . $payment['order_id'],
            true
        );

        // Success redirect URL
        $data['success_url'] = $this->url->link('checkout/success', '', true);

        // Store name
        $data['store_name'] = $this->config->get('config_name');

        // Blockchain explorer link
        $data['explorer_url'] = $this->getExplorerUrl($payment['network'], $payment['address']);

        // Layout parts
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('extension/payment/payzcore_confirm', $data));
    }

    /**
     * Get a human-readable label for a blockchain network code.
     *
     * @param string $network Network code (TRC20, BEP20, ERC20, POLYGON, ARBITRUM).
     *
     * @return string Human-readable network label.
     */
    private function getNetworkLabel($network) {
        $this->load->language('extension/payment/payzcore');

        $labels = array(
            'TRC20'    => $this->language->get('text_trc20'),
            'BEP20'    => $this->language->get('text_bep20'),
            'ERC20'    => $this->language->get('text_erc20'),
            'POLYGON'  => $this->language->get('text_polygon'),
            'ARBITRUM' => $this->language->get('text_arbitrum'),
        );

        return isset($labels[$network]) ? $labels[$network] : $network;
    }

    /**
     * Get the blockchain explorer URL for an address on a given network.
     *
     * @param string $network Network code.
     * @param string $address Blockchain address.
     *
     * @return string Explorer URL.
     */
    private function getExplorerUrl($network, $address) {
        $explorers = array(
            'TRC20'    => 'https://tronscan.org/#/address/',
            'BEP20'    => 'https://bscscan.com/address/',
            'ERC20'    => 'https://etherscan.io/address/',
            'POLYGON'  => 'https://polygonscan.com/address/',
            'ARBITRUM' => 'https://arbiscan.io/address/',
        );

        $base = isset($explorers[$network]) ? $explorers[$network] : 'https://tronscan.org/#/address/';

        return $base . $address;
    }

    /**
     * Get an initialized PayzCore API client.
     *
     * @return Payzcore API client instance.
     */
    private function getApiClient() {
        require_once(DIR_SYSTEM . 'library/payzcore.php');

        $api_key = $this->config->get('payment_payzcore_api_key');
        $api_url = $this->config->get('payment_payzcore_api_url') ?: 'https://api.payzcore.com';

        return new Payzcore($api_key, $api_url);
    }

    /**
     * Send a JSON response.
     *
     * @param int   $status_code HTTP status code.
     * @param array $data        Response data.
     *
     * @return void
     */
    private function sendJsonResponse($status_code, $data) {
        http_response_code($status_code);
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($data));
    }

    /**
     * Write a debug log message (if debug mode is enabled).
     *
     * Uses the OpenCart Log class from the registry.
     *
     * @param string $message Log message.
     *
     * @return void
     */
    private function debugLog($message) {
        if ($this->config->get('payment_payzcore_debug')) {
            $log = new Log('payzcore.log');
            $log->write($message);
        }
    }
}
