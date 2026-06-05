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
        $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

        $isSubmittingContactPage = $this->context->controller->php_self == 'contact' && $isPost;
        $isSubmittingRegistration = $this->isRegistrationSubmission();
        $isSubmittingNewsletter = (Tools::getValue('module') == 'ps_emailsubscription' && Tools::getValue('controller') == 'subscription' && $isPost) || Tools::getValue('submitNewsletter');
        $isSubmittingPasswordReset = $this->context->controller->php_self == 'password' && $isPost;

        return $captchaEnabled && ($shouldValidateCaptcha || $isSubmittingContactPage || $isSubmittingRegistration || $isSubmittingNewsletter || $isSubmittingPasswordReset);
    }

    /**
     * Detects account-creation POST requests on registration/checkout controllers.
     */
    protected function isRegistrationSubmission(): bool
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return false;
        }

        $phpSelf = $this->context->controller->php_self ?? '';

        if (!in_array($phpSelf, ['registration', 'authentication', 'order', 'orderopc'], true)) {
            return false;
        }

        return Tools::getValue('create_account') == 1 || Tools::getValue('submitCreate') == 1;
    }

    /**
     * Registration on the dedicated page is handled by hookActionSubmitAccountBefore.
     * Checkout controllers validate here because that hook may not fire.
     */
    protected function shouldValidateCaptchaInInitAfter(): bool
    {
        return ($this->context->controller->php_self ?? '') !== 'registration';
    }

    /**
     * This hook is called to validate the captcha
     *
     * @return void
     */
    public function hookActionControllerInitAfter()
    {
        $shouldValidateCaptcha = $this->shouldValidateCaptcha();

        if ($shouldValidateCaptcha && $this->shouldValidateCaptchaInInitAfter()) {
            $challenge = Tools::getValue('challenge', '');
            $nonce = Tools::getValue('nonce', '');

            if (!$this->validateSubmittedCaptcha($challenge, $nonce)) {
                $this->log('Failed to validate captcha', PrestaShopLoggerCore::LOG_SEVERITY_LEVEL_WARNING);
                $this->context->controller->errors[] = $this->l('Captcha is not valid');

                // When the controller is a ModuleFrontController, the error can be
                // in "controller->module->error" (e.g. in ps_emailsubscription)
                if (property_exists($this->context->controller, 'module') && $this->context->controller->module) {
                    $this->context->controller->module->error = $this->l('Captcha is not valid');
                }

                return;
            }

            $this->log('Captcha validated successfully');
        }
    }

    public function hookActionSubmitAccountBefore()
    {
        $shouldValidateCaptcha = $this->shouldValidateCaptcha();
        $needsCaptchaValidation = $shouldValidateCaptcha && $this->isRegistrationSubmission();

        if (!$needsCaptchaValidation) {
            return true;
        }

        $challenge = Tools::getValue('challenge', null);
        $nonce = Tools::getValue('nonce', null);

        $this->log('Trying to validate captcha during account submission');

        if (!$this->validateSubmittedCaptcha($challenge, $nonce)) {
            $this->log('Failed to validate captcha during account submission', PrestaShopLoggerCore::LOG_SEVERITY_LEVEL_WARNING);

            $this->context->controller->errors[] = $this->l('Captcha is not valid, try again');

            return false;
        }

        $this->log('Registration captcha validated successfully during account submission');

        return true;
    }

    /**
     * Stores the issued challenge in the visitor cookie for later verification.
     */
    public function issueChallenge($challenge): void
    {
        if (empty($challenge)) {
            return;
        }

        $this->context->cookie->pow_captcha_challenge = $challenge;
        $this->context->cookie->write();
    }

    /**
     * Validates challenge and nonce, ensuring the challenge was issued to this visitor.
     */
    protected function validateSubmittedCaptcha($challenge, $nonce): bool
    {
        if (!$this->isIssuedChallenge($challenge)) {
            $this->log('Submitted challenge does not match issued challenge', PrestaShopLoggerCore::LOG_SEVERITY_LEVEL_WARNING);

            return false;
        }

        $pcs = new PowCaptchaService();
        $isValid = $pcs->validateCaptcha($challenge, $nonce);

        if ($isValid) {
            $this->clearIssuedChallenge();
        }

        return $isValid;
    }

    protected function isIssuedChallenge($challenge): bool
    {
        if (empty($challenge)) {
            return false;
        }

        $issuedChallenge = $this->context->cookie->pow_captcha_challenge ?? '';

        return !empty($issuedChallenge) && hash_equals((string) $issuedChallenge, (string) $challenge);
    }

    protected function clearIssuedChallenge(): void
    {
        $this->context->cookie->pow_captcha_challenge = '';
        $this->context->cookie->write();
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
