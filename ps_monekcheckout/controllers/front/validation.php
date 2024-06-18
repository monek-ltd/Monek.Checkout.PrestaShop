<?php

class Ps_MonekCheckoutValidationModuleFrontController extends ModuleFrontController
{
    private const PARTIAL_ORIGIN_ID = 'a6c921f4-8e00-4b11-99f4-';
    private $is_test_mode_active = true; // Change this as per your requirement

    public function postProcess()
    {
        $cart = $this->context->cart;
        $customer = new Customer($cart->id_customer);
        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);

        $this->module->validateOrder($cart->id, Configuration::get('PS_OS_PAYMENT'), $total, $this->module->displayName, null, [], (int)$currency->id, false, $customer->secure_key);

        $order = new Order($this->module->currentOrder);
        $return_url = $this->context->link->getModuleLink($this->module->name, 'return', [], true);

        $body_data = $this->prepare_payment_request_body_data($order, '0000893', '826', $return_url, 'Test Purchase');
        $this->send_payment_request($body_data);
    }

    private function prepare_payment_request_body_data($order, $merchant_id, $country_code, $return_plugin_url, $purchase_description)
    {
        $billing_amount = $order->getTotalPaid();
        
        // Generate idempotency token and integrity secret
        $idempotency_token = uniqid($order->id, true);
        $integrity_secret = uniqid($order->id, true);
        
        // Prepare body data array
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
            'OriginID' => self::PARTIAL_ORIGIN_ID . str_replace('.', '', '1.0.0') . str_repeat('0', 14 - strlen('1.0.0')), //TODO Replace '1.0.0' with your plugin version
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

        Tools::redirect('https://staging.monek.com/Secure/checkout.aspx'. '?' . http_build_query($body_data));
    }

    private function get_ipay_prepare_url()
    {
        //TODO Set correct urls
        $ipay_prepare_extension = 'iPayPrepare.ashx';
        return ($this->is_test_mode_active ? 'https://staging.url/' : 'https://production.url/') . $ipay_prepare_extension;
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
            'BillingCounty' => $billing_address->state ?? '', //TODO: State does not exist. No county info?
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
            $cardholder_detail_information['DeliveryCounty'] = $delivery_address->state ?? ''; //TODO: State does not exist. No county info?
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

        //TODO: check if we can get tracking info??

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
        //TODO: Check if this is correct
        //Discount information is also stored against the order...
        //TODO: Check how i would add a discount in prestashop.
        $discounts = array();
        $cart_rules = $order->getCartRules();

        foreach ($cart_rules as $cart_rule) {
            $discounts[] = array(
                'code' => $cart_rule['name'],
                'description' => $cart_rule['description'],
                'amount' => $cart_rule['value']
            );
        }

        return $discounts;
    }

    private function get_order_taxes($order)
    {
        $taxes = array();
        $order_detail_list = OrderDetail::getList($order->id);

        //TODO: this is currently not working, tax information is stored against the order. 
        //TODO: Also check how tax is added in prestashop

        foreach ($order_detail_list as $order_detail) {
            $tax = new Tax($order_detail['id_tax']);
            $taxes[] = array(
                'code' => $tax->name[$this->context->language->id],
                'description' => $tax->description[$this->context->language->id],
                'rate' => $tax->rate,
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
