# PartnerAds - Magento 2.3+ Affiliate tracking

This extension adds the following functionality to Magento:

- Track visitors from Partner Ads publishers via cookie
- Send order information to Partner Ads affiliate network depending on order status

## Compatibility

The Partner Ads affiliate tracking extension tested with:

- Magento 2.3
- Magento 2.4

## For Magento versions below 2.3:

For those running Magento versions earlier than 2.3, we recommend utilizing version 2.1 of this extension.
This version maintains the essential features tailored to be compatible with older Magento instances, ensuring you can still leverage the benefits of the Partner Ads affiliate network without the need for an immediate platform upgrade.

## Usage

### Installation via Composer (Recommended)

- Install the module composer by running `composer require partnerads/magento2`
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

### Themes and caching

The module adds one small script to every storefront page: `Partner_Module/js/tracker.js`, plain JavaScript without jQuery, RequireJS or Alpine. It works the same on Luma, Breeze and Hyvä themes, and no theme-specific copy is needed.

- The page HTML is the same with and without the tracking parameters, so pages can be cached in Varnish or the built-in full-page cache. The tracker reads `pa-partnerid` and `pacid` from the URL in the browser.
- Invalid values are ignored in the browser. With a valid partner ID, the tracker makes one request to `/papartner/ajax/cookie/`, which validates the values again and sets the `partner_id` and `pacid` cookies.
- Theme overrides: if your theme overrides `Partner_Module::script.phtml` or has its own copy of `Partner_Module/js/partner` (for example for Breeze), remove them after upgrading to 2.4.0. They keep working meanwhile, but only the module's own template is cache-safe and theme-independent. On Hyvä, the old `text/x-magento-init` template never ran.
- Magento's Content Security Policy needs nothing extra, also on the checkout pages: the tracker is a static file without inline code, and its request goes to the shop's own domain.

### Debugging

- Login to Magento admin
- Go to Stores - Configuration - Partner Ads Setup
- Set `Mode` to `Development`. This is a dry run: nothing is sent to Partner-ads. When an order reaches the commit state, the request that would have been sent is written to `var/log/partner.log`, and the order is marked so that it is never exported later.
- Perform an order placement using valid `pacid` and `pa-partnerid` values and move the order to the commit state
- Check `var/log/partner.log`

Set `Mode` back to `Production` afterwards. In `Production`, `var/log/partner.log` has one line per export and per failed attempt.

## Changelog

### 2.4.0 (unreleased)

- Removed the PartnerAds GraphQL query.
- Partner IDs of 1–10 digits and click IDs (`pacid`) of up to 255 characters are accepted, including Google Ads click IDs. The `partnerads_pacid_id` order column is widened to 255 characters.
- Orders only get partner data from well-formed tracking cookies.
- A new partner link without a click ID removes the click ID of an earlier link. A click ID without a valid partner ID is ignored.
- The installed module version is shown at the top of the Partner Ads Setup configuration.
- An order save never fails because of the export to Partner-ads. In observer mode the export runs right after the save with a 5-second timeout.
- Failed exports are retried automatically by the cron job, in both export modes: at most 5 attempts, about 5 minutes, 15 minutes, 1 hour and 6 hours apart, and only for orders from the last 30 days. In observer mode only exports that failed after the upgrade are retried; older unsent orders are not picked up.
- The cron job only looks at orders in the commit state from the last 30 days, at most 50 per run.
- Rejected and given-up exports are written to the failed exports CSV again, with the reason. The admin download streams the file straight from `var/partner/` without writing another copy.
- `Mode` `Development` is now a dry run: nothing is sent, and the request is written to `var/log/partner.log`. Older versions sent real sales in both modes; existing configurations count as `Production`.
- `var/log/partner.log` is quieter: one line per export, failed attempt or cron run, and no lines for ordinary order saves.
- Partner IDs must be a positive number without leading zeros (e.g. `0` and `0012345` are no longer accepted).
- One dependency-free tracker script for Luma, Breeze and Hyvä; no theme copy is needed.
- The tracking parameters are no longer printed into the page, so pages are safe to cache in Varnish and the full-page cache. Tracking reads the parameters in the browser.
- Invalid tracking parameters are ignored in the browser.
- Theme overrides of `Partner_Module::script.phtml` and copies of `Partner_Module/js/partner` are deprecated. Shops with such an override or copy should remove it after upgrading; it keeps working meanwhile.

### 2.3.3

- Security: the partner tracking parameters are now validated and safely encoded before they are output on the page. All shops should upgrade.
- Security: hardened the PartnerAds GraphQL query.
- The tracking cookies are marked `Secure` on HTTPS, and downloading failed exports in the admin now requires the Partner Ads configuration permission.
- The module runs on PHP 7.1–7.3 again (2.3.2 required PHP 7.4 in practice).

## Support & Bugs

Please contact supportdk@partner-ads.com for support and bug requests.
