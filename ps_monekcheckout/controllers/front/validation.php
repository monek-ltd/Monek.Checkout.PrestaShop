<?php

class Ps_MonekCheckoutValidationModuleFrontController extends ModuleFrontController
{
    private const PARTIAL_ORIGIN_ID = '7d07f975-b3c6-46b8-8f3a-';
    public const ELITE_URL = 'https://elite.monek.com/Secure/';
    public const STAGING_URL = 'https://staging.monek.com/Secure/';

    public function postProcess()
    {
        $cart = $this->context->cart;
        $customer = new Customer($cart->id_customer);
        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
        
        $this->module->validateOrder($cart->id, Configuration::get('PS_OS_PAYMENT'), $total, $this->module->displayName, null, [], (int)$currency->id, false, $customer->secure_key);

        $body_data = $this->prepare_payment_request_body_data(
            new Order($this->module->currentOrder), 
            Configuration::get('MONEKCHECKOUT_MONEK_ID'), 
            $this->getCountryCode3Digit(Configuration::get('MONEKCHECKOUT_COUNTRY')),
            $this->context->link->getModuleLink($this->module->name, 'return', [], true), 
            Configuration::get('MONEKCHECKOUT_BASKET_SUMMARY'));
        $this->send_payment_request($body_data);
    }

    private function getCountryCode3Digit($iso_code_2digit)
    {
        $countries = Country::getCountries($this->context->language->id);
        foreach ($countries as $country) {
            if ($country['iso_code'] == $iso_code_2digit) {
                return $country['id_country'];
            }
        }
        return null; 
    }

    private function prepare_payment_request_body_data($order, $merchant_id, $country_code, $return_plugin_url, $purchase_description)
    {
        $billing_amount = $order->getTotalPaid();
        
        $idempotency_token = uniqid($order->id, true);
        $integrity_secret = uniqid($order->id, true);
        
        $body_data = array(
            'MerchantID' => $merchant_id,
            'MessageType' => 'ESALE_KEYED',
            'Amount' => $this->convert_decimal_to_flat($billing_amount),
            'CurrencyCode' => $this->get_iso4217_currency_code(),
            'CountryCode' => $country_code,
            'Dispatch' => 'NOW',
            'ResponseAction' => 'REDIRECT',
            'RedirectUrl' => $return_plugin_url, 
            'WebhookUrl' => $return_plugin_url,
            'PaymentReference' => $order->id,
            'ThreeDSAction' => 'ACSDIRECT',
            'IdempotencyToken' => $idempotency_token,
            'OriginID' => self::PARTIAL_ORIGIN_ID . str_replace('.', '', '1.0.0') . str_repeat('0', 14 - strlen('1.0.0')), //TODO Replace '1.0.0' with plugin version
            'PurchaseDescription' => $purchase_description,
            'IntegritySecret' => $integrity_secret,
            'Basket' => $this->generate_basket_base64($order),
            'ShowDeliveryAddress' => 'YES'
        );

        $body_data = $this->generate_cardholder_detail_information($body_data);

        return $body_data;
    }

    private function send_payment_request($body_data)
    {
        $prepared_payment_url = $this->get_ipay_prepare_url();

        //TODO: (for testing) REMOVE
        //Tools::redirect('https://staging.monek.com/Secure/checkout.aspx'. '?' . http_build_query($body_data));

        $ch = curl_init($prepared_payment_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($body_data));

        //TODO ??
        if(Configuration::get('MONEKCHECKOUT_TEST_MODE')){
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code == 200) {
            Tools::redirect($this->get_ipay_url().'?PreparedPayment=' . urlencode($response));
        } else {
            PrestaShopLogger::addLog(
                'Payment request failed. cURL error (' . curl_errno($ch) . '): ' . curl_error($ch) . ', HTTP code: ' . $http_code . ', Response: ' . var_export($response, true),
                3,
                null,
                'ps_monekcheckout',
                (int)$this->context->cart->id
            );
            die('Payment request failed: ' . $response);
        }
    }
    
    private function get_ipay_url()
    {
        $ipay_extension = 'checkout.aspx';
        return (Configuration::get('MONEKCHECKOUT_TEST_MODE') ? self::STAGING_URL : self::ELITE_URL) . $ipay_extension;
    }

    private function get_ipay_prepare_url()
    {
        $ipay_prepare_extension = 'iPayPrepare.ashx';
        return (Configuration::get('MONEKCHECKOUT_TEST_MODE') ? self::STAGING_URL : self::ELITE_URL) . $ipay_prepare_extension;
    }

    private function generate_basket_base64($order)
    {
        $order_items = $this->get_item_details($order);
        $basket = array('items' => array());

        foreach ($order_items as $item) {
            $basket['items'][] = array(
                'sku' => $item['sku'] ?? '',
                'description' => $item['product_name'] ?? '',
                'quantity' => $item['quantity'] ?? '',
                'unitPrice' => $item['price'] ?? '',
                'total' => $item['total'] ?? ''
            );
        }

        $order_discounts = $this->get_order_discounts($order);
        if (!empty($order_discounts)) {
            $basket['discounts'] = array();
            foreach ($order_discounts as $discount) {
                $basket['discounts'][] = array(
                    'code' => $discount['code'] ?? '',
                    'description' => $discount['description'] ?? '',
                    'amount' => $discount['amount'] ?? ''
                );
            }
        }

        $order_taxes = $this->get_order_taxes($order);
        if (!empty($order_taxes)) {
            $basket['taxes'] = array();
            foreach ($order_taxes as $tax) {
                $basket['taxes'][] = array(
                    'code' => $tax['code'] ?? '',
                    'description' => $tax['description'] ?? '',
                    'rate' => $tax['rate'] ?? '',
                    'amount' => $tax['amount'] ?? ''
                );
            }
        }

        $order_delivery = $this->get_order_delivery($order);
        if (!empty($order_delivery)) {
            $basket['delivery'] = array(
                'carrier' => $order_delivery[0]['carrier'] ?? '',
                'amount' => $order_delivery[0]['amount'] ?? ''
            );
        }

        $basket_json = json_encode($basket);
        $basket_base64 = base64_encode($basket_json);

        return $basket_base64;
    }

    private function generate_cardholder_detail_information($request_body)
    {
        $billing_address = new Address($this->context->cart->id_address_invoice);
        $billing_country = new Country($billing_address->id_country);

        $cardholder_detail_information = array(
            'BillingName' => $billing_address->firstname . ' ' . $billing_address->lastname,
            'BillingCompany' => $billing_address->company ?? '',
            'BillingLine1' => $billing_address->address1 ?? '',
            'BillingLine2' => $billing_address->address2 ?? '',
            'BillingCity' => $billing_address->city ?? '',
            'BillingCountry' => $billing_country->name[$this->context->language->id] ?? '',
            'BillingCountryCode' => $billing_country->iso_code ?? '',
            'BillingPostcode' => $billing_address->postcode ?? '',
            'EmailAddress' => $this->context->customer->email ?? '',
            'PhoneNumber' => $billing_address->phone ?? '',
        );

        if ($this->context->cart->isVirtualCart() == false) {
            $delivery_address = new Address($this->context->cart->id_address_delivery);
            $delivery_country = new Country($delivery_address->id_country);
            $cardholder_detail_information['DeliveryName'] = $delivery_address->firstname . ' ' . $delivery_address->lastname;
            $cardholder_detail_information['DeliveryCompany'] = $delivery_address->company ?? '';
            $cardholder_detail_information['DeliveryLine1'] = $delivery_address->address1 ?? '';
            $cardholder_detail_information['DeliveryLine2'] = $delivery_address->address2 ?? '';
            $cardholder_detail_information['DeliveryCity'] = $delivery_address->city ?? '';
            $cardholder_detail_information['DeliveryCountry'] = $delivery_country->name[$this->context->language->id] ?? '';
            $cardholder_detail_information['DeliveryCountryCode'] = $delivery_country->iso_code ?? '';
            $cardholder_detail_information['DeliveryPostcode'] = $delivery_address->postcode ?? '';
        } else {
            $cardholder_detail_information['DeliveryIsBilling'] = "YES";
        }

        $merged_array = array_merge($request_body, $cardholder_detail_information);
        
        return $merged_array;
    }

    private function get_item_details($order)
    {
        $items_details = array();

        $order_detail_list = OrderDetail::getList($order->id);
        foreach ($order_detail_list as $order_detail) {
            $product = new Product($order_detail['product_id']);
            $items_details[] = array(
                'product_name' => $product->name[$this->context->language->id],
                'product_id' => $product->id,
                'sku' => $product->reference,
                'quantity' => $order_detail['product_quantity'],
                'price' => $order_detail['unit_price_tax_incl'],
                'subtotal' => $order_detail['total_price_tax_excl'],
                'total' => $order_detail['total_price_tax_incl'],
                'total_tax' => $order_detail['total_price_tax_incl'] - $order_detail['total_price_tax_excl']
            );
        }

        return $items_details;
    }

    private function get_order_delivery($order)
    {
        $delivery = array();
        $carrier = new Carrier($order->id_carrier);

        if ($carrier->id) {
            $delivery[] = array(
                'carrier' => $carrier->name,
                'amount' => $order->total_shipping
            );
        }

        return $delivery;
    }

    private function get_order_discounts($order)
    {
        $discounts = array();
        $cart_rules = $order->getCartRules();

        foreach ($cart_rules as $cart_rule) {
            $discounts[] = array(
                'code' => $cart_rule['id_cart_rule'],
                'description' => $cart_rule['name'],
                'amount' => $cart_rule['value']
            );
        }

        return $discounts;
    }

    private function get_order_taxes($order)
    {
        $taxes = array();
        $order_detail_list = OrderDetail::getList($order->id);

        foreach ($order_detail_list as $order_detail) {
            $taxes[] = array(
                'code' => $order_detail['id_tax_rules_group'],
                'description' => $order_detail['tax_name'],
                'rate' => $order_detail['tax_rate'],
                'amount' => $order_detail['total_price_tax_incl'] - $order_detail['total_price_tax_excl']
            );
        }

        return $taxes;
    }

    private function convert_decimal_to_flat($amount)
    {
        return number_format($amount, 2, '.', '') * 100;
    }

    private function get_iso4217_currency_code()
    {
        $currency = $this->context->currency;
        return $currency->iso_code_num;
    }
}
