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

require_once 'helpers/curl_helper.php';
require_once 'helpers/order_builder.php';
require_once 'model/webhook_payload.php';

/**
 * Monek Checkout Module Front Controller for handling the confirmation webhook from Monek Checkout
 *
 * @package monekcheckout
 */
class monekcheckoutWebhookModuleFrontController extends ModuleFrontController
{
    private const ELITE_URL = 'https://elite.monek.com/Secure/';
    private const STAGING_URL = 'https://staging.monek.com/Secure/';

    /**
     * Process the payment webhook
	 * @see FrontController::postProcess()
	 * 
	 * @return void
	 */
    public function postProcess() : void
    {
        $json_echo = file_get_contents('php://input');
        $transaction_webhook_payload_data = json_decode($json_echo, true);
        $payload = new WebhookPayload($transaction_webhook_payload_data);
        $this->process_transaction_webhook_payload($payload);
    }
    
    /**
     * Confirm the integrity of the transaction data through an HTTP POST request to the Monek API 
     *
     * @param int $cartId
     * @param WebhookPayload $payload
     * @return bool
     */
    private function confirm_integrity_digest(int $cartId, WebhookPayload $payload) : bool
    {
        try {
            PrestaShopLogger::addLog('Attempting to confirm integrity digest.', 1, null, 'monekcheckout', $cartId);

            $sql = 'SELECT `idempotency_token`, `integrity_secret` FROM `' . _DB_PREFIX_ . 'payment_tokens` WHERE `id_cart` = ' . $cartId;
            $result = Db::getInstance()->getRow($sql);

            $idempotency_token = $result['idempotency_token'];
            $integrity_secret = $result['integrity_secret'];

            if (!isset($integrity_secret) || $integrity_secret == '') {
                PrestaShopLogger::addLog('Failed to retrieve secret', 3, null, 'monekcheckout', $cartId);
                return false;
            }

            $integrity_check_url = $this->get_integrity_check_url();

            $body_data = [
                'IntegritySecret' => $integrity_secret,
                'IntegrityDigest' => $payload->integrity_digest,
                'RequestTime' => $payload->transaction_date_time,
                'IdempotencyToken' => $idempotency_token,
                'PaymentReference' => $payload->payment_reference,
                'CrossReference' => $payload->cross_reference,
                'ResponseCode' => $payload->response_code,
                'ResponseMessage' => $payload->message,
                'Amount' => $payload->amount,
                'CurrencyCode' => $payload->currency_code
            ];

            $headers = [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ];

            $curl_helper = new CurlHelper();
            $response = $curl_helper->remote_post($this->context, $integrity_check_url, $body_data, $headers);

            if ($response->success) {
                PrestaShopLogger::addLog('integrity confirmed.', 1, null, 'monekcheckout', $cartId);
                return true;
            } else {
                PrestaShopLogger::addLog('Failed to confirm integrity.', 3, null, 'monekcheckout', $cartId);
                return false;
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog(' Exception: ' . $e->getMessage(), 3, null, 'monekcheckout', $cartId);
            return false;
        }
    }

    /**
     * Create an order from the cart
	 *
	 * @param Cart $cart
	 * @return Order
	 */
    private function create_order(Cart $cart) : Order
    {
        if (!$cart->OrderExists()) {
            //Sleeping for 5 seconds incase webhook execution is running faster than the callback.
            sleep(5);
        }

        $order = OrderBuilder::create_order($cart, 'Webhook', $this->context, $this->module);

        return $order;
    }

    /**
     * Get the integrity check URL
     *
     * @return string
	 */
    private function get_integrity_check_url() : string
    {
        $integrity_check_extension = 'IntegrityCheck.ashx';
        return (Configuration::get('MONEKCHECKOUT_TEST_MODE') ? self::STAGING_URL : self::ELITE_URL) . $integrity_check_extension;
    }
    
    /**
     * process the post-transaction confirmation webhook payload from Monek after a payment has been processed 
     *
     * @param WebhookPayload  $payload
     * @return void
     */
    private function process_transaction_webhook_payload(WebhookPayload  $payload) : void
    {
        if (filter_input(INPUT_SERVER, 'REQUEST_METHOD', FILTER_SANITIZE_FULL_SPECIAL_CHARS) === 'POST') {

            if (!$payload->validate()) {
                PrestaShopLogger::addLog('Webhook failed validation', 2, null, 'monekcheckout', (int) $this->context->cart->id);
                header('HTTP/1.1 400 Bad Request');
                echo json_encode(['error' => 'Bad Request']);

                return;
            }
            if($payload->response_code == '00') {
                $cart = new Cart($payload->payment_reference);

                $response = $this->confirm_integrity_digest((int) $this->context->cart->id, $payload);
            
                $order = $this->create_order($cart);

                if (!$order->id) {
                    PrestaShopLogger::addLog('Something went wrong.', 3, null, 'monekcheckout', (int) $order->id);
                    header('HTTP/1.1 400 Bad Request');
                    echo json_encode(['error' => 'Bad Request']);
                    return;
                }

                if ($response) {
                    PrestaShopLogger::addLog('Payment confirmed - Updating order state.', 1, null, 'monekcheckout', (int) $order->id);
                    $history = new OrderHistory();
                    $history->id_order = (int) $order->id;
                    $history->changeIdOrderState(Configuration::get('PS_OS_PAYMENT'), (int) $order->id);
                    $history->add(true);

                    $order->setCurrentState(Configuration::get('PS_OS_PAYMENT'));
                } else {
                    PrestaShopLogger::addLog('Payment confirmation failed. Please contact support.', 4, null, 'monekcheckout', (int) $order->id);
                    header('HTTP/1.1 400 Bad Request');
                    echo json_encode(['error' => 'Bad Request']);
                }
            }
        } else {
            header('HTTP/1.1 405 Method Not Allowed');
            header('Allow: POST');
            echo json_encode(['error' => 'Method Not Allowed']);
        }
    }
}
