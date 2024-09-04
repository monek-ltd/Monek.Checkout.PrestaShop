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

/**
 * Class Callback - represents the callback from the payment gateway 
 *
 * @package monek
 */
class Callback 
{
    public $payment_reference;
    public $response_code;
    public $message;

    public function __construct() {
        $this->payment_reference = filter_var(Tools::getValue('paymentreference'), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->response_code = filter_var(Tools::getValue('responsecode'), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->message = filter_var(Tools::getValue('message'), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }
}