<?php

use PrestaShop\Module\PowCaptcha\Service\PowCaptchaService;

if (!defined('_PS_VERSION_')) {
    exit;
}


$autoloadPaths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
];

foreach ($autoloadPaths as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    }
}

class Pow_Captcha extends Module
{
    public $errors = [];

    public function __construct()
    {
        $this->name = 'pow_captcha';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'aeyoll';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Pow Captcha');
        $this->description = $this->l('Pow Captcha for PrestaShop');

        $this->ps_versions_compliancy = ['min' => '1.6.0.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        $hooks = [
            'displayHeader',
            'displayBeforeContactFormSubmit',
            'actionControllerInitAfter',
            'actionSubmitAccountBefore',
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
        return $this->display(__FILE__, 'views/templates/hook/beforeContactFormSubmit.tpl');
    }

    public function shouldValidateCaptcha()
    {
        $captchaEnabled = Configuration::get('POW_CAPTCHA_ENABLE') == 1;
        $shouldValidateCaptcha = Tools::isSubmit('challenge') && Tools::isSubmit('nonce');

        $isSubmittingContactPage = $this->context->controller->php_self == 'contact' && $_SERVER['REQUEST_METHOD'] === 'POST';
        $isSubmittingRegistration = (Tools::getValue('create_account') == 1 || Tools::getValue('submitCreate') == 1) && $_SERVER['REQUEST_METHOD'] === 'POST';
        $isSubmittingNewsletter = (Tools::getValue('module') == 'ps_emailsubscription' && Tools::getValue('controller') == 'subscription' && $_SERVER['REQUEST_METHOD'] === 'POST') || Tools::getValue('submitNewsletter');

        return $captchaEnabled && ($shouldValidateCaptcha || $isSubmittingContactPage || $isSubmittingRegistration || $isSubmittingNewsletter);
    }

    /**
     * This hook is called to validate the captcha
     *
     * @return void
     */
    public function hookActionControllerInitAfter()
    {
        $shouldValidateCaptcha = $this->shouldValidateCaptcha();

        if ($shouldValidateCaptcha && Tools::getValue('create_account') != 1 && Tools::getValue('submitCreate') != 1) {
            $challenge = Tools::getValue('challenge', '');
            $nonce = Tools::getValue('nonce', '');
            $challengeDisplay = is_null($challenge) ? 'undefined' : $challenge;
            $nonceDisplay = is_null($nonce) ? 'undefined' : $nonce;

            $this->log(sprintf('Trying to validate captcha: challenge=[%s] nonce=[%s]', $challengeDisplay, $nonceDisplay));

            $pcs = new PowCaptchaService();
            $isValid = $pcs->validateCaptcha($challenge, $nonce);

            if (!$isValid) {
                $this->log(sprintf('Failed to validate captcha: challenge=[%s] nonce=[%s]', $challengeDisplay, $nonceDisplay), PrestaShopLoggerCore::LOG_SEVERITY_LEVEL_WARNING);
                $this->context->controller->errors[] = $this->l('Captcha is not valid');

                // When the controller is a ModuleFrontController, the error can be
                // in "controller->module->error" (e.g. in ps_emailsubscription)
                if (property_exists($this->context->controller, 'module')) {
                    $this->context->controller->module->error = $this->l('Captcha is not valid');
                }

                return;
            } else {
                $this->log(sprintf('Captcha validated successfully: challenge=[%s] nonce=[%s]', $challengeDisplay, $nonceDisplay));
            }
        }
    }

    public function hookActionSubmitAccountBefore()
    {
        $shouldValidateCaptcha = $this->shouldValidateCaptcha();

        $isAccountCreation = Tools::getValue('create_account') == 1 && Tools::getValue('submitCreate') == 1;
        $needsCaptchaValidation = $shouldValidateCaptcha && $isAccountCreation;

        if ($needsCaptchaValidation) {
            $challenge = Tools::getValue('challenge', null);
            $nonce = Tools::getValue('nonce', null);
            $challengeDisplay = is_null($challenge) ? 'undefined' : $challenge;
            $nonceDisplay = is_null($nonce) ? 'undefined' : $nonce;

            $this->log(sprintf('Trying to validate captcha: challenge=[%s] nonce=[%s] create_account=[%s] submit_create=[%s]', $challengeDisplay, $nonceDisplay, Tools::getValue('create_account'), Tools::getValue('submitCreate')));

            $pcs = new PowCaptchaService();
            $isValid = $pcs->validateCaptcha($challenge, $nonce);
            if (!$isValid) {
                $this->log(sprintf('Failed to validate captcha during account submission: challenge=[%s] nonce=[%s]', $challengeDisplay, $nonceDisplay), PrestaShopLoggerCore::LOG_SEVERITY_LEVEL_WARNING);

                $this->context->controller->errors[] = $this->l('Captcha is not valid, try again');
                return;
            } else {
                $this->log(sprintf('Registration Captcha validated successfully during account submission: challenge=[%s] nonce=[%s]', $challengeDisplay, $nonceDisplay));
                return true;
            }
        }
    }

    protected function log($message, $severity = PrestaShopLoggerCore::LOG_SEVERITY_LEVEL_INFORMATIVE)
    {
        $ip = Tools::getRemoteAddr();
        $context = Context::getContext();
        $userId = isset($context->customer) ? $context->customer->id : null;

        $fullMessage = "[pow_captcha] ip=[{$ip}]" . ($userId ? "| user=[{$userId}]" : " | guest |") . "  " . $message;

        PrestaShopLogger::addLog($fullMessage, $severity);
    }
}
