<?php
require_once 'helpers/curl_helper.php';

class Ps_MonekCheckoutReturnModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $json_echo = file_get_contents('php://input');
        $transaction_webhook_payload_data = json_decode($json_echo, true);

        if (isset($transaction_webhook_payload_data)) {
            $this->process_transaction_webhook_payload($transaction_webhook_payload_data);
        } else {
            $this->process_payment_callback();
        }
    }
    private function confirm_integrity_digest($order, $transaction_webhook_payload_data)
    {
        $sql = "SELECT `idempotency_token`, `integrity_secret` FROM `" . _DB_PREFIX_ . "payment_tokens` WHERE `id_cart` = " . (int)$order->id;
        $result = Db::getInstance()->getRow($sql);

        $idempotency_token = $result['idempotency_token'];
        $integrity_secret = $result['integrity_secret'];

        if (!isset($integrity_secret) || $integrity_secret == '') {
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(array('error' => 'Internal Server Error'));
            return;
        }

        $integrity_check_url = $this->get_integrity_check_url();

        $body_data = array(
            'IntegritySecret' => $integrity_secret,
            'IntegrityDigest' => $transaction_webhook_payload_data['integrityDigest'],
            'RequestTime' => $transaction_webhook_payload_data['transactionDateTime'],
            'IdempotencyToken' => $idempotency_token,
            'PaymentReference' => $transaction_webhook_payload_data['paymentReference'],
            'CrossReference' => $transaction_webhook_payload_data['crossReference'],
            'ResponseCode' => $transaction_webhook_payload_data['responseCode'],
            'ResponseMessage' => $transaction_webhook_payload_data['message'],
            'Amount' => $transaction_webhook_payload_data['amount'],
            'CurrencyCode' => $transaction_webhook_payload_data['currencyCode']
        );

        $headers = array(
            'Content-Type' => 'application/x-www-form-urlencoded'
        );

        $curl_helper = new CurlHelper();
        $response = $curl_helper->remote_post($this->context, $integrity_check_url, $body_data, $headers);

        if($response->success){
            return $response->body;
        }
        else {
            PrestaShopLogger::addLog("Failed to confirm integrity.", 1, null, 'ps_monekcheckout', (int)$this->context->cart->id);
            return false;
        }
    }

    private function create_order($cart){
        $awaiting_confirmation_state_id = $this->get_module_order_state();

        if (!$awaiting_confirmation_state_id) {
            PrestaShopLogger::addLog("Could not find Awaiting Order Confirmation state.", 1, null, 'ps_monekcheckout', (int)$this->context->cart->id);
            return;
        }

        $customer = new Customer($cart->id_customer);
        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
        $this->module->validateOrder($cart->id, $awaiting_confirmation_state_id, $total, $this->module->displayName, null, [], (int)$currency->id, false, $customer->secure_key);

    }

    private function get_integrity_check_url(){
        $integrity_check_extension = 'IntegrityCheck.ashx';
        return (Configuration::get('MONEKCHECKOUT_TEST_MODE') ? TransactDirectGateway::$staging_url : TransactDirectGateway::$elite_url) . $integrity_check_extension;
    }

    private function get_module_order_state()
    {
        $sql = 'SELECT `id_order_state` FROM `' . _DB_PREFIX_ . 'order_state` WHERE `module_name` = \'' . pSQL("ps_monekcheckout") . '\'';

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
            PrestaShopLogger::addLog($note, 1, $responseCode, 'ps_monekcheckout', (int)$this->context->cart->id);
            Tools::redirect('index.php?controller=order&step=1');
            return;
        }

        $cart = $this->context->cart;

        $this->create_order($cart);

        $order = Order::getByCartId($cart->id);

        Tools::redirect($this->context->link->getPageLink('order-confirmation',
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
                header('HTTP/1.1 400 Bad Request');
                echo json_encode(array('error' => 'Bad Request'));
                return;
            }

            $cart = new Cart($transaction_webhook_payload_data['paymentReference']);

            if (!$cart->OrderExists()) {
                $this->create_order($cart);
            }

            $order = Order::getByCartId($cart->id);
            
            if (!$order->id) {
                header('HTTP/1.1 400 Bad Request');
                echo json_encode(array('error' => 'Bad Request'));
                return;
            }
            
            $response = $this->confirm_integrity_digest($order, $transaction_webhook_payload_data);

            if ($response['responseCode'] != '00') {
                header('HTTP/1.1 400 Bad Request');
                echo json_encode(array('error' => 'Bad Request'));
            } else {
                $history = new OrderHistory();
                $history->id_order = (int)$order->id;
                $history->changeIdOrderState(Configuration::get('PS_OS_PAYMENT'), (int)$order->id);
                $history->add(true);

                $order->setCurrentState(Configuration::get('PS_OS_PAYMENT'));
            }

        } else {
            header('HTTP/1.1 405 Method Not Allowed');
            header('Allow: POST');
            echo json_encode(array('error' => 'Method Not Allowed'));
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
