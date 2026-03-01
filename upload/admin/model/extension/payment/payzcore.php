<?php
/**
 * PayzCore - Admin Model
 *
 * Handles database table creation/removal for the PayzCore extension.
 *
 * @package    PayzCore OpenCart Extension
 * @version    1.0.0
 */
class ModelExtensionPaymentPayzcore extends Model {
    /**
     * Install the extension.
     *
     * Creates the payzcore_payments table to track payment monitoring requests
     * associated with OpenCart orders.
     *
     * @return void
     */
    public function install() {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "payzcore_payments` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `order_id` INT(11) NOT NULL,
                `payment_id` VARCHAR(64) NOT NULL DEFAULT '',
                `address` VARCHAR(128) NOT NULL DEFAULT '',
                `amount` DECIMAL(20,8) NOT NULL DEFAULT '0.00000000',
                `network` VARCHAR(16) NOT NULL DEFAULT '',
                `token` VARCHAR(10) NOT NULL DEFAULT 'USDT',
                `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
                `tx_hash` VARCHAR(128) NOT NULL DEFAULT '',
                `qr_code` TEXT,
                `expires_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_order_id` (`order_id`),
                KEY `idx_payment_id` (`payment_id`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        // v1.0.0 migration: add token column + widen network column for existing installs
        $columns = $this->db->query(
            "SHOW COLUMNS FROM `" . DB_PREFIX . "payzcore_payments` LIKE 'token'"
        );

        if (!$columns->num_rows) {
            $this->db->query(
                "ALTER TABLE `" . DB_PREFIX . "payzcore_payments`
                 ADD COLUMN `token` VARCHAR(10) NOT NULL DEFAULT 'USDT' AFTER `network`"
            );
        }

        $network_col = $this->db->query(
            "SHOW COLUMNS FROM `" . DB_PREFIX . "payzcore_payments` WHERE Field = 'network'"
        );

        if ($network_col->num_rows && strpos($network_col->row['Type'], '16') === false) {
            $this->db->query(
                "ALTER TABLE `" . DB_PREFIX . "payzcore_payments`
                 MODIFY COLUMN `network` VARCHAR(16) NOT NULL DEFAULT ''"
            );
        }

        // v1.1.0 migration: add static wallet columns
        $notice_col = $this->db->query(
            "SHOW COLUMNS FROM `" . DB_PREFIX . "payzcore_payments` LIKE 'notice'"
        );

        if (!$notice_col->num_rows) {
            $this->db->query(
                "ALTER TABLE `" . DB_PREFIX . "payzcore_payments`
                 ADD COLUMN `notice` TEXT DEFAULT NULL AFTER `qr_code`,
                 ADD COLUMN `requires_txid` TINYINT(1) NOT NULL DEFAULT 0 AFTER `notice`,
                 ADD COLUMN `confirm_endpoint` VARCHAR(255) NOT NULL DEFAULT '' AFTER `requires_txid`"
            );
        }
    }

    /**
     * Uninstall the extension.
     *
     * Drops the payzcore_payments table.
     *
     * @return void
     */
    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "payzcore_payments`");
    }
}
