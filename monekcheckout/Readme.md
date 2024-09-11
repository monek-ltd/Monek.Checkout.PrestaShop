# Monek.Checkout.PrestaShop
### Monek PrestaShop Module

Enhance your online store with the Monek PrestaShop Module, a powerful and seamless integration for secure payment processing. Accept payments effortlessly, streamline transactions, and provide a smooth checkout experience for your customers.

Contents:
- Manual Installation Guide
- Known Issues



## Manual Installation Guide for Monek Checkout in PrestaShop

### Introduction
This guide provides step-by-step instructions for manually installing the Monek Checkout for PrestaShop. Ensure that you have the necessary permissions and backups before proceeding.

### Prerequisites
- Prestashop installed and activated.
- Access to your web server's back office.

### Step 1: Download the Monek Checkout Module from GitHub
Visit the [GitHub repository](https://github.com/monek-ltd/Monek.Checkout.PrestaShop/) where the Monek Checkout Module is hosted and download the [latest version](https://github.com/monek-ltd/Monek.Checkout.PrestaShop/releases/latest) of the module files. Github should allow you to download the repository as a ZIP archive.


### Step 2: Extract Plugin Files
After downloading the [latest version](https://github.com/monek-ltd/Monek.Checkout.PrestaShop/releases/latest) Monek Checkout Module from the [GitHub repository](https://github.com/monek-ltd/Monek.Checkout.PrestaShop/), extract the contents of the ZIP file to your local machine. This will reveal the module files and folders. (Look for the "monekcheckout" folder)


### Step 3: Connect to Your PrestaShop Site
Log in to your back office account and locate the module manager under the back office menu.


### Step 4: Upload the Monek Checkout Module
Locate the 'Upload a module' button in the top right hand corner of the screen, press this and the drag the monekcheckout folder from your download into the dialog box that appears. 

(You may need to Zip this folder in order to upload it to your site).

### Step 5: Configure the module
After installation, click configure.

Fill in the required details:
- Monek ID: Enter the Monek ID provided to you.
  
Save changes to apply the configuration.

If you don't have the necessary information, such as your Monek ID, visit [Monek Contact Page](https://monek.com/contact) to get help. Ensure that all information entered is accurate to enable seamless payment processing on your WooCommerce store.


## Configuration

### GooglePay: 

Indicates if the Google Pay™ button will appear on the checkout page. `YES` or `NO` (default)

All merchants must adhere to the Google Pay APIs [Acceptable Use Policy](https://payments.developers.google.com/terms/aup) and accept the terms defined in the [Google Pay API Terms of Service](https://payments.developers.google.com/terms/sellertos). 

Google Pay is a trademark of Google LLC.


## Known Issues:

### Failed Payment Redirect
Having 'Increase front office security' enabled prevents a redirect to the cart/basket summary page.

- When a customer payment fails for any reason they are redirected to the homepage rather than redirected to the cart/basket.
- The error message is still seen from the cart/basket as the user clicks back into the basket.
- This can be fixed by disabling 'Increase front office security' from the General Settins tab, this disables token in the front office.

Tested on PrestaShop versions: 8.1.7, 8.1.6, 8.1.2