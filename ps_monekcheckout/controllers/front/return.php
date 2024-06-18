<?php
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

    private function process_payment_callback()
    {
        $responseCode = Tools::getValue('responsecode');
        $id_order = Tools::getValue('paymentreference');
        $order = new Order($id_order);

        if (!$order->id) {
            Tools::redirect('index.php?controller=order&step=1');
            return;
        }

        if ($responseCode !== '00') {
            $note = 'Payment declined: ' . Tools::getValue('message');
            $order->setCurrentState(Configuration::get('PS_OS_ERROR'));
            $order->addOrderPayment(new OrderPayment($note));
            Tools::redirect('index.php?controller=order&step=1');
            return;
        }

        $order->setCurrentState(Configuration::get('PS_OS_PAYMENT'));
        Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $order->id_cart . '&id_module=' . $this->module->id . '&id_order=' . $order->id . '&key=' . $order->secure_key);
    }

    private function process_transaction_webhook_payload($transaction_webhook_payload_data)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->validate_webhook_payload($transaction_webhook_payload_data)) {
                header('HTTP/1.1 400 Bad Request');
                echo json_encode(array('error' => 'Bad Request'));
                return;
            }

            $order = new Order($transaction_webhook_payload_data['paymentReference']);
            if (!$order->id) {
                header('HTTP/1.1 400 Bad Request');
                echo json_encode(array('error' => 'Bad Request'));
                return;
            }

            if ($transaction_webhook_payload_data['responseCode'] == '00') {
                $order->addOrderPayment(new OrderPayment('Payment confirmed.'));
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
