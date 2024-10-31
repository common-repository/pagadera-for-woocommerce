=== PAGADERA ===
Contributors: pagadera
Tags: token, payments, wallet, payment gateway, providers, debit, bank, woocommerce, caribbean, curacao, aruba, bonaire, sxm, st maarten.
Requires at least: 5.0
Tested up to: 6.4.1
Stable tag: 2.2.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The PAGADERA plugin is a payment gateway plugin for the Pagadera Payment Network which integrates into the WooCommerce plugin as a payment option.

== Description ==

PAGADERA is a platform which enables instant online payments.

Providers such as banks, credit unions and other financial institutions can join the PAGADERA Payment Network as providers to enable their customers to pay using their funds.

Follow the steps below to configure the Pagadera plugin:

1) Make sure WooCommerce is installed and enabled.
2) Search "Pagadera" in the plugin searchbar, install and activate the Pagadera plugin.
3) Go to "WooCommerce" > "Settings" > "Payments" and click to manage the "Pagadera Payment Gateway".
4) Open a new browser and go to https://www.pagadera.com, go to Member Portal and become a Pagadera Member.
5) Choose a business package at a provider in your country to create a wallet account.
6) Create an API for your new wallet account. Choose type "Redirect" and enter ReturnURL which can be found on your Wordpress Pagadera Payment Gateway configuration page.
7) Enter ProviderURL which can be found on Pagadera Member Portal API page.
8) Enter Key, Secret and Currency and enable Pagadera Payment Gateway.
9) Your shop is ready to accept Pagadera payments.

Note !! - This plugin is currently NOT block-based compatible. Follow the WooCommerce instructions to change the Checkout page to classic checkout.

== Frequently Asked Questions ==

= How much does it cost to accept payments with Pagadera =

Pagadera has multiple providers which each offer their own packages. Create a free membership and click on create wallet to view the packages at your preferred providers in your country. The Pagadera website provides generic pricing for packages for initial consideration.
Special packages are available for high-volume businesses. Contact Pagadera for additional details.

= Do I need a merchant account at my bank? =

No merchant or other special bank account is required. Payouts can be transferred to any local bank account in the same country as the provider.

== Changelog ==

= 2.2.0 =
* Changed logo to smaller logo.

= 2.1.0 =
* Extended logging.
* Increased payment verification security.
* Added timestamp to redirect url for added security.

= 2.0.2 =
* Added logging to WooCommerce log.
* Added debugging switch to log debug details to WooCommerce log.

= 2.0.1 =
* Changed Provider URL label to Provider API URL.
* Change description of Currency input.

= 2.0.0 =
* Applied additional guideline best practices to comply with and publish to Wordpress Directory.

= 1.0 =
* Initial version.