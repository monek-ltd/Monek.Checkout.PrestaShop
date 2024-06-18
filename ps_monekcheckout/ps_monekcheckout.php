<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class ps_monekcheckout extends PaymentModule
{
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
        $this->description = $this->l('Redirects to an external URL for payment.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function install()
    {
        return parent::install() &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('paymentReturn');
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $newOption->setCallToActionText($this->l('Pay securely with Monek'));
        $newOption->setAction($this->context->link->getModuleLink($this->name, 'validation', [], true));
        $newOption->setAdditionalInformation($this->context->smarty->fetch('module:ps_monekcheckout/views/templates/front/redirect.tpl'));

        return [$newOption];
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        return $this->context->smarty->fetch('module:ps_monekcheckout/views/templates/front/payment_return.tpl');
    }
}
