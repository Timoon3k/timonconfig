# Administrative local checkout release — Core 0.9.9

The administrator-only **Close local checkout attempt** is for stale, reference-free checkout attempts that may have been created during a Stripe/PayPal API error. It does **not** claim that Stripe/PayPal canceled or never charged.

## What it can do

- Only WordPress administrators (`manage_options`) over HTTPS may submit it with an administrative nonce, two specific confirmations and a 15–300 character reason.
- Only applies to an attempt at least 10 minutes old, in the current Test/Live environment, with `pending` or `checkout` status and **no** provider checkout, customer, payment, or subscription identifier.
- Uses the same checkout and provider leases as normal payments, rechecks those conditions under database row locks, and refuses a closure if paid entitlement history exists.
- Sets the existing local attempt to `canceled` without deleting any record or changing VIP access. The administrator audit retains the reason and `remote_canceled=false`.
- Releases only that local pending checkout restriction; a new purchase may still be charged in addition to an old unresolved hosted checkout. **The administrator must check the payment provider dashboard and contact the buyer before encouraging another purchase**.
- Stripe and PayPal webhook verification and canonical reconciliation are unchanged. A later authentic payment event can still update the original order and grant/revoke an entitlement according to the provider state.

## What it cannot do

- It cannot cancel a Stripe Checkout Session or PayPal agreement; use provider-verified **Close unpaid checkout** for that.
- It refuses any order with a known provider ID (including only customer ID), under-ten-minute attempts, completed/active/refunded/disputed orders, and any paid-entitlement link.
- It cannot refund charges, erase an event, delete financial history, or remove a customer's VIP role.

## Administrator procedure

1. Review Stripe Live/Sandbox or PayPal Live/Sandbox transactions and agreements for the exact buyer, amount, approximate time and order. Check live versus test carefully.
2. For a confirmed paid transaction, reconcile or fulfill it instead of releasing the attempt.
3. If the provider cannot show a matching payment and local order has **no remote reference**, go to **WordPress → Settings → TimonConfigs billing → Customer orders**, find the order, and use the local-only control.
4. Enter the reason, confirm the dashboard review and acknowledge that any remote payment or approval may still arrive. Save.
5. Confirm the local order is canceled, record the decision in the customer's support case, and continue watching delayed webhook notifications before asking the buyer to pay again.

The release does not guarantee that a remote provider request did not succeed. If you cannot determine provider activity reliably, keep the checkout blocked and involve provider support.
