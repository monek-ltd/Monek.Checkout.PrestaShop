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
 * THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
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

/**
 * Class OrderBuilder - builds the order
 *
 * @package monek
 */
class OrderBuilder
{
    /**
     * Creates the Order
     *
     * @param Cart $cart
     * @param string $source
     * @param Context $context
     * @param Module $module
     * @return mixed
	 */
    public static function create_order(Cart $cart, string $source, Context $context, Module $module)
    {
        if ($cart->OrderExists()) {
            PrestaShopLogger::addLog("$source- Order found.", 1, null, 'monekcheckout', (int) $cart->id);
        } else {
            PrestaShopLogger::addLog("$source- Order does not exist, creating new order.", 1, null, 'monekcheckout', (int) $cart->id);

            $awaiting_confirmation_state_id = self::get_module_order_state();

            if (!$awaiting_confirmation_state_id) {
                PrestaShopLogger::addLog("$source- Could not find Awaiting Order Confirmation state.", 3, null, 'monekcheckout', (int) $cart->id);
                return;
            }

            $customer = new Customer($cart->id_customer);
            $currency = $context->currency;
            $total = (float) $cart->getOrderTotal(true, CartCore::BOTH);
            $module->validateOrder($cart->id, $awaiting_confirmation_state_id, $total, $module->displayName, null, [], (int) $currency->id, false, $customer->secure_key);

            PrestaShopLogger::addLog("$source- Order created.", 1, null, 'monekcheckout', (int) $cart->id);
        }

        PrestaShopLogger::addLog("$source- Fetching order.", 1, null, 'monekcheckout', (int) $cart->id);
        $order = Order::getByCartId($cart->id);

        if (!$order) {
            PrestaShopLogger::addLog("$source- Order NOT found.", 3, null, 'monekcheckout', (int) $cart->id);
        } else {
            PrestaShopLogger::addLog("$source- Order found.", 1, null, 'monekcheckout', (int) $cart->id);
        }

        return $order;
    }

    /**
    * Get the custom order state id for the module
    *
    * @return bool|string|null
    */
    private static function get_module_order_state()
    {
        $sql = 'SELECT `id_order_state` FROM `' . _DB_PREFIX_ . 'order_state` WHERE `module_name` = \'' . pSQL('monekcheckout') . '\'';
    
        $orderStateId = Db::getInstance()->getValue($sql);
    
        return $orderStateId;
    }
}
