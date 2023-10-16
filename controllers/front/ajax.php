<?php

use PrestaShop\Module\PowCaptcha\Service\PowCaptchaService;

$autoloadPath = __DIR__ . '/../../vendor/autoload.php';

if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

class Pow_CaptchaAjaxModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $powCaptchaApiUrl = Configuration::get('POW_CAPTCHA_API_URL');
        $id = Tools::getValue('id');
        $form = Tools::getValue('form');

        $pcs = new PowCaptchaService();
        $challenge = $pcs->getChallenge();

        $this->context->smarty->assign([
            'powCaptchaApiUrl' => $powCaptchaApiUrl,
            'challenge' => $challenge,
            'id' => $id,
            'form' => $form,
        ]);

        $this->setTemplate('module:pow_captcha/views/templates/front/ajax.tpl');
    }
}
