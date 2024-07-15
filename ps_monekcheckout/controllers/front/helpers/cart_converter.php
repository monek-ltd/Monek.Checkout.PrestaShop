<?php

class CartConverter {
    private const PARTIAL_ORIGIN_ID = '7d07f975-b3c6-46b8-8f3a-';

    private function convert_decimal_to_flat($amount)
    {
        return number_format($amount, 2, '.', '') * 100;
    }

    private function generate_basket_base64($cart)
    {
        $order_items = $this->get_item_details($cart);
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

        $order_discounts = $this->get_order_discounts($cart);
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

        $order_taxes = $this->get_order_taxes($cart);
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

        $order_delivery = $this->get_order_delivery($cart);
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

    private function generate_cardholder_detail_information($context, $request_body)
    {
        $billing_address = new Address($context->cart->id_address_invoice);
        $billing_country = new Country($billing_address->id_country);

        $cardholder_detail_information = array(
            'BillingName' => $billing_address->firstname . ' ' . $billing_address->lastname,
            'BillingCompany' => $billing_address->company ?? '',
            'BillingLine1' => $billing_address->address1 ?? '',
            'BillingLine2' => $billing_address->address2 ?? '',
            'BillingCity' => $billing_address->city ?? '',
            'BillingCountry' => $billing_country->name[$context->language->id] ?? '',
            'BillingCountryCode' => $billing_country->iso_code ?? '',
            'BillingPostcode' => $billing_address->postcode ?? '',
            'EmailAddress' => $context->customer->email ?? '',
            'PhoneNumber' => $billing_address->phone ?? '',
        );

        if ($context->cart->isVirtualCart() == false) {
            $delivery_address = new Address($context->cart->id_address_delivery);
            $delivery_country = new Country($delivery_address->id_country);
            $cardholder_detail_information['DeliveryName'] = $delivery_address->firstname . ' ' . $delivery_address->lastname;
            $cardholder_detail_information['DeliveryCompany'] = $delivery_address->company ?? '';
            $cardholder_detail_information['DeliveryLine1'] = $delivery_address->address1 ?? '';
            $cardholder_detail_information['DeliveryLine2'] = $delivery_address->address2 ?? '';
            $cardholder_detail_information['DeliveryCity'] = $delivery_address->city ?? '';
            $cardholder_detail_information['DeliveryCountry'] = $delivery_country->name[$context->language->id] ?? '';
            $cardholder_detail_information['DeliveryCountryCode'] = $delivery_country->iso_code ?? '';
            $cardholder_detail_information['DeliveryPostcode'] = $delivery_address->postcode ?? '';
        } else {
            $cardholder_detail_information['DeliveryIsBilling'] = "YES";
        }

        $merged_array = array_merge($request_body, $cardholder_detail_information);
        
        return $merged_array;
    }

    private function get_item_details($cart)
    {
        $items_details = array();

        $cart_products = $cart->getProducts();
        foreach ($cart_products as $product) {
            $items_details[] = array(
                'product_name' => $product['name'],
                'product_id' => $product['id_product'],
                'sku' => $product['reference'],
                'quantity' => $product['cart_quantity'],
                'price' => $product['price_wt'],
                'subtotal' => $product['total'],
                'total' => $product['total_wt'],
                'total_tax' => $product['total_wt'] - $product['total']
            );
        }

        return $items_details;
    }

    private function get_order_delivery($cart)
    {
        $delivery = array();
        $carrier = new Carrier($cart->id_carrier);
        $shippingCostWithTax = $cart->getOrderTotal(true, Cart::ONLY_SHIPPING);

        if ($carrier->id) {
            $delivery[] = array(
                'carrier' => $carrier->name,
                'amount' => $shippingCostWithTax
            );
        }

        return $delivery;
    }

    private function get_order_discounts($cart)
    {
        $discounts = array();
        $cart_rules = $cart->getCartRules();

        foreach ($cart_rules as $cart_discount) {
            $amount = 0;
        
            if ($cart_discount['reduction_amount'] > 0) {
                $amount = $cart_discount['reduction_amount'];
            } else {
                $amount = $cart->getOrderTotal(true, Cart::BOTH) * ($cart_discount['reduction_percent'] / 100);
            }

            $discounts[] = array(
                'code' => $cart_discount['code'],
                'description' => $cart_discount['description'],
                'amount' => $amount
            );
        }

        return $discounts;
    }

    private function get_order_taxes($cart)
    {
        $taxes = array();
        $cart_products = $cart->getProducts();
        

        foreach ($cart_products as $product) {
            $tax_rate = $product['rate'];
            $total_tax = $product['total_wt'] - $product['total'];
            $tax_name = $product['tax_name'];

            $found = false;
            foreach ($taxes as &$tax) {
                if ($tax['description'] == $tax_name && $tax['rate'] == $tax_rate) {
                    $tax['amount'] += $total_tax;
                    $found = true;
                    break;
                }
            }
            unset($tax); 

            if (!$found) {
                $taxes[] = array(
                    'description' => $tax_name,
                    'rate' => $tax_rate,
                    'amount' => $total_tax
                );
            }
        }

        return $taxes;
    }

    private function get_iso4217_currency_code($context)
    {
        $currency = $context->currency;
        return $currency->iso_code_num;
    }

    public function prepare_payment_request_body_data($context, $cart, $merchant_id, $country_code, $return_plugin_url, $purchase_description)
    {
        $billing_amount = $cart->getOrderTotal(true, Cart::BOTH);
    
        $idempotency_token = uniqid($cart->id, true);
        $integrity_secret = uniqid($cart->id, true);

        PrestaShopLogger::addLog('Saving identity tokens to database.', 1, null, 'ps_monekcheckout', (int)$cart->id);

       $data = [
            'id_cart' => (int)$cart->id,
            'idempotency_token' => pSQL($idempotency_token),
            'integrity_secret' => pSQL($integrity_secret),
        ];

        Db::getInstance()->insert('payment_tokens', $data, false, false, Db::ON_DUPLICATE_KEY);


        $body_data = array(
            'MerchantID' => $merchant_id,
            'MessageType' => 'ESALE_KEYED',
            'Amount' => $this->convert_decimal_to_flat($billing_amount),
            'CurrencyCode' => $this->get_iso4217_currency_code($context),
            'CountryCode' => $country_code,
            'Dispatch' => 'NOW',
            'ResponseAction' => 'REDIRECT',
            'RedirectUrl' => $return_plugin_url, 
            'WebhookUrl' => $return_plugin_url,
            'PaymentReference' => $cart->id,
            'ThreeDSAction' => 'ACSDIRECT',
            'IdempotencyToken' => $idempotency_token,
            'OriginID' => self::PARTIAL_ORIGIN_ID . str_replace('.', '',  Module::getInstanceByName("ps_monekcheckout")->version) . str_repeat('0', 14 - strlen(Module::getInstanceByName("ps_monekcheckout")->version)),
            'PurchaseDescription' => $purchase_description,
            'IntegritySecret' => $integrity_secret,
            'Basket' => $this->generate_basket_base64($cart),
            'ShowDeliveryAddress' => 'YES'
        );

        $body_data = $this->generate_cardholder_detail_information($context, $body_data);

        return $body_data;
    }
}