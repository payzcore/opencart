<?php
/**
 * PayzCore - Admin Controller
 *
 * Handles the admin settings page for the PayzCore extension.
 * Allows store administrators to configure API credentials,
 * blockchain network, order status mappings, and other settings.
 *
 * @package    PayzCore OpenCart Extension
 * @version    1.0.0
 */
class ControllerExtensionPaymentPayzcore extends Controller {
    /**
     * @var array Validation errors
     */
    private $error = array();

    /**
     * Display and process the settings form.
     *
     * @return void
     */
    public function index() {
        $this->load->language('extension/payment/payzcore');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_payzcore', $this->request->post);

            // Fetch and cache config from PayzCore API
            $api_key = trim($this->request->post['payment_payzcore_api_key'] ?? '');
            $api_url = trim($this->request->post['payment_payzcore_api_url'] ?? 'https://api.payzcore.com');
            if (!empty($api_key)) {
                $this->fetchAndCacheConfig($api_url, $api_key);
            }
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link(
                'marketplace/extension',
                'user_token=' . $this->session->data['user_token'] . '&type=payment',
                true
            ));
        }

        // Collect error messages
        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        $data['error_api_key'] = isset($this->error['api_key']) ? $this->error['api_key'] : '';
        $data['error_webhook_secret'] = isset($this->error['webhook_secret']) ? $this->error['webhook_secret'] : '';
        $data['error_api_url'] = isset($this->error['api_url']) ? $this->error['api_url'] : '';
        $data['error_expires_in'] = isset($this->error['expires_in']) ? $this->error['expires_in'] : '';

        // Breadcrumbs
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link(
                'marketplace/extension',
                'user_token=' . $this->session->data['user_token'] . '&type=payment',
                true
            ),
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link(
                'extension/payment/payzcore',
                'user_token=' . $this->session->data['user_token'],
                true
            ),
        );

        // Action URLs
        $data['action'] = $this->url->link(
            'extension/payment/payzcore',
            'user_token=' . $this->session->data['user_token'],
            true
        );
        $data['cancel'] = $this->url->link(
            'marketplace/extension',
            'user_token=' . $this->session->data['user_token'] . '&type=payment',
            true
        );

        // Webhook callback URL for display
        $data['webhook_url'] = HTTPS_CATALOG . 'index.php?route=extension/payment/payzcore/callback';

        // Load current settings or POST data
        $settings = array(
            'payment_payzcore_api_key',
            'payment_payzcore_webhook_secret',
            'payment_payzcore_api_url',
            'payment_payzcore_address',
            'payment_payzcore_expires_in',
            'payment_payzcore_order_status_id',
            'payment_payzcore_completed_status_id',
            'payment_payzcore_expired_status_id',
            'payment_payzcore_geo_zone_id',
            'payment_payzcore_status',
            'payment_payzcore_sort_order',
            'payment_payzcore_debug',
        );

        foreach ($settings as $setting) {
            $key = str_replace('payment_payzcore_', '', $setting);
            if (isset($this->request->post[$setting])) {
                $data[$setting] = $this->request->post[$setting];
            } else {
                $data[$setting] = $this->config->get($setting);
            }
        }

        // Defaults
        if (empty($data['payment_payzcore_api_url'])) {
            $data['payment_payzcore_api_url'] = 'https://api.payzcore.com';
        }
        if (empty($data['payment_payzcore_expires_in'])) {
            $data['payment_payzcore_expires_in'] = 3600;
        }

        // Load cached config status for display
        $cached_json = $this->config->get('payment_payzcore_cached_config');
        $data['cached_config'] = $cached_json;
        $data['cached_at'] = $this->config->get('payment_payzcore_cached_at');
        $data['cached_networks'] = array();

        if (!empty($cached_json)) {
            $parsed = json_decode($cached_json, true);
            if (is_array($parsed) && !empty($parsed['networks'])) {
                $data['cached_networks'] = $parsed['networks'];
            }
        }

        // Payment page text settings (admin-editable, with English defaults)
        $textDefaults = array(
            'payment_payzcore_text_payment_title'    => 'Complete Your Payment',
            'payment_payzcore_text_payment_info'     => 'Send the exact amount to the address below. Your order will be confirmed automatically once the transaction is detected on the blockchain.',
            'payment_payzcore_text_send_exactly'     => 'Send exactly',
            'payment_payzcore_text_to_address'       => 'To this address',
            'payment_payzcore_text_copy'             => 'Copy',
            'payment_payzcore_text_copied'           => 'Copied!',
            'payment_payzcore_text_scan_qr'          => 'Or scan QR code',
            'payment_payzcore_text_time_remaining'   => 'Time remaining',
            'payment_payzcore_text_status_checking'  => 'Checking blockchain for your transaction',
            'payment_payzcore_text_status_confirmed' => 'Payment confirmed! Redirecting...',
            'payment_payzcore_text_status_expired'   => 'Payment request has expired.',
            'payment_payzcore_text_status_detected'  => 'Transaction detected! Confirming...',
            'payment_payzcore_text_important'        => 'Important',
            'payment_payzcore_text_warning_exact'    => 'Send the exact amount shown. Sending a different amount may delay processing.',
            'payment_payzcore_text_warning_network'  => 'Make sure you are sending on the correct network. Sending on the wrong network may result in lost funds.',
            'payment_payzcore_text_warning_address'  => 'Double-check the address before sending. Blockchain transactions cannot be reversed.',
            'payment_payzcore_text_explorer_link'    => 'View address on blockchain explorer',
        );

        foreach ($textDefaults as $textKey => $textDefault) {
            if (isset($this->request->post[$textKey])) {
                $data[$textKey] = $this->request->post[$textKey];
            } elseif ($this->config->get($textKey)) {
                $data[$textKey] = $this->config->get($textKey);
            } else {
                $data[$textKey] = $textDefault;
            }
        }

        // Load order statuses for dropdown
        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        // Load geo zones for dropdown
        $this->load->model('localisation/geo_zone');
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        // Success message
        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        // Language strings
        $lang_keys = array(
            'heading_title', 'text_edit', 'text_enabled', 'text_disabled', 'text_all_zones',
            'text_yes', 'text_no',
            'text_connection_status', 'text_connected', 'text_not_synced', 'text_save_to_sync',
            'text_last_sync', 'text_available_networks', 'text_networks_help',
            'entry_api_key', 'entry_webhook_secret', 'entry_api_url',
            'entry_address', 'entry_expires_in', 'entry_order_status', 'entry_completed_status',
            'entry_expired_status', 'entry_geo_zone', 'entry_status', 'entry_sort_order',
            'entry_debug',
            'entry_text_payment_title', 'entry_text_payment_info', 'entry_text_send_exactly',
            'entry_text_to_address', 'entry_text_copy', 'entry_text_copied',
            'entry_text_scan_qr', 'entry_text_time_remaining',
            'entry_text_status_checking', 'entry_text_status_confirmed',
            'entry_text_status_expired', 'entry_text_status_detected',
            'entry_text_important', 'entry_text_warning_exact',
            'entry_text_warning_network', 'entry_text_warning_address',
            'entry_text_explorer_link',
            'help_api_key', 'help_webhook_secret', 'help_api_url',
            'help_address', 'help_expires_in', 'help_order_status', 'help_completed_status',
            'help_expired_status', 'help_geo_zone', 'help_debug',
            'help_texts',
            'tab_general', 'tab_order_status', 'tab_texts',
            'placeholder_api_key', 'placeholder_webhook_secret', 'placeholder_api_url', 'placeholder_address',
            'info_webhook_url', 'info_webhook_help', 'info_description',
            'button_save', 'button_cancel',
        );

        foreach ($lang_keys as $key) {
            $data[$key] = $this->language->get($key);
        }

        // Render
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/payzcore', $data));
    }

    /**
     * Run on extension install.
     *
     * @return void
     */
    public function install() {
        $this->load->model('extension/payment/payzcore');
        $this->model_extension_payment_payzcore->install();
    }

    /**
     * Run on extension uninstall.
     *
     * @return void
     */
    public function uninstall() {
        $this->load->model('extension/payment/payzcore');
        $this->model_extension_payment_payzcore->uninstall();
    }

    /**
     * Fetch project config from PayzCore API and cache it.
     *
     * @param string $api_url API base URL
     * @param string $api_key Project API key
     * @return void
     */
    private function fetchAndCacheConfig($api_url, $api_key) {
        $url = rtrim($api_url, '/') . '/v1/config';

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => array(
                'x-api-key: ' . $api_key,
                'Accept: application/json',
                'User-Agent: payzcore-opencart/1.0.0',
            ),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ));

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300 && $body) {
            $data = json_decode($body, true);
            if (is_array($data)) {
                $this->load->model('setting/setting');
                $this->model_setting_setting->editSetting('payment_payzcore_cached', array(
                    'payment_payzcore_cached_config' => json_encode($data),
                    'payment_payzcore_cached_at'     => date('Y-m-d H:i:s'),
                ));
            }
        }
    }

    /**
     * Validate the settings form.
     *
     * @return bool True if valid.
     */
    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/payment/payzcore')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        $api_key = trim(isset($this->request->post['payment_payzcore_api_key']) ? $this->request->post['payment_payzcore_api_key'] : '');
        if (empty($api_key)) {
            $this->error['api_key'] = $this->language->get('error_api_key');
        }

        $webhook_secret = trim(isset($this->request->post['payment_payzcore_webhook_secret']) ? $this->request->post['payment_payzcore_webhook_secret'] : '');
        if (empty($webhook_secret)) {
            $this->error['webhook_secret'] = $this->language->get('error_webhook_secret');
        }

        $api_url = trim(isset($this->request->post['payment_payzcore_api_url']) ? $this->request->post['payment_payzcore_api_url'] : '');
        if (empty($api_url) || !filter_var($api_url, FILTER_VALIDATE_URL)) {
            $this->error['api_url'] = $this->language->get('error_api_url');
        }

        $expires_in = (int)(isset($this->request->post['payment_payzcore_expires_in']) ? $this->request->post['payment_payzcore_expires_in'] : 0);
        if ($expires_in < 600) {
            $this->error['expires_in'] = $this->language->get('error_expires_in');
        }

        return !$this->error;
    }
}
