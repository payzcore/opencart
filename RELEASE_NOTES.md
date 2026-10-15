# PayzCore OpenCart extension v1.0.1

## Fixed
- **Pay again after expiry:** when an order's payment expired or was cancelled and the customer returned to checkout with the same order, the extension created the new payment with the same `external_order_id` (`oc-{order_id}`). PayzCore returns the existing payment for a repeated order id, so the customer got the expired payment back. A new attempt now uses `oc-{order_id}-r{8 hex}`.
- Webhook callback looks up the payment by `payment_id` first (exact match when an order has several attempts), then by `external_order_id`.

Marketplace: upload `payzcore-opencart.zip` (built by `packages/build-zips.sh`) to extension_id=48214.

Docs: https://docs.payzcore.com/integrations/opencart
