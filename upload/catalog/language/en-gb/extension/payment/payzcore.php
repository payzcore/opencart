<?php
/**
 * PayzCore - Catalog Language File (en-gb)
 *
 * @package    PayzCore OpenCart Extension
 * @version    1.0.0
 */

// Text
$_['text_title']              = 'Pay with USDT (Stablecoin)';
$_['text_title_multi']        = 'Pay with Stablecoin (USDT/USDC)';
$_['text_description']        = 'Pay using stablecoin via blockchain transfer. Your order will be confirmed automatically once the transaction is detected.';
$_['text_select_network']     = 'Select Blockchain Network';
$_['text_select_token']       = 'Select Stablecoin';
$_['text_trc20_note']         = 'USDT only (USDC not available on TRC20)';

// Payment page
$_['text_payment_title']      = 'Complete Your Payment';
$_['text_payment_info']       = 'Send the exact amount to the address below. Your order will be confirmed automatically once the transaction is detected on the blockchain.';
$_['text_send_exactly']       = 'Send exactly';
$_['text_to_address']         = 'To this address';
$_['text_network']            = 'Network';
$_['text_time_remaining']     = 'Time remaining';
$_['text_or_scan_qr']        = 'Or scan QR code';
$_['text_waiting']            = 'Waiting for transaction...';
$_['text_status_checking']    = 'Checking blockchain for your transaction';
$_['text_payment_detected']   = 'Transaction detected! Confirming...';
$_['text_payment_confirmed']  = 'Payment confirmed! Redirecting...';
$_['text_payment_expired']    = 'Payment request has expired.';
$_['text_copied']             = 'Copied!';
$_['text_copy']               = 'Copy';
$_['text_order_id']           = 'Order #';
$_['text_usdt']               = 'USDT';
$_['text_trc20']              = 'TRC20 (Tron)';
$_['text_bep20']              = 'BEP20 (BSC)';
$_['text_erc20']              = 'ERC20 (Ethereum)';
$_['text_polygon']            = 'POLYGON (Polygon)';
$_['text_arbitrum']           = 'ARBITRUM (Arbitrum)';
$_['text_important']          = 'Important';
$_['text_warning_exact']      = 'Send the exact amount shown. Sending a different amount may delay processing.';
$_['text_warning_network']    = 'Make sure you are sending on the correct network. Sending on the wrong network may result in lost funds.';
$_['text_warning_address']    = 'Double-check the address before sending. Blockchain transactions cannot be reversed.';
$_['text_explorer_link']      = 'View address on blockchain explorer';
$_['text_confirm_button']     = 'Pay with USDT';
$_['text_loading']            = 'Preparing your payment...';
$_['text_redirect']           = 'Redirecting to order confirmation...';

// Transaction hash submission (static wallet)
$_['text_txid_label']         = 'Submit Transaction Hash';
$_['text_txid_placeholder']   = 'Paste your transaction hash here';
$_['text_txid_submit']        = 'Submit';
$_['text_txid_submitted']     = 'Transaction hash submitted. Verifying...';
$_['text_txid_submitting']    = 'Submitting...';
$_['text_txid_invalid_hex']   = 'Please enter a valid transaction hash (hexadecimal format).';
$_['text_partial_guidance']   = 'Send the remaining amount to the same address.';
$_['text_connection_issue']   = 'Connection issue, retrying...';
$_['text_copy_amount']        = 'Copy';
$_['text_copied_amount']      = 'Copied!';

// Errors
$_['error_order_not_found']   = 'Order not found. Please try again.';
$_['error_payment_failed']    = 'Could not create a payment monitoring request. Please try again or choose a different payment method.';
$_['error_txid_required']     = 'Transaction hash is required.';
$_['error_general']           = 'An unexpected error occurred. Please contact support.';
