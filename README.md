
# PartnerAds - Magento 2.4+ Affiliate tracking

PartnerAds tracking extension for Magento 2. 
This extension adds the following functionality to Magento:

- Track visitors from Partner Ads publishers via cookie 
- Send order information to Partner Ads affiliate network depending on order status 
- Possibility for communication Magento backend via GraphQL endpoints


The plugin is tested and confirmed working on

-   Magento 2.4

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

## Support & Bugs 

Please contact supportdk@partner-ads.com for support and bug requests. 