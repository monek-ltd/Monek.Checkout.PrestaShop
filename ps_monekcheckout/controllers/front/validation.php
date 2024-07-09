<?php
require_once 'helpers/cart_converter.php';
require_once 'helpers/countrycode_converter.php';
require_once 'helpers/curl_helper.php';

class Ps_MonekCheckoutValidationModuleFrontController extends ModuleFrontController
{
    public const ELITE_URL = 'https://elite.monek.com/Secure/';
    public const STAGING_URL = 'https://staging.monek.com/Secure/';

    public function postProcess()
    {
        PrestaShopLogger::addLog('New Monek payment.', 1, null, 'ps_monekcheckout', (int)$this->context->cart->id);

        $cart = $this->context->cart;
        $cart_converter = new CartConverter(); 

        $body_data = $cart_converter->prepare_payment_request_body_data(
            $this->context,
            $cart, 
            Configuration::get('MONEKCHECKOUT_MONEK_ID'), 
            $this->getCountryCode3Digit(Configuration::get('MONEKCHECKOUT_COUNTRY')),
            $this->context->link->getModuleLink($this->module->name, 'return', [], true), 
            Configuration::get('MONEKCHECKOUT_BASKET_SUMMARY'));
        $this->send_payment_request($body_data);
    }

       private function getCountryCode3Digit($iso_code_2digit)
    {
        $converter = new CountryCodeConverter();
        return $converter->getCountryCode3Digit($iso_code_2digit);
    }

    private function get_ipay_prepare_url()
    {
        $ipay_prepare_extension = 'iPayPrepare.ashx';
        return (Configuration::get('MONEKCHECKOUT_TEST_MODE') ? self::STAGING_URL : self::ELITE_URL) . $ipay_prepare_extension;
    }

    private function get_ipay_url()
    {
        $ipay_extension = 'checkout.aspx';
        return (Configuration::get('MONEKCHECKOUT_TEST_MODE') ? self::STAGING_URL : self::ELITE_URL) . $ipay_extension;
    }

    private function send_payment_request($body_data)
    {
        $prepared_payment_url = $this->get_ipay_prepare_url();

        PrestaShopLogger::addLog('Sending prepared payment request.', 1, null, 'ps_monekcheckout', (int)$this->context->cart->id);

        $curl_helper = new CurlHelper();

        $response = $curl_helper->remote_post($this->context, $prepared_payment_url, $body_data, array(
            'Content-Type' => 'application/x-www-form-urlencoded'
        ));

        if ($response->success) {            
            $redirect_url = $this->get_ipay_url() . '?PreparedPayment=' . urlencode($response->body);
            PrestaShopLogger::addLog('Redirecting to checkout page.', 1, null, 'ps_monekcheckout', (int)$this->context->cart->id);
            return Tools::redirect($redirect_url);
        } else {
            die('Prepared payment request failed. Please contact support.');
        }
    }
}

