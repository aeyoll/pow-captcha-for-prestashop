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

        $this->setTemplate('module:pow_captcha/views/templates/front/ajax.tpl');
    }
}
