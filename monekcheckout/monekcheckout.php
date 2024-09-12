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
 * Class monekcheckout - Monek Checkout Payment Module
 *
 * @package monek
 */
class monekcheckout extends PaymentModule
{
    public const CONFIG_BASKET_SUMMARY = 'MONEKCHECKOUT_BASKET_SUMMARY';
    public const CONFIG_COUNTRY = 'MONEKCHECKOUT_COUNTRY';
    public const CONFIG_GOOGLE_PAY = 'MONEKCHECKOUT_GOOGLE_PAY';
    public const CONFIG_MONEK_ID = 'MONEKCHECKOUT_MONEK_ID';
    public const CONFIG_TEST_MODE = 'MONEKCHECKOUT_TEST_MODE';
    public const AWAITING_ORDER_CONFIRMATION_STATE_ID = 'AWAITING_ORDER_CONFIRMATION_STATE_ID';

    public function __construct()
    {
        $this->name = 'monekcheckout';
        $this->tab = 'payments_gateways';
        $this->version = '1.1.1';
        $this->author = 'monek';
        $this->controllers = ['validation'];
        $this->is_eu_compatible = 1;

        $this->module_key = '93c7bb0b5945620c58cdc9092462795b';
        $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Monek Checkout');
        $this->description = $this->l('Redirects to Monek Checkout for payment using debit/credit card.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    /**
     * Install the module
	 *
	 * @return bool
	 */
    public function install() : bool
    {
        if (!$this->createCustomOrderState()) {
            return false;
        }

        $tableName = _DB_PREFIX_ . 'payment_tokens';
        $engine = _MYSQL_ENGINE_;
        $charset = 'utf8';

        $sql = "CREATE TABLE IF NOT EXISTS `$tableName` (
            `id_cart` int(10) unsigned NOT NULL,
            `idempotency_token` varchar(255) NOT NULL,
            `integrity_secret` varchar(255) NOT NULL,
            `confirmation_received` BOOLEAN NOT NULL DEFAULT 0,
            PRIMARY KEY (`id_cart`)
        ) ENGINE=$engine DEFAULT CHARSET=$charset;";

        if (!Db::getInstance()->execute($sql)) {
            throw new RuntimeException('Table creation failed');
        }

        PrestaShopLogger::addLog('Payment tokens table created successfully.', 1, null, 'monekcheckout');

        return parent::install()
            && $this->registerHook('paymentOptions')
            && $this->registerHook('paymentReturn')
            && $this->registerHook('displayShoppingCart')
            && Configuration::updateValue(self::CONFIG_BASKET_SUMMARY, 'Goods')
            && Configuration::updateValue(self::CONFIG_COUNTRY, 'GB')
            && Configuration::updateValue(self::CONFIG_MONEK_ID, '')
            && Configuration::updateValue(self::CONFIG_TEST_MODE, '')
            && Configuration::updateValue(self::CONFIG_GOOGLE_PAY, '');
    }

    /**
	 * Uninstall the module
     *
     * @return bool
	 */
    public function uninstall() : bool
    {
        return parent::uninstall()
            && Configuration::deleteByName(self::CONFIG_BASKET_SUMMARY)
            && Configuration::deleteByName(self::CONFIG_COUNTRY)
            && Configuration::deleteByName(self::CONFIG_MONEK_ID)
            && Configuration::deleteByName(self::CONFIG_TEST_MODE)
            && Configuration::deleteByName(self::CONFIG_GOOGLE_PAY);
    }

    /**
	 * Create custom order state - Awaiting Payment Confirmation
	 *
	 * @return bool
	 */
    private function createCustomOrderState() : bool
    {
        $orderStateExists = (bool) Db::getInstance()->getValue('SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'order_state WHERE module_name = "monekcheckout"');

        if ($orderStateExists) {
            return true;
        }

        $order_state = new OrderState();
        $order_state->name[Configuration::get('PS_LANG_DEFAULT')] = 'Awaiting Payment Confirmation';
        $order_state->send_email = false;
        $order_state->color = '#4169E1';
        $order_state->unremovable = true;
        $order_state->hidden = false;
        $order_state->delivery = false;
        $order_state->logable = true;
        $order_state->invoice = false;
        $order_state->module_name = 'monekcheckout';

        if ($order_state->add()) {
            Configuration::updateValue(self::AWAITING_ORDER_CONFIRMATION_STATE_ID, (int) $order_state->id);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Updates the configuration setting values
     *
     * @return string
	 */
    public function getContent() : string
    {
        $output = '';

        if (Tools::isSubmit("submit{$this->name}")) {
            $basketSummary = pSQL(trim(Tools::getValue(self::CONFIG_BASKET_SUMMARY)));
            $country = pSQL(Tools::getValue(self::CONFIG_COUNTRY));
            $monekid = pSQL(trim(Tools::getValue(self::CONFIG_MONEK_ID)));
            $testMode = Tools::getValue(self::CONFIG_TEST_MODE);
            $googlePayEnabled = Tools::getValue(self::CONFIG_GOOGLE_PAY);

            if (empty($monekid)) {
                $output .= $this->displayError($this->l('Invalid Monek ID'));
            } elseif (empty($country)) {
                $output .= $this->displayError($this->l('Must select country'));
            } elseif (empty($basketSummary)) {
                $output .= $this->displayError($this->l('Basket Summary can not be empty'));
            } else {
                Configuration::updateValue(self::CONFIG_BASKET_SUMMARY, $basketSummary);
                Configuration::updateValue(self::CONFIG_COUNTRY, $country);
                Configuration::updateValue(self::CONFIG_MONEK_ID, $monekid);
                Configuration::updateValue(self::CONFIG_TEST_MODE, $testMode);
                Configuration::updateValue(self::CONFIG_GOOGLE_PAY, $googlePayEnabled);
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }

        return $output . $this->renderForm();
    }

    /**
	 * Render the configuration form
	 *
	 * @return string
     */
    protected function renderForm() : string
    {
        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Monek ID'),
                        'name' => self::CONFIG_MONEK_ID,
                        'size' => 7,
                        'required' => true,
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Country'),
                        'name' => self::CONFIG_COUNTRY,
                        'required' => true,
                        'options' => [
                            'query' => $this->getCountryOptions(),
                            'id' => 'id_option',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Enable Trial Features'),
                        'name' => self::CONFIG_TEST_MODE,
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => self::CONFIG_TEST_MODE . '_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => self::CONFIG_TEST_MODE . '_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Enable GooglePay'),
                        'name' => self::CONFIG_GOOGLE_PAY,
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => self::CONFIG_GOOGLE_PAY . '_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => self::CONFIG_GOOGLE_PAY . '_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                        'desc' => $this->l('Enable this option to provide access to GooglePay as a payment option. Merchants must adhere to the Google Pay APIs Acceptable Use Policy and accept the terms defined in the Google Pay API Terms of Service.'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Basket Summary'),
                        'name' => self::CONFIG_BASKET_SUMMARY,
                        'size' => 50,
                        'required' => true,
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit' . $this->name;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$fieldsForm]);
    }

    /**
     * Get the configuration field values
     *
     * @return array
	 */
    protected function getConfigFieldsValues() : array
    {
        return [
            self::CONFIG_BASKET_SUMMARY => Tools::getValue(self::CONFIG_BASKET_SUMMARY, Configuration::get(self::CONFIG_BASKET_SUMMARY)),
            self::CONFIG_COUNTRY => Tools::getValue(self::CONFIG_COUNTRY, Configuration::get(self::CONFIG_COUNTRY)),
            self::CONFIG_MONEK_ID => Tools::getValue(self::CONFIG_MONEK_ID, Configuration::get(self::CONFIG_MONEK_ID)),
            self::CONFIG_TEST_MODE => Tools::getValue(self::CONFIG_TEST_MODE, Configuration::get(self::CONFIG_TEST_MODE)),
            self::CONFIG_GOOGLE_PAY => Tools::getValue(self::CONFIG_GOOGLE_PAY, Configuration::get(self::CONFIG_GOOGLE_PAY)),
        ];
    }

    /**
	 * Get the country options
	 *
	 * @return array
     */
    protected function getCountryOptions() : array
    {
        $countries = Country::getCountries($this->context->language->id);
        $options = [];

        foreach ($countries as $country) {
            $options[] = [
                'id_option' => $country['iso_code'],
                'name' => $country['name'],
            ];
        }

        return $options;
    }

    /**
     * Display the payment error message
     *
     * @param array $params
     * @return string
	 */
    public function hookDisplayShoppingCart(array $params) : string
    { 
        if (isset($this->context->cookie->payment_error_message)) {
            $this->context->smarty->assign('payment_error_message', htmlspecialchars($this->context->cookie->payment_error_message));
            unset($this->context->cookie->payment_error_message);
        
            return $this->display(__FILE__, 'views/templates/front/payment_error.tpl');
        }
        return '';
    }

    /**
     * Display the monek checkout payment option
     *
     * @param array $params
     * @return array
     */
    public function hookPaymentOptions(array $params) : array
    {
        if (!$this->active) {
            return [];
        }

        $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $newOption->setCallToActionText($this->l('Pay by Debit/Credit Card'));
        $newOption->setAction($this->context->link->getModuleLink($this->name, 'validation', [], true));
        $newOption->setAdditionalInformation($this->context->smarty->fetch('module:monekcheckout/views/templates/front/monek_payment_message.tpl'));

        return [$newOption];
    }

    /**
     * Display the payment complete message
	 *
	 * @param array $params
	 * @return string
	 */
    public function hookPaymentReturn(array $params) : string
    {
        if (!$this->active) {
            return '';
        }

        return $this->context->smarty->fetch('module:monekcheckout/views/templates/front/payment_complete_message.tpl');
    }
}
