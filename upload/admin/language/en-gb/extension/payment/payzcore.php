<?php
/**
 * PayzCore - Admin Language File (en-gb)
 *
 * @package    PayzCore OpenCart Extension
 * @version    1.0.0
 */

// Heading
$_['heading_title']                = 'PayzCore - Stablecoin Monitoring';

// Text
$_['text_extension']               = 'Extensions';
$_['text_success']                 = 'Success: You have modified PayzCore settings!';
$_['text_edit']                    = 'Edit PayzCore Settings';
$_['text_home']                    = 'Home';
$_['text_enabled']                 = 'Enabled';
$_['text_disabled']                = 'Disabled';
$_['text_yes']                     = 'Yes';
$_['text_no']                      = 'No';
$_['text_all_zones']               = 'All Zones';
$_['text_payzcore']                = '<a href="https://payzcore.com" target="_blank" style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;color:#06b6d4;"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1500 1500" fill="#06b6d4" style="height:22px;width:22px;"><path d="M 475.7 1024.3 L 475.7 1258.2 L 709.6 1258.2 L 709.6 1024.3 L 1042.7 1024.3 C 1102.7 1024.3 1154.9 999.9 1194.4 960.8 C 1233.6 921.6 1257.9 869 1257.9 809.1 L 1257.9 457 C 1257.9 397 1233.6 344.8 1194.4 305.3 C 1155.2 266.1 1102.7 241.8 1042.7 241.8 L 690.9 241.8 C 631 241.8 578.7 266.1 539.2 305.3 C 499.7 344.5 475.7 397 475.7 457 L 475.7 790.1 L 7.9 790.1 L 7.9 1024 L 475.7 1024 Z M 241.8 790.4 L 241.8 457 C 241.8 333.5 292.2 221.3 372.7 138.8 C 455.2 58.3 567.4 7.9 690.9 7.9 L 1042.7 7.9 C 1166.2 7.9 1278.4 58.3 1360.9 138.8 C 1441.7 221.3 1492.1 333.5 1492.1 457 L 1492.1 808.8 C 1492.1 932.3 1441.7 1044.5 1361.2 1127 C 1279 1207.4 1166.5 1257.9 1043 1257.9 L 709.6 1257.9 L 709.6 1492.1 L 475.7 1492.1 L 475.7 1258.2 L 241.8 1258.2 Z M 938.2 475.7 L 796 475.7 C 773.4 475.7 751.2 485.2 736 501.8 C 719.1 516.7 709.9 539.2 709.9 561.8 L 709.9 790.1 L 938.2 790.1 C 960.8 790.1 983 780.6 998.2 764 C 1015.1 749.1 1024.3 726.5 1024.3 704 L 1024.3 561.8 C 1024.3 539.2 1014.8 517 998.2 501.8 C 983 485.2 960.5 475.7 938.2 475.7 Z"/></svg> PayzCore</a>';
$_['text_connection_status']       = 'Connection Status';
$_['text_connected']               = 'Connected';
$_['text_not_synced']              = 'Not synced';
$_['text_save_to_sync']            = 'Save settings with a valid API key to sync available networks.';
$_['text_last_sync']               = 'Last sync';
$_['text_available_networks']      = 'Available networks';
$_['text_networks_help']           = 'Available networks and tokens are determined by your wallet configuration at <a href="https://app.payzcore.com" target="_blank">app.payzcore.com</a>. Save settings to refresh.';

// Entry
$_['entry_api_key']                = 'API Key';
$_['entry_webhook_secret']         = 'Webhook Secret';
$_['entry_api_url']                = 'API URL';
$_['entry_address']                = 'Static Wallet Address';
$_['entry_expires_in']             = 'Payment Expiry (seconds)';
$_['entry_order_status']           = 'Pending Order Status';
$_['entry_completed_status']       = 'Completed Order Status';
$_['entry_expired_status']         = 'Expired Order Status';
$_['entry_geo_zone']               = 'Geo Zone';
$_['entry_status']                 = 'Status';
$_['entry_sort_order']             = 'Sort Order';
$_['entry_debug']                  = 'Debug Logging';

// Help
$_['help_api_key']                 = 'Your PayzCore project API key (starts with pk_live_ or pk_test_). Found in your PayzCore dashboard under Projects.';
$_['help_webhook_secret']          = 'Your PayzCore webhook secret (starts with whsec_). Used to verify incoming webhook notifications. Found in your PayzCore dashboard under Projects.';
$_['help_api_url']                 = 'PayzCore API endpoint. Default: https://api.payzcore.com. Only change if using a self-hosted instance.';
$_['help_address']                 = 'Optional. A static blockchain wallet address to use for all payments instead of HD-derived addresses. When set, customers may need to submit their transaction hash manually. Leave empty to use PayzCore HD wallet derivation.';
$_['help_expires_in']              = 'Time in seconds before a payment monitoring request expires. Default: 3600 (1 hour). Minimum: 600 (10 minutes).';
$_['help_order_status']            = 'Order status when a payment monitoring request is created and waiting for the customer to send stablecoin.';
$_['help_completed_status']        = 'Order status when a payment is detected and confirmed on the blockchain.';
$_['help_expired_status']          = 'Order status when a payment monitoring request expires without receiving funds.';
$_['help_geo_zone']                = 'Restrict this payment method to a specific geo zone. Select "All Zones" to make it available everywhere.';
$_['help_debug']                   = 'Enable detailed logging for troubleshooting. Logs are written to the OpenCart error log. Disable in production.';

// Tab
$_['tab_general']                  = 'General';
$_['tab_order_status']             = 'Order Status';
$_['tab_texts']                    = 'Payment Page Texts';

// Payment Page Text Settings
$_['entry_text_payment_title']     = 'Payment Page Title';
$_['entry_text_payment_info']      = 'Payment Info Text';
$_['entry_text_send_exactly']      = 'Send Exactly Label';
$_['entry_text_to_address']        = 'Address Label';
$_['entry_text_copy']              = 'Copy Button';
$_['entry_text_copied']            = 'Copied Text';
$_['entry_text_scan_qr']           = 'QR Code Label';
$_['entry_text_time_remaining']    = 'Time Remaining Label';
$_['entry_text_status_checking']   = 'Status: Checking';
$_['entry_text_status_confirmed']  = 'Status: Confirmed';
$_['entry_text_status_expired']    = 'Status: Expired';
$_['entry_text_status_detected']   = 'Status: Detected';
$_['entry_text_important']         = 'Important Label';
$_['entry_text_warning_exact']     = 'Warning: Exact Amount';
$_['entry_text_warning_network']   = 'Warning: Network';
$_['entry_text_warning_address']   = 'Warning: Address';
$_['entry_text_explorer_link']     = 'Explorer Link Text';
$_['help_texts']                   = 'Customize all customer-facing texts on the payment page. Change these to display your preferred language.';

// Placeholder
$_['placeholder_api_key']          = 'pk_live_xxxxxxxxxxxxxxxx';
$_['placeholder_webhook_secret']   = 'whsec_xxxxxxxxxxxxxxxx';
$_['placeholder_api_url']          = 'https://api.payzcore.com';
$_['placeholder_address']          = 'TXxxxxx... or 0xxxxx... (optional)';

// Error
$_['error_permission']             = 'Warning: You do not have permission to modify PayzCore settings!';
$_['error_api_key']                = 'API Key is required! Enter your PayzCore project API key.';
$_['error_webhook_secret']         = 'Webhook Secret is required! Enter your PayzCore webhook secret.';
$_['error_api_url']                = 'API URL is required and must be a valid URL!';
$_['error_expires_in']             = 'Payment expiry must be at least 600 seconds (10 minutes).';
// Info
$_['info_webhook_url']             = 'Webhook URL';
$_['info_webhook_help']            = 'Configure this URL as your webhook endpoint in the PayzCore dashboard. PayzCore will send payment status notifications to this URL.';
$_['info_description']             = 'PayzCore is a non-custodial blockchain transaction monitoring service. It watches blockchain addresses for incoming stablecoin transfers (USDT, USDC) on multiple networks (TRC20, BEP20, ERC20, Polygon, Arbitrum) and sends webhook notifications when transactions are detected. PayzCore does not hold, transmit, or custody any funds.';
