# Billplz for Magento 2.4

Accept payment using Billplz for Magento 2.4.x

**Version: 1.0.0**

The extension is using [Billplz](https://www.billplz.com/) API v3.

#### Installation

1. Copy all files to `app/code/Billplz/BillplzPaymentGateway` folder directory.
1. Enable plugin.
    ```bash
    php bin/magento module:enable Billplz_BillplzPaymentGateway --clear-static-content
    ```
1. Run database upgrade.
    ```bash
    php bin/magento setup:upgrade
    ```
1. Run compilation process.
    ```bash
    php bin/magento setup:di:compile
    ```
1. Flush cache
    ```bash
    php bin/magento cache:flush
    ```
1. Configure it in `Stores > Configuration > Sales > Payment Methods > Billplz`.
1. Get Billplz API secret key, Collection ID, XSignature Key and update in the config.

#### Test Mode

This extension also include a testing mode where you can do a test run on Billplz sandbox environment. To use sandbox mode, set API Key from Billplz Sandbox. Create an account in [Billplz Sandbox](https://www.billplz-sandbox.com) environment. 

#### Other

Facebook: [Billplz Dev Jam](https://www.facebook.com/groups/billplzdevjam/)
