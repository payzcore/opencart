<?php
/**
 * PayzCore - Catalog Model
 *
 * Handles payment method availability and database operations
 * for the PayzCore extension on the storefront.
 *
 * @package    PayzCore OpenCart Extension
 * @version    1.0.0
 */
class ModelExtensionPaymentPayzcore extends Model {
    /**
     * Check if the payment method is available for the current order.
     *
     * Called by OpenCart during checkout to determine which payment
     * methods to display. Checks geo zone restrictions and status.
     *
     * @param array $address Customer billing/payment address.
     * @param float $total   Order total.
     *
     * @return array|void Payment method info array, or nothing if unavailable.
     */
    public function getMethod($address, $total) {
        $this->load->language('extension/payment/payzcore');

        // Check if minimum total requirement is met (must be > 0)
        if ($total <= 0) {
            return;
        }

        // If store currency is not USD, verify that USD rate is configured
        if ($this->config->get('config_currency') !== 'USD') {
            $usd_value = $this->currency->convert(1, $this->config->get('config_currency'), 'USD');
            if ($usd_value <= 0) {
                return;
            }
        }

        // Geo zone check
        $geo_zone_id = (int)$this->config->get('payment_payzcore_geo_zone_id');

        if ($geo_zone_id) {
            $query = $this->db->query(
                "SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone`
                 WHERE `geo_zone_id` = '" . (int)$geo_zone_id . "'
                 AND `country_id` = '" . (int)$address['country_id'] . "'
                 AND (`zone_id` = '" . (int)$address['zone_id'] . "' OR `zone_id` = '0')"
            );

            if (!$query->num_rows) {
                return;
            }
        }

        // Build network and token display
        $enabled_networks = $this->getEnabledNetworks();
        $token = $this->getDefaultToken();

        $network_labels = array(
            'TRC20'    => 'TRC20 (Tron)',
            'BEP20'    => 'BEP20 (BSC)',
            'ERC20'    => 'ERC20 (Ethereum)',
            'POLYGON'  => 'Polygon',
            'ARBITRUM' => 'Arbitrum',
        );

        if (count($enabled_networks) === 1) {
            $network = $enabled_networks[0];
            $network_label = isset($network_labels[$network]) ? $network_labels[$network] : $network;

            if ($network === 'TRC20') { $token = 'USDT'; }

            $title = str_replace('USDT', $token, $this->language->get('text_title'));
            $title .= ' - ' . $network_label;
        } else {
            $title = $this->language->get('text_title_multi') . ' - Multiple networks';
        }

        return array(
            'code'       => 'payzcore',
            'title'      => $title,
            'terms'      => '',
            'sort_order' => (int)$this->config->get('payment_payzcore_sort_order'),
        );
    }

    /**
     * Get the list of enabled blockchain networks from cached API config.
     *
     * Networks are fetched from the PayzCore API when admin saves settings
     * and stored in payment_payzcore_cached_config. Falls back to TRC20
     * if no cached config is available.
     *
     * @return array List of network codes (e.g. ['TRC20', 'BEP20']).
     */
    public function getEnabledNetworks() {
        $cached_json = $this->config->get('payment_payzcore_cached_config');

        if (!empty($cached_json)) {
            $cached = json_decode($cached_json, true);
            if (is_array($cached) && !empty($cached['networks'])) {
                $networks = array();
                foreach ($cached['networks'] as $c) {
                    if (isset($c['network'])) {
                        $networks[] = $c['network'];
                    }
                }
                if (!empty($networks)) {
                    return $networks;
                }
            }
        }

        // No cached config available - default to TRC20
        return array('TRC20');
    }

    /**
     * Get the default token from cached API config.
     *
     * @return string Token identifier (USDT or USDC).
     */
    public function getDefaultToken() {
        $cached_json = $this->config->get('payment_payzcore_cached_config');

        if (!empty($cached_json)) {
            $cached = json_decode($cached_json, true);
            if (is_array($cached) && !empty($cached['default_token'])) {
                return $cached['default_token'];
            }
        }

        return 'USDT';
    }

    /**
     * Store a payment monitoring record linked to an order.
     *
     * @param int   $order_id     OpenCart order ID.
     * @param array $payment_data Payment data from PayzCore API response:
     *   - payment_id (string): PayzCore payment UUID.
     *   - address (string):    Blockchain address for receiving USDT.
     *   - amount (string):     Expected amount with random cents.
     *   - network (string):    TRC20, BEP20, ERC20, POLYGON, or ARBITRUM.
     *   - token (string):     USDT or USDC.
     *   - status (string):     Payment status.
     *   - qr_code (string):    Base64-encoded QR code image.
     *   - expires_at (string): ISO 8601 expiry timestamp.
     *
     * @return void
     */
    public function addPayment($order_id, $payment_data) {
        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "payzcore_payments` SET
            `order_id` = '" . (int)$order_id . "',
            `payment_id` = '" . $this->db->escape($payment_data['payment_id']) . "',
            `address` = '" . $this->db->escape($payment_data['address']) . "',
            `amount` = '" . (float)$payment_data['amount'] . "',
            `network` = '" . $this->db->escape($payment_data['network']) . "',
            `token` = '" . $this->db->escape(isset($payment_data['token']) ? $payment_data['token'] : 'USDT') . "',
            `status` = '" . $this->db->escape($payment_data['status']) . "',
            `qr_code` = '" . $this->db->escape(isset($payment_data['qr_code']) ? $payment_data['qr_code'] : '') . "',
            `notice` = " . (isset($payment_data['notice']) ? "'" . $this->db->escape($payment_data['notice']) . "'" : "NULL") . ",
            `requires_txid` = '" . (isset($payment_data['requires_txid']) ? (int)$payment_data['requires_txid'] : 0) . "',
            `confirm_endpoint` = '" . $this->db->escape(isset($payment_data['confirm_endpoint']) ? $payment_data['confirm_endpoint'] : '') . "',
            `expires_at` = " . (isset($payment_data['expires_at']) ? "'" . $this->db->escape($payment_data['expires_at']) . "'" : "NULL") . ",
            `created_at` = NOW()"
        );
    }

    /**
     * Get the payment record for an order.
     *
     * @param int $order_id OpenCart order ID.
     *
     * @return array|false Payment record or false if not found.
     */
    public function getPaymentByOrderId($order_id) {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "payzcore_payments`
             WHERE `order_id` = '" . (int)$order_id . "'
             ORDER BY `id` DESC
             LIMIT 1"
        );

        return $query->num_rows ? $query->row : false;
    }

    /**
     * Get the payment record by PayzCore payment ID.
     *
     * @param string $payment_id PayzCore payment UUID.
     *
     * @return array|false Payment record or false if not found.
     */
    public function getPaymentByPaymentId($payment_id) {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "payzcore_payments`
             WHERE `payment_id` = '" . $this->db->escape($payment_id) . "'
             LIMIT 1"
        );

        return $query->num_rows ? $query->row : false;
    }

    /**
     * Get the payment record by external order ID prefix.
     *
     * External order IDs are stored as "oc-{order_id}" in PayzCore.
     *
     * @param string $external_order_id External order ID string.
     *
     * @return array|false Payment record or false if not found.
     */
    public function getPaymentByExternalOrderId($external_order_id) {
        // Extract order_id from "oc-123" format
        $order_id = (int)str_replace('oc-', '', $external_order_id);

        if ($order_id <= 0) {
            return false;
        }

        return $this->getPaymentByOrderId($order_id);
    }

    /**
     * Update payment status and optionally the transaction hash.
     *
     * @param int    $order_id OpenCart order ID.
     * @param string $status   New status value.
     * @param string $tx_hash  Transaction hash (optional).
     *
     * @return void
     */
    public function updatePaymentStatus($order_id, $status, $tx_hash = '') {
        $sql = "UPDATE `" . DB_PREFIX . "payzcore_payments` SET
                `status` = '" . $this->db->escape($status) . "'";

        if (!empty($tx_hash)) {
            $sql .= ", `tx_hash` = '" . $this->db->escape($tx_hash) . "'";
        }

        $sql .= " WHERE `order_id` = '" . (int)$order_id . "'";

        $this->db->query($sql);
    }
}
