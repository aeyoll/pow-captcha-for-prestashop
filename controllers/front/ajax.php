<?php

class Pow_CaptchaAjaxModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        $powCaptchaApiUrl = Configuration::get('POW_CAPTCHA_API_URL');
        $this->context->smarty->assign('powCaptchaApiUrl', $powCaptchaApiUrl);
        $this->setTemplate('module:pow_captcha/views/templates/front/ajax.tpl');
    }
}
