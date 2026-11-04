# PartnerAds - Magento 2 Affiliate tracking

This extension adds the following functionality to Magento:

- Track visitors from Partner Ads publishers via cookie
- Send order information to Partner Ads affiliate network depending on order status

## Requirements

- Magento 2.3.5 or later, or Magento 2.4
- PHP 7.1 or later (as far as your Magento version supports it)

Magento 2.3.0–2.3.4 are not supported: the export to Partner-ads uses the Laminas HTTP client, which Magento ships from 2.3.5.

## For Magento versions below 2.3:

For those running Magento versions earlier than 2.3, we recommend utilizing version 2.1 of this extension.
This version maintains the essential features tailored to be compatible with older Magento instances, ensuring you can still leverage the benefits of the Partner Ads affiliate network without the need for an immediate platform upgrade.

## Usage

### Installation via Composer (Recommended)

- Install the module with Composer by running `composer require partnerads/magento2`
- Enable the module by running `php bin/magento module:enable Partner_Module`
- Apply database updates by running `php bin/magento setup:upgrade`
- Compile Magento code base by running `php bin/magento setup:di:compile`
- Deploy static content by running `php bin/magento setup:static-content:deploy`
- Clean the cache by running `php bin/magento cache:clean`
- Setup Partner Program ID via Magento Admin panel
- You are ready to go

### Module settings in Magento Admin

![Partner Ads Magento module settings in Magento backend](https://intelligo.link/jc/2022-02-3MQ23gE6kI.png)

- Login to Magento admin
- Go to Stores - Configuration - Partner Ads Setup
- Enter Partner Program ID
- Change settings according to your stores setup

### Multi store setup

The Partner Ads module supports multi store setup per Magento’s normal requirements, therefore you are able to set individual Partner Ads program on a per store view basis via Magento admin.

### Export to Partner-ads

When an order with partner data reaches the order state set in *Commit by Order State* (default `processing`), the sale is reported to Partner-ads:

- *Export Mode* `Observer` (default): right when the order is saved, with a short timeout (connect 3 s, total 5 s). An order save never fails because of the export.
- *Export Mode* `CronJob`: by the Magento cron job `partner_export_orders_cron` (every minute, timeout 10 s). It picks up orders in the commit state that were created within the last 30 days.

If Partner-ads can't be reached or gives no definite answer (network error, timeout, HTTP 5xx or 429), the cron job tries again, in both export modes, so Magento cron must run:

- at most 5 attempts per order in total, about 5 minutes, 15 minutes, 1 hour and 6 hours apart;
- only for orders created within the last 30 days.

An export that is rejected (HTTP 4xx), that still fails after the last attempt, or whose order turns 30 days old first, is written to `var/partner/failed_export.csv` with the reason. Download it with the *Download Failed Exports* link under *Export Mode*. Partner-ads ignores a sale that is sent twice with the same program ID and order number, so a retry after a timeout is safe.

In observer mode, the cron job only retries exports that failed after the upgrade to 2.4.0. Older orders that were never sent are not picked up, so upgrading doesn't suddenly report old orders.

**Retries need Magento cron.** Without a running Magento cron (`bin/magento cron:run` from the system crontab, as Magento requires anyway), exports that are waiting for a retry are never sent.

### Export status in the admin

Stores › Configuration › Partner Ads Module › Partner Ads Setup shows two read-only rows at the top: the installed module version and the export status, for example:

> Exports waiting for retry: 3 · Failed: 1 · Last export cron run: 2026-11-04 09:58 (store time)

- *Exports waiting for retry*: exports that will be sent again by the cron job.
- *Failed*: exports that are not retried any more; they are in the *Download Failed Exports* file.
- *Last export cron run*: when the cron job `partner_export_orders_cron` last finished successfully. Magento keeps this for about one hour, so older runs show as "none in the last hour".

The counts are for the whole Magento installation (all store views). If exports are waiting and the cron job hasn't run successfully within the last hour, a warning says that cron does not seem to run. The row only reads from the database; it changes nothing and sends nothing.

### Themes and caching

The module adds one small script to every storefront page: `Partner_Module/js/tracker.js`, plain JavaScript without jQuery, RequireJS or Alpine. It works the same on Luma, Breeze and Hyvä themes, and no theme-specific copy is needed.

- The page HTML is the same with and without the tracking parameters, so pages can be cached in Varnish or the built-in full-page cache. The tracker reads `pa-partnerid` and `pacid` from the URL in the browser.
- Invalid values are ignored in the browser. With a valid partner ID, the tracker makes one request to `/papartner/ajax/cookie/`, which validates the values again and sets the `partner_id` and `pacid` cookies.
- Theme overrides: if your theme overrides `Partner_Module::script.phtml` or has its own copy of `Partner_Module/js/partner` (for example for Breeze), remove them after upgrading to 2.4.0. They keep working meanwhile, but only the module's own template is cache-safe and theme-independent. On Hyvä, the old `text/x-magento-init` template never ran.
- Magento's Content Security Policy needs nothing extra, also on the checkout pages: the tracker is a static file without inline code, and its request goes to the shop's own domain.

### Upgrading from 2.3.x

2.4.0 is one update from 2.3.x. Before upgrading:

- **Magento 2.3.5 or later** is required (see Requirements).
- **Headless / PWA shops:** the PartnerAds GraphQL query has been removed. Orders get partner data only from the `partner_id` and `pacid` cookies, which are set on the storefront domain when a visitor arrives through a partner link. Check that these cookies reach the request that places the order (same domain as the storefront) before you upgrade.
- **Custom code on the export:** `Model\Request`, the order observer (`Model\Observer\OnOrderStateChange`) and the cron class (`Cron\Export\Orders`) were rewritten, and their constructors and methods changed. The observer now listens to `sales_order_save_commit_after` (was `sales_order_save_after`), so the export runs after the order save has been committed. Plugins, preferences or observers of your own on these classes or events may need changes.

Upgrade:

```bash
composer update partnerads/magento2
bin/magento setup:upgrade                 # new table partner_module_export, wider pacid column
# production mode:
bin/magento setup:di:compile
bin/magento setup:static-content:deploy   # new file Partner_Module/js/tracker.js
bin/magento cache:flush                   # and purge Varnish / your CDN
```

After upgrading:

- **Magento cron** must run, so that failed exports are retried (see "Export to Partner-ads").
- **Themes:** if your theme overrides `Partner_Module::script.phtml`, or has its own copy of `Partner_Module/js/partner` (for example a Breeze component or a Hyvä version), remove the override or copy. The module's own template works on Luma, Breeze and Hyvä. An old override keeps working meanwhile, but its page still contains the tracking parameters, and a copy of the script may send a second request.
- **Consent or cookie tools** that look for the old `text/x-magento-init` block with `Partner_Module/js/partner` must look for `script[src*="Partner_Module/js/tracker"]` instead.
- **Mode `Development`** is now a dry run that sends nothing. Make sure a live shop is set to `Production`.

### Debugging

- Login to Magento admin
- Go to Stores - Configuration - Partner Ads Setup
- Set `Mode` to `Development`. This is a dry run: nothing is sent to Partner-ads. When an order reaches the commit state, the request that would have been sent is written to `var/log/partner.log`, and the order is marked so that it is never exported later.
- Perform an order placement using valid `pacid` and `pa-partnerid` values and move the order to the commit state
- Check `var/log/partner.log`

Set `Mode` back to `Production` afterwards. In `Production`, `var/log/partner.log` has one line per export and per failed attempt.

## Changelog

### 2.4.0

Requires Magento 2.3.5 or later. See "Upgrading from 2.3.x" before you upgrade.

#### Tracking

- One small tracker script, `Partner_Module/js/tracker.js`, for Luma, Breeze and Hyvä, without jQuery, RequireJS or Alpine; no theme copy is needed.
- The tracking parameters are no longer printed into the page, so pages are safe to cache in Varnish and the full-page cache. The tracker reads them from the URL in the browser.
- Invalid tracking parameters are ignored in the browser. If the cookie request fails because of a network error or a temporary server error, it is sent once more.
- Partner IDs of 1–10 digits without leading zeros, and click IDs (`pacid`) of up to 255 characters, including Google Ads click IDs, are accepted. The `partnerads_pacid_id` order column is widened to 255 characters.
- Orders only get partner data from well-formed tracking cookies.
- A new partner link without a click ID removes the click ID of an earlier link. A click ID without a valid partner ID is ignored.

#### Export

- An order save never fails because of the export to Partner-ads. In observer mode the export runs right after the order save is committed, with a 5-second timeout.
- Failed exports are retried by the Magento cron job in both export modes: at most 5 attempts, about 5 minutes, 15 minutes, 1 hour and 6 hours apart, for orders from the last 30 days. In observer mode only exports that failed after the upgrade are retried; older unsent orders are not picked up.
- The cron job only looks at orders in the commit state from the last 30 days, at most 50 per run.
- Rejected and given-up exports are written to the failed exports CSV again, with the reason. The admin download streams the file from `var/partner/` without writing another copy.
- `Mode` `Development` is now a dry run: nothing is sent, and the request is written to `var/log/partner.log`. Older versions sent real sales in both modes; existing configurations count as `Production`.
- `var/log/partner.log` is quieter: one line per export, failed attempt or cron run, and no lines for ordinary order saves.

#### Admin

- The installed module version and an export status line (exports waiting for retry, failed exports, last export cron run, and a warning when cron doesn't seem to run) are shown at the top of the Partner Ads Setup configuration.

#### Compatibility

- Requires Magento 2.3.5 or later (declared in `composer.json`); PHP 7.1 or later.
- `Model\Request`, the order observer and the export cron class were rewritten; the observer listens to `sales_order_save_commit_after`. Custom plugins or preferences on them may need changes.
- Theme overrides of `Partner_Module::script.phtml` and copies of `Partner_Module/js/partner` are deprecated; remove them after upgrading. They keep working meanwhile. The deprecated `Partner_Module/js/partner` reads the tracking parameters only from the URL and no longer needs jQuery.
- The README names the right log file, `var/log/partner.log`.
- New `SECURITY.md`: how to report a security problem privately.

#### Removed

- Removed the PartnerAds GraphQL query.

### 2.3.3

- Security: the partner tracking parameters are now validated and safely encoded before they are output on the page. All shops should upgrade.
- Security: hardened the PartnerAds GraphQL query.
- The tracking cookies are marked `Secure` on HTTPS, and downloading failed exports in the admin now requires the Partner Ads configuration permission.
- The module runs on PHP 7.1–7.3 again (2.3.2 required PHP 7.4 in practice).

## Support & Bugs

Please contact supportdk@partner-ads.com for support and bug requests.

Security problems: please report them privately as described in [SECURITY.md](SECURITY.md), not in a public issue.
