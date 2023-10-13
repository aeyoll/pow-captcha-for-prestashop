<?php

if (!defined('_PS_VERSION_')) {
    exit;
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
            'actionBeforeContactSubmit',
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
        if (((bool)Tools::isSubmit('submitPow_captchaModule')) == true) {
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

    public function hookDisplayHeader()
    {
        $shouldDisplayCaptcha = $this->context->controller instanceof ContactController
            && Configuration::get('POW_CAPTCHA_ENABLE') == 1;

        if ($shouldDisplayCaptcha) {
            $baseUrl = Configuration::get('POW_CAPTCHA_API_URL');
            $powCaptchaJavascriptUrl = $baseUrl . 'static/captcha.js?v=1.0';

            $this->context->smarty->assign('powCaptchaJavascriptUrl', $powCaptchaJavascriptUrl);

            return $this->display(__FILE__, 'views/templates/hook/header.tpl');
        }
    }

    public function hookDisplayBeforeContactFormSubmit()
    {
        return $this->display(__FILE__, 'views/templates/hook/beforeContactFormSubmit.tpl');
    }

    public function hookActionBeforeContactSubmit()
    {
        $shouldValidateCaptcha = $this->context->controller instanceof ContactController
            && Configuration::get('POW_CAPTCHA_ENABLE') == 1
            && Tools::isSubmit('submitMessage');

        if ($shouldValidateCaptcha) {
            $challenge = Tools::getValue('challenge', '');
            $nonce = Tools::getValue('nonce', '');
            $isValid = $this->validateCaptcha($challenge, $nonce);

            if (!$isValid) {
                $this->context->controller->errors[] = $this->trans('Captcha is not valid', [], 'Modules.PowCaptcha.Front');
            }
        }
    }

    /**
     * @param $url The path to request
     * @param $returnStatusCode If true; return the status code instead of the content
     */
    protected function request(string $url, $returnStatusCode = false)
    {
        $baseUrl = Configuration::get('POW_CAPTCHA_API_URL');
        $apiToken = Configuration::get('POW_CAPTCHA_API_TOKEN');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, sprintf('%s%s', $baseUrl, $url));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [sprintf('Authorization: Bearer %s', $apiToken)]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($returnStatusCode) {
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }

        $response = @curl_exec($ch);

        if ($returnStatusCode) {
            return curl_getinfo($ch, CURLINFO_HTTP_CODE);
        } else {
            return $response;
        }
    }

    public function validateCaptcha(string $challenge, string $nonce): bool
    {
        $url = sprintf('Verify?challenge=%s&nonce=%s', $challenge, $nonce);

        try {
            $statusCode = $this->request($url, true);
            return $statusCode === 200;
        } catch (\Exception $e) {
            return false;
        }
    }
}
