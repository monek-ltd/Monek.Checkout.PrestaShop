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

class monekcheckoutReturnModuleFrontController extends ModuleFrontController
{
    public const ELITE_URL = 'https://elite.monek.com/Secure/';
    public const STAGING_URL = 'https://staging.monek.com/Secure/';

    public function postProcess()
    {
        $json_echo = Tools::file_get_contents('php://input');
        $transaction_webhook_payload_data = json_decode($json_echo, true);

        if (isset($transaction_webhook_payload_data)) {
            PrestaShopLogger::addLog('Webhook detected, pausing execution.', 1, null, 'monekcheckout', (int) $this->context->cart->id);
            sleep(5);
            PrestaShopLogger::addLog('Resuming webhoook execution.', 1, null, 'monekcheckout', (int) $this->context->cart->id);
            $this->process_transaction_webhook_payload($transaction_webhook_payload_data);
        } else {
            PrestaShopLogger::addLog('Callback detected.', 1, null, 'monekcheckout', (int) $this->context->cart->id);
            $this->process_payment_callback();
        }
    }

    private function confirm_integrity_digest($order, $transaction_webhook_payload_data)
    {
        try {
            PrestaShopLogger::addLog('Attempting to confirm integrity digest.', 1, null, 'monekcheckout', (int) $order->id);

            $sql = 'SELECT `idempotency_token`, `integrity_secret` FROM `' . _DB_PREFIX_ . 'payment_tokens` WHERE `id_cart` = ' . (int) $order->id_cart;
            $result = Db::getInstance()->getRow($sql);

            $idempotency_token = $result['idempotency_token'];
            $integrity_secret = $result['integrity_secret'];

            if (!isset($integrity_secret) || $integrity_secret == '') {
                PrestaShopLogger::addLog('Failed to retrieve secret', 3, null, 'monekcheckout', (int) $order->id);
                header('HTTP/1.1 500 Internal Server Error');
                echo json_encode(['error' => 'Internal Server Error']);
                return;
            }

            $integrity_check_url = $this->get_integrity_check_url();

            $body_data = [
                'IntegritySecret' => $integrity_secret,
                'IntegrityDigest' => $transaction_webhook_payload_data['integrityDigest'],
                'RequestTime' => $transaction_webhook_payload_data['transactionDateTime'],
                'IdempotencyToken' => $idempotency_token,
                'PaymentReference' => $transaction_webhook_payload_data['paymentReference'],
                'CrossReference' => $transaction_webhook_payload_data['crossReference'],
                'ResponseCode' => $transaction_webhook_payload_data['responseCode'],
                'ResponseMessage' => $transaction_webhook_payload_data['message'],
                'Amount' => $transaction_webhook_payload_data['amount'],
                'CurrencyCode' => $transaction_webhook_payload_data['currencyCode'],
            ];

            $headers = [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ];

            $curl_helper = new CurlHelper();
            $response = $curl_helper->remote_post($this->context, $integrity_check_url, $body_data, $headers);

            if ($response->success) {
                PrestaShopLogger::addLog('integrity confirmed.', 1, null, 'monekcheckout', (int) $order->id);
                return true;
            } else {
                PrestaShopLogger::addLog('Failed to confirm integrity.', 3, null, 'monekcheckout', (int) $order->id);
                return false;
            }
        } catch (Exception $e) {
            PrestaShopLogger::addLog(' Exception: ' . $e->getMessage(), 3, null, 'monekcheckout', (int) $order->id);
            return false;
        }
    }

    private function create_order($cart, $source)
    {
        if ($cart->OrderExists()) {
            PrestaShopLogger::addLog($source . '- Order found.', 1, null, 'monekcheckout', (int) $cart->id);
        } else {
            PrestaShopLogger::addLog($source . '- Order does not exist, creating new order.', 1, null, 'monekcheckout', (int) $cart->id);

            $awaiting_confirmation_state_id = $this->get_module_order_state();

            if (!$awaiting_confirmation_state_id) {
                PrestaShopLogger::addLog($source . '- Could not find Awaiting Order Confirmation state.', 3, null, 'monekcheckout', (int) $cart->id);
                return;
            }

            $customer = new Customer($cart->id_customer);
            $currency = $this->context->currency;
            $total = (float) $cart->getOrderTotal(true, Cart::BOTH);
            $this->module->validateOrder($cart->id, $awaiting_confirmation_state_id, $total, $this->module->displayName, null, [], (int) $currency->id, false, $customer->secure_key);

            PrestaShopLogger::addLog($source . '- Order created.', 1, null, 'monekcheckout', (int) $cart->id);
        }

        PrestaShopLogger::addLog($source . '- Fetching order.', 1, null, 'monekcheckout', (int) $cart->id);
        $order = Order::getByCartId($cart->id);

        if (!$order) {
            PrestaShopLogger::addLog($source . '- Order NOT found.', 3, null, 'monekcheckout', (int) $cart->id);
        } else {
            PrestaShopLogger::addLog($source . '- Order found.', 1, null, 'monekcheckout', (int) $cart->id);
        }

        return $order;
    }

    private function get_integrity_check_url()
    {
        $integrity_check_extension = 'IntegrityCheck.ashx';
        return (Configuration::get('MONEKCHECKOUT_TEST_MODE') ? self::STAGING_URL : self::ELITE_URL) . $integrity_check_extension;
    }

    private function get_module_order_state()
    {
        $sql = 'SELECT `id_order_state` FROM `' . _DB_PREFIX_ . 'order_state` WHERE `module_name` = \'' . pSQL('monekcheckout') . '\'';

        $orderStateId = Db::getInstance()->getValue($sql);

        return $orderStateId;
    }

    private function process_payment_callback()
    {
        $responseCode = Tools::getValue('responsecode');
        $id_cart = Tools::getValue('paymentreference');

        if (!$id_cart) {
            Tools::redirect('index.php');
            return;
        }

        if ($responseCode !== '00') {
            $note = 'Payment declined: ' . Tools::getValue('message');
            PrestaShopLogger::addLog($note, 2, $responseCode, 'monekcheckout', (int) $id_cart);
            Tools::redirect('index.php?controller=order&step=1');
            return;
        }

        $cart = new Cart($id_cart);

        $order = $this->create_order($cart, 'Callback');

        PrestaShopLogger::addLog('redirecting to confirmation page', 1, $responseCode, 'monekcheckout', (int) $order->id);

        Tools::redirect(
            $this->context->link->getPageLink(
                'order-confirmation',
                true,
                (int) $this->context->language->id,
                [
                    'id_cart' => (int) $order->id_cart,
                    'id_module' => (int) $this->module->id,
                    'id_order' => (int) $order->id,
                    'key' => $order->secure_key,
                ]
            )
        );
    }

    private function process_transaction_webhook_payload($transaction_webhook_payload_data)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->validate_webhook_payload($transaction_webhook_payload_data)) {
                PrestaShopLogger::addLog('Webhook failed validation', 2, null, 'monekcheckout', (int) $this->context->cart->id);
                header('HTTP/1.1 400 Bad Request');
                echo json_encode(['error' => 'Bad Request']);

                return;
            }

            $cart = new Cart($transaction_webhook_payload_data['paymentReference']);

            $order = $this->create_order($cart, 'Webhook');

            if (!$order->id) {
                PrestaShopLogger::addLog('Something went wrong.', 3, null, 'monekcheckout', (int) $order->id);
                header('HTTP/1.1 400 Bad Request');
                echo json_encode(['error' => 'Bad Request']);
                return;
            }

            $response = $this->confirm_integrity_digest($order, $transaction_webhook_payload_data);

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
        } else {
            header('HTTP/1.1 405 Method Not Allowed');
            header('Allow: POST');
            echo json_encode(['error' => 'Method Not Allowed']);
        }
    }

    private function validate_webhook_payload($transaction_webhook_payload_data)
    {
        return isset($transaction_webhook_payload_data['transactionDateTime'])
            && isset($transaction_webhook_payload_data['paymentReference'])
            && isset($transaction_webhook_payload_data['crossReference'])
            && isset($transaction_webhook_payload_data['responseCode'])
            && isset($transaction_webhook_payload_data['message'])
            && isset($transaction_webhook_payload_data['amount'])
            && isset($transaction_webhook_payload_data['currencyCode'])
            && isset($transaction_webhook_payload_data['integrityDigest']);
    }
}
