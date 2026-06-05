<?php

use PrestaShop\Module\PowCaptcha\Service\PowCaptchaRateLimiter;
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

        $rateLimiter = new PowCaptchaRateLimiter();
        $ip = Tools::getRemoteAddr();

        if (!$rateLimiter->isAllowed($ip)) {
            header('HTTP/1.1 429 Too Many Requests');
            header('Content-Type: application/json');
            echo json_encode(['error' => 'rate_limit_exceeded']);
            exit;
        }

        $rateLimiter->recordRequest($ip);

        $powCaptchaApiUrl = Configuration::get('POW_CAPTCHA_API_URL');

        $pcs = new PowCaptchaService();
        $challenge = $pcs->getChallenge();

        /** @var Pow_Captcha|false $module */
        $module = Module::getInstanceByName('pow_captcha');
        if ($module && $challenge) {
            $module->issueChallenge($challenge);
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Content-Type: application/json');

        echo json_encode([
            'challenge' => $challenge,
            'apiUrl' => $powCaptchaApiUrl,
        ]);
        exit;
    }
}
