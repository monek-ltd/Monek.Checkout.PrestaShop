# Monek.Checkout.PrestaShop
### Monek Checkout Changelog

#### September 12 2024 - version 1.1.1
* Fixed - Removed the response method check for the webhook as it sometimes caused an issue with the order confirmation.
* Fixed - Fixed an issue where the order status was not updating correctly after a successful payment because it failed the integrity check.

#### September 10 2024 - version 1.1.0
* Added - Added a new feature to allow the user to enable GooglePay as a payment method

#### September 05 2024 - Version 1.0.5
* Fixed - Validating declined orders.

#### September 04 2024 - Version 1.0.4
* Updated - Refactored return controller code to improve separation.
* Added - Added php doc comments to improve readability.
* Added - Added more type specifications to functions and variables to improve maintainability and usability.
* Added - Changelog.md file to keep track of changes.

#### September 03 2024 - Version 1.0.3
* Added - Added user friendly error messages when payment is declined. 

#### August 13 2024 - Version 1.0.2
* Fixed - Improved SSL Verification for API requests.
* Updated - Renamed plugin based on prestashop compatability guidelines.

#### July 29 2024 - Version 1.0.1
* Updated - Minor updates based on PrestaShop guidelines, standards and conventions.

#### July 16 2024 - Version 1.0.0
** Initial Release **