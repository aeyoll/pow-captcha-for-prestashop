<?php

use PrestaShop\Module\PowCaptcha\Service\PowCaptchaService;

$autoloadPaths = [
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../../vendor/autoload.php',
];

foreach ($autoloadPaths as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    }
}

class Pow_CaptchaAjaxModuleFrontController extends ModuleFrontController
{
    public $ajax = true;

    public function initContent()
    {
        parent::initContent();

        $powCaptchaApiUrl = Configuration::get('POW_CAPTCHA_API_URL');

        $pcs = new PowCaptchaService();
        $challenge = $pcs->getChallenge();

        $this->context->smarty->assign([
            'powCaptchaApiUrl' => $powCaptchaApiUrl,
            'challenge' => $challenge,
        ]);

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');

        if (version_compare(_PS_VERSION_, '1.7.0.0', '<')) {
            $this->context->smarty->display(__DIR__ . '/../../views/templates/front/ajax.tpl');
            die();
        } else {
            $this->setTemplate('module:pow_captcha/views/templates/front/ajax.tpl');
        }
    }
}
