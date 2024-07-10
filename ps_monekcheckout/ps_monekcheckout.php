<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class ps_monekcheckout extends PaymentModule
{
    const CONFIG_BASKET_SUMMARY = 'MONEKCHECKOUT_BASKET_SUMMARY';
    const CONFIG_COUNTRY = 'MONEKCHECKOUT_COUNTRY';
    const CONFIG_MONEK_ID = 'MONEKCHECKOUT_MONEK_ID';
    const CONFIG_TEST_MODE = 'MONEKCHECKOUT_TEST_MODE';
    const AWAITING_ORDER_CONFIRMATION_STATE_ID = 'AWAITING_ORDER_CONFIRMATION_STATE_ID';

    public function __construct()
    {
        $this->name = 'ps_monekcheckout';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'monek';
        $this->controllers = ['validation'];
        $this->is_eu_compatible = 1;

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('Monek Checkout');
        $this->description = $this->l('Take payments with debit/credit cards securely and easily.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function install()
    {
        if (!$this->createCustomOrderState()) {
            return false;
        }
        
        $sql = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "payment_tokens` ( 
            `id_cart` int(10) unsigned NOT NULL,
            `idempotency_token` varchar(255) NOT NULL,
            `integrity_secret` varchar(255) NOT NULL,
            PRIMARY KEY (`id_cart`)
        ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8;";

        if (!Db::getInstance()->execute($sql)) {
            return false;
        }

        return parent::install() &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('paymentReturn') &&
            Configuration::updateValue(self::CONFIG_BASKET_SUMMARY, 'Goods') &&
            Configuration::updateValue(self::CONFIG_COUNTRY, 'GB') &&
            Configuration::updateValue(self::CONFIG_MONEK_ID, '') &&
            Configuration::updateValue(self::CONFIG_TEST_MODE, '');
    }

    public function uninstall()
    {
        return parent::uninstall() &&
            Configuration::deleteByName(self::CONFIG_BASKET_SUMMARY) &&
            Configuration::deleteByName(self::CONFIG_COUNTRY) &&
            Configuration::deleteByName(self::CONFIG_MONEK_ID) &&
            Configuration::deleteByName(self::CONFIG_TEST_MODE);
    }   
    
    private function createCustomOrderState()
    {
        $orderStateExists = (bool)Db::getInstance()->getValue('SELECT COUNT(*) FROM '._DB_PREFIX_.'order_state WHERE module_name = "ps_monekcheckout"');

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
        $order_state->module_name = 'ps_monekcheckout';

        if ($order_state->add()) {
            Configuration::updateValue(self::AWAITING_ORDER_CONFIRMATION_STATE_ID, (int)$order_state->id);
            return true;
        } else {
            return false;
        }
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submit'.$this->name)) {
            $basketSummary = Tools::getValue(self::CONFIG_BASKET_SUMMARY);
            $country = Tools::getValue(self::CONFIG_COUNTRY);
            $monekid = Tools::getValue(self::CONFIG_MONEK_ID);
            $testMode = Tools::getValue(self::CONFIG_TEST_MODE);

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
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }

        return $output.$this->renderForm();
    }

    protected function renderForm()
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
                            'query'  => $this->getCountryOptions(),
                            'id' => 'id_option',
                            'name' => 'name'
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Enable Trial Features'),
                        'name' => self::CONFIG_TEST_MODE,
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => self::CONFIG_TEST_MODE.'_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ],
                            [
                                'id' => self::CONFIG_TEST_MODE.'_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            ]
                        ]
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Basket Summary'),
                        'name' => self::CONFIG_BASKET_SUMMARY,
                        'size' => 50,
                        'required' => 'true'
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
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int)Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submit'.$this->name;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$fieldsForm]);
    }

    protected function getConfigFieldsValues()
    {
        return [
            self::CONFIG_BASKET_SUMMARY => Tools::getValue(self::CONFIG_BASKET_SUMMARY, Configuration::get(self::CONFIG_BASKET_SUMMARY)),
            self::CONFIG_COUNTRY => Tools::getValue(self::CONFIG_COUNTRY, Configuration::get(self::CONFIG_COUNTRY)),
            self::CONFIG_MONEK_ID => Tools::getValue(self::CONFIG_MONEK_ID, Configuration::get(self::CONFIG_MONEK_ID)),
            self::CONFIG_TEST_MODE => Tools::getValue(self::CONFIG_TEST_MODE, Configuration::get(self::CONFIG_TEST_MODE)),
        ];
    }
    protected function getCountryOptions()
    {
        $countries = Country::getCountries($this->context->language->id);
        $options = [];

        foreach ($countries as $country) {
            $options[] = [
                'id_option' => $country['iso_code'],
                'name' => $country['name']
            ];
        }

        return $options;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $newOption->setCallToActionText($this->l('Pay by Debit/Credit Card'));
        $newOption->setAction($this->context->link->getModuleLink($this->name, 'validation', [], true));
        $newOption->setAdditionalInformation($this->context->smarty->fetch('module:ps_monekcheckout/views/templates/front/monek_payment_message.tpl'));

        return [$newOption];
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        return $this->context->smarty->fetch('module:ps_monekcheckout/views/templates/front/payment_complete_message.tpl');
    }
}
