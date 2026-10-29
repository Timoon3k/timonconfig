# Emergency hotfix Core 1.1.6: potential boot-time recursion from gettext

## Incident
After installing Core 1.1.5 (with Theme 0.14.2), the production site became inaccessible. The operator verified that **disabling TimonConfigs Core restored the site**, isolating the outage to the plugin or its interaction with installed components.

## Root-cause hypothesis
Core 1.1.5 registered global `gettext` and `gettext_with_context` filters during plugin initialization. Each translation invoked `vip_checkout_context()`, which queried WooCommerce cart state and stored config. Cart/option initialization itself triggers translations, creating a high risk of re-entrant translation callbacks, memory exhaustion, or a fatal recursion.

This is inferred from the code change and observed behavior; the exact production PHP fatal requires hosting logs.

## Fix
Core 1.1.6 registers checkout translation filters **lazily**, at the WordPress `wp` hook priority 25, only if the current request is the real VIP checkout and the WooCommerce cart is ready. Subsequent global translation callbacks read a single in-memory boolean rather than touching WooCommerce or options. Hooks register only once per request. The existing billing field, Discord ownership, Stripe/PayPal handling and role synchronization are unchanged.

## Rollback & installation
1. Keep Core 1.1.5 disabled until a backup is available.
2. The quickest proven recovery is the previous working Core 1.1.4, retaining Theme 0.14.2 if the site works with the plugin disabled.
3. Back up WordPress/DB. Upload Core 1.1.6 ZIP using Plugins -> Add New -> Upload Plugin, replacing the installed Core. Alternatively replace only the `timonconfigs-core` plugin directory through Hostinger File Manager while it is disabled.
4. Activate and verify frontend, /wp-admin, logged-out behavior, Discord OAuth, checkout, order ownership and WooCommerce Stripe checkout.
5. If the site becomes inaccessible, disable the directory again and capture the PHP error log. Do **not** run live payment tests until the site is stable and payments/roles are verified.

## Tests
- PHP syntax checks on all plugin PHP files.
- Standalone integration regression asserts gettext not registered during plugin init, activated at checkout, checkout billing fields and privacy notice, Stripe card/wallet selectors and no extra WooCommerce login/logout.
- Production activation and real Stripe/PayPal renewal **not tested** by CI.

The theme does not need to change.
