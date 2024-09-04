<?php
/**
 * Copyright (c) 2024 Monek Ltd
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 *  @author    Monek Ltd
 *  @copyright 2024 Monek Ltd
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once 'helpers/cart_converter.php';
require_once 'helpers/countrycode_converter.php';
require_once 'helpers/curl_helper.php';

/**
 * Monek Checkout Module Front Controller for Payment Validation and Redirect to Checkout Page
 *
 * @package monekcheckout
 */
class monekcheckoutValidationModuleFrontController extends ModuleFrontController
{
    private const ELITE_URL = 'https://elite.monek.com/Secure/';
    private const STAGING_URL = 'https://staging.monek.com/Secure/';

    /**
     * Process payment validation and redirect to checkout page
     * @see FrontController::postProcess()
     * 
     * @return void
     */
    public function postProcess() : void
    {
        try {
            PrestaShopLogger::addLog('New Monek payment.', 1, null, 'monekcheckout', (int) $this->context->cart->id);

            $cart = $this->context->cart;
            $cartConverter = new CartConverter();

            $monekId = pSQL(Configuration::get('MONEKCHECKOUT_MONEK_ID'));
            $countryCode = pSQL(Configuration::get('MONEKCHECKOUT_COUNTRY'));
            $basketSummary = pSQL(Configuration::get('MONEKCHECKOUT_BASKET_SUMMARY'));
            $returnUrl = filter_var($this->context->link->getModuleLink($this->module->name, 'return', [], true), FILTER_SANITIZE_URL);
            $webhookUrl = filter_var($this->context->link->getModuleLink($this->module->name, 'webhook', [], true), FILTER_SANITIZE_URL);

            $bodyData = $cartConverter->prepare_payment_request_body_data(
                $this->context,
                $cart,
                $monekId,
                $this->getCountryCode3Digit($countryCode),
                $returnUrl,
                $webhookUrl,
                $basketSummary,
            );

            $this->sendPaymentRequest($bodyData);

        } catch (Exception $e) {
			PrestaShopLogger::addLog($e->getMessage(), 3, null, 'monekcheckout', (int) $this->context->cart->id);

		    $this->context->cookie->__set('payment_error_message', $e->getMessage());
            Tools::redirect('index.php?controller=cart');

            return;
        }
    }
    
    /**
     * Get 3 digit country code from 2 digit country code
     *
     * @param string $isoCode2Digit
     * @return string 
     */
    private function getCountryCode3Digit(string $isoCode2Digit) : string
    {
        $converter = new CountryCodeConverter();
        return $converter->getCountryCode3Digit($isoCode2Digit);
    }

    /**
     * Get iPay prepare URL
	 *
	 * @return string
	 */
    private function getIpayPrepareUrl() : string
    {
        $ipayPrepareExtension = 'iPayPrepare.ashx';
        return (Configuration::get('MONEKCHECKOUT_TEST_MODE') ? self::STAGING_URL : self::ELITE_URL) . $ipayPrepareExtension;
    }

    /**
     * Get iPay URL
     *
     * @return string
	 */
    private function getIpayUrl() : string
    {
        $ipayExtension = 'checkout.aspx';
        return (Configuration::get('MONEKCHECKOUT_TEST_MODE') ? self::STAGING_URL : self::ELITE_URL) . $ipayExtension;
    }
    
    /**
     * Send prepared payment request 
     *
     * @param array $bodyData
     * @throws \Exception
     * @return void
     */
    private function sendPaymentRequest($bodyData)
    {
        $preparedPaymentUrl = $this->getIpayPrepareUrl();

        PrestaShopLogger::addLog('Sending prepared payment request.', 1, null, 'monekcheckout', (int) $this->context->cart->id);

        $curlHelper = new CurlHelper();

        $response = $curlHelper->remote_post(
            $this->context,
            $preparedPaymentUrl,
            $bodyData,
            [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        );

        if ($response->success) {
            $redirectUrl = $this->getIpayUrl() . '?PreparedPayment=' . urlencode($response->body);

            PrestaShopLogger::addLog('Redirecting to checkout page.', 1, null, 'monekcheckout', (int) $this->context->cart->id);

            return Tools::redirect($redirectUrl);
        } else {
            throw new Exception('Failed to send payment request: ' . $response->body);
        }
    }
}
