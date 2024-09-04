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
require_once 'model/callback.php';

/**
 * Monek Checkout Module Front Controller for handling the return from Monek Checkout
 *
 * @package monekcheckout
 */
class monekcheckoutReturnModuleFrontController extends ModuleFrontController
{
    /**
     * Process the payment callback
     * @see FrontController::postProcess()
     * 
     * @return void
     */
    public function postProcess() : void
    {
         PrestaShopLogger::addLog('Callback detected.', 1, null, 'monekcheckout', (int) $this->context->cart->id);

         $callback = new Callback();

        if (!$callback->payment_reference) {
            Tools::redirect('index.php');
            return;
        }

        if ($callback->response_code !== '00') {
            $note = "Payment declined: {$callback->message}";
            PrestaShopLogger::addLog($note, 2, $callback->response_code, 'monekcheckout', (int) $callback->payment_reference);
            $this->context->cookie->__set('payment_error_message', $note);
            Tools::redirect('index.php?controller=cart');
            return;
        }

        $cart = new Cart($callback->payment_reference);

        $order = OrderBuilder::create_order($cart, 'Callback', $this->context, $this->module);

        PrestaShopLogger::addLog('redirecting to confirmation page', 1, $callback->response_code, 'monekcheckout', (int) $order->id);

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
}
