# PartnerAds - Magento 2.3+ Affiliate tracking

This extension adds the following functionality to Magento:

- Track visitors from Partner Ads publishers via cookie
- Send order information to Partner Ads affiliate network depending on order status
- Possibility for communication Magento backend via GraphQL endpoints

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

### Debugging

Starting from version 2.1.0, we have made significant enhancements to the debug log functionality. To effectively debug Partner Ads tracking and communication with Partner Ads servers, please follow these steps:

- Login to Magento admin
- Go to Stores - Configuration - Partner Ads Setup
- Adjust the `Mode` setting to `Debug`
- Perform an order placement using valid `PACID` and `PARTNERID` values
- Monitor the request and response log in the `var/log/Partnerads.log` file

Please remember to set `Mode` back to `Production` afterwards.

## Support & Bugs

Please contact supportdk@partner-ads.com for support and bug requests.
