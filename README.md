# PayzCore for OpenCart 3.x

Accept stablecoin payments (USDT, USDC) in your OpenCart store via PayzCore blockchain transaction monitoring.

PayzCore is a **non-custodial** monitoring service. It watches blockchain addresses for incoming stablecoin transfers on multiple networks and sends webhook notifications when transactions are detected. PayzCore does not hold, transmit, or custody any funds.

## Important

**PayzCore is a blockchain monitoring service, not a payment processor.** All payments are sent directly to your own wallet addresses. PayzCore never holds, transfers, or has access to your funds.

- **Your wallets, your funds** — You provide your own wallet (HD xPub or static addresses). Customers pay directly to your addresses.
- **Read-only monitoring** — PayzCore watches the blockchain for incoming transactions and sends webhook notifications. That's it.
- **Protection Key security** — Sensitive operations like wallet management, address changes, and API key regeneration require a Protection Key that only you set. PayzCore cannot perform these actions without your authorization.
- **Your responsibility** — You are responsible for securing your own wallets and private keys. PayzCore provides monitoring and notification only.

## Requirements

- OpenCart 3.0.0 or later
- PHP 7.4 or later
- cURL extension enabled
- A PayzCore account ([payzcore.com](https://payzcore.com))

## Installation

### Method 1: Extension Installer (Recommended)

1. Download the latest release `.zip` file
2. Go to **Admin > Extensions > Installer**
3. Upload the `.zip` file
4. Go to **Admin > Extensions > Extensions > Payments**
5. Find **PayzCore - Stablecoin Monitoring** and click **Install**
6. Click **Edit** to configure

### Method 2: Manual Upload

1. Copy the contents of the `upload/` directory to your OpenCart root directory
2. Go to **Admin > Extensions > Extensions > Payments**
3. Find **PayzCore - Stablecoin Monitoring** and click **Install**
4. Click **Edit** to configure

## Configuration

1. **API Key** - Your PayzCore project API key (starts with `pk_live_`)
2. **Webhook Secret** - Your webhook secret (starts with `whsec_`)
3. **API URL** - Default: `https://api.payzcore.com`
4. **Network** - Choose TRC20 (Tron), BEP20 (BSC), ERC20 (Ethereum), POLYGON (Polygon), or ARBITRUM (Arbitrum)
5. **Token** - Choose USDT (default) or USDC
6. **Static Wallet Address** - (Optional) A fixed blockchain address for all payments. When set, customers submit their transaction hash manually. Leave empty to use PayzCore HD wallet derivation.
7. **Payment Expiry** - Time before payment request expires (default: 3600 seconds)
8. **Order Status** - Map pending, completed, and expired statuses
9. **Status** - Enable/Disable the payment method

### Webhook Setup

After configuring the extension, copy the **Webhook URL** shown in the settings page and add it to your PayzCore project settings:

1. Go to your PayzCore dashboard
2. Navigate to **Projects > Your Project > Edit**
3. Paste the webhook URL in the **Webhook URL** field
4. Save

The webhook URL format is:
```
https://your-store.com/index.php?route=extension/payment/payzcore/callback
```

## How It Works

1. Customer selects "Pay with USDT" at checkout
2. The extension creates a monitoring request via the PayzCore API
3. Customer sees payment instructions (address, amount, QR code, countdown)
4. PayzCore monitors the blockchain for incoming transfers
5. When a transaction is detected, PayzCore sends a webhook notification
6. The extension updates the order status automatically

### Static Wallet Mode

When a static wallet address is configured, the extension sends it to the PayzCore API with each payment request. The API may return additional fields:

- **notice** - A message displayed to the customer (e.g., exact amount instructions)
- **requires_txid** - When true, the customer sees a form to submit their transaction hash
- **confirm_endpoint** - The API endpoint where the transaction hash is submitted

This is useful for merchants who prefer to use a single receiving address rather than HD-derived addresses.

## Supported Networks

- **TRC20** (Tron) - Lower transaction fees
- **BEP20** (BSC) - Binance Smart Chain
- **ERC20** (Ethereum) - Ethereum mainnet
- **POLYGON** (Polygon) - Low-cost L2
- **ARBITRUM** (Arbitrum) - Ethereum L2

## Supported Tokens

- **USDT** (Tether) - Most widely used stablecoin
- **USDC** (USD Coin) - Circle-backed stablecoin

## See Also

- [Getting Started](https://docs.payzcore.com/getting-started) — Account setup and first payment
- [Webhooks Guide](https://docs.payzcore.com/guides/webhooks) — Events, headers, and signature verification
- [Supported Networks](https://docs.payzcore.com/guides/networks) — Available networks and tokens
- [Error Reference](https://docs.payzcore.com/guides/errors) — HTTP status codes and troubleshooting
- [API Reference](https://docs.payzcore.com) — Interactive API documentation

## Support

- Documentation: [docs.payzcore.com](https://docs.payzcore.com)
- Website: [payzcore.com](https://payzcore.com)

## Before Going Live

**Always test your setup before accepting real payments:**

1. **Verify your wallet** — In the PayzCore dashboard, verify that your wallet addresses are correct. For HD wallets, click "Verify Key" and compare address #0 with your wallet app.
2. **Run a test order** — Place a test order for a small amount ($1–5) and complete the payment. Verify the funds arrive in your wallet.
3. **Test sweeping** — Send the test funds back out to confirm you control the addresses with your private keys.

> **Warning:** Wrong wallet configuration means payments go to addresses you don't control. Funds sent to incorrect addresses are permanently lost. PayzCore is watch-only and cannot recover funds. Please test before going live.

## License

MIT License - PayzCore 2026
