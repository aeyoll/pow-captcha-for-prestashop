<?php

use PrestaShop\Module\PowCaptcha\Service\PowCaptchaService;

if (!defined('_PS_VERSION_')) {
    exit;
}

$autoloadPath = __DIR__ . '/vendor/autoload.php';

if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

class Pow_Captcha extends Module
{
    public function __construct()
    {
        $this->name = 'pow_captcha';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'aeyoll';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Pow Captcha', [], 'Modules.PowCaptcha.Admin');
        $this->description = $this->trans('Pow Captcha for PrestaShop', [], 'Modules.Mercerine.Admin');

        $this->ps_versions_compliancy = ['min' => '1.6.0.0', 'max' => _PS_VERSION_];

        $this->errors = array();
    }

    public function install()
    {
        $hooks = [
            'displayHeader',
            'displayBeforeContactFormSubmit',
            'actionControllerInitAfter',
        ];

        return parent::install()
            && $this->registerHook($hooks);
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitPow_captchaModule'))) {
            $this->postProcess();
        }

        return $this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitPow_captchaModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Enabled'),
                        'name' => 'POW_CAPTCHA_ENABLE',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'type' => 'text',
                        'name' => 'POW_CAPTCHA_API_TOKEN',
                        'label' => $this->l('Token'),
                    ),
                    array(
                        'type' => 'text',
                        'name' => 'POW_CAPTCHA_API_URL',
                        'label' => $this->l('Url'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'POW_CAPTCHA_ENABLE' => Configuration::get('POW_CAPTCHA_ENABLE', true),
            'POW_CAPTCHA_API_TOKEN' => Configuration::get('POW_CAPTCHA_API_TOKEN', ''),
            'POW_CAPTCHA_API_URL' => Configuration::get('POW_CAPTCHA_API_URL', ''),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
     * This hook loads Pow Captcha scripts
     *
     * @return void
     */
    public function hookDisplayHeader()
    {
        $shouldDisplayCaptcha = Configuration::get('POW_CAPTCHA_ENABLE') == 1;

        if ($shouldDisplayCaptcha) {
            $baseUrl = Configuration::get('POW_CAPTCHA_API_URL');
            $powCaptchaJavascriptUrl = $baseUrl . 'static/captcha.js?v=1.0';

            $this->context->smarty->assign('powCaptchaJavascriptUrl', $powCaptchaJavascriptUrl);

            return $this->display(__FILE__, 'views/templates/hook/header.tpl');
        }
    }

    /**
     * This hook loads the Pow Captcha markup for each form
     *
     * @param array $params
     * @return void
     */
    public function hookDisplayBeforeContactFormSubmit($params)
    {
        if (!array_key_exists('id', $params) || !$params['id']) {
            $this->context->controller->errors[] = $this->trans('Missing id parameter in displayBeforeContactFormSubmit hook', [], 'Modules.PowCaptcha.Front');
            return;
        }

        if (!array_key_exists('form', $params) || !$params['form']) {
            $this->context->controller->errors[] = $this->trans('Missing form parameter in displayBeforeContactFormSubmit hook', [], 'Modules.PowCaptcha.Front');
            return;
        }

        $this->context->smarty->assign([
            'pow_captcha_id' => $params['id'],
            'pow_captcha_form' => $params['form'],
        ]);

        return $this->display(__FILE__, 'views/templates/hook/beforeContactFormSubmit.tpl');
    }

    /**
     * This hook is called to validate the captcha
     *
     * @return void
     */
    public function hookActionControllerInitAfter()
    {
        $shouldValidateCaptcha = Configuration::get('POW_CAPTCHA_ENABLE') == 1
            && Tools::isSubmit('challenge')
            && Tools::isSubmit('nonce');

        if ($shouldValidateCaptcha) {
            $challenge = Tools::getValue('challenge', '');
            $nonce = Tools::getValue('nonce', '');

            PrestaShopLogger::addLog(sprintf('[pow_captcha]: Trying to validate captcha with challenge %s and nonce %s', $challenge, $nonce));

            $pcs = new PowCaptchaService();
            $isValid = $pcs->validateCaptcha($challenge, $nonce);

            if (!$isValid) {
                PrestaShopLogger::addLog('[pow_captcha]: Failed to validate captcha', 2);
                $this->context->controller->errors[] = $this->trans('Captcha is not valid', [], 'Modules.PowCaptcha.Front');

                // When the controller is a ModuleFrontController, the erreor can be
                // in "controller->module->error" (e.g. in ps_emailsubscription)
                if (property_exists($this->context->controller, 'module')) {
                    $this->context->controller->module->error = $this->trans('Captcha is not valid', [], 'Modules.PowCaptcha.Front');
                }

                return;
            } else {
                PrestaShopLogger::addLog('[pow_captcha]: Captcha validated successfully');
            }
        }
    }
}
