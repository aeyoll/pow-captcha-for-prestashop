<input type="hidden" name="challenge" value="{$challenge}" />
<input type="hidden" name="nonce" />

<div class="captcha-container"
  data-sqr-captcha-url="{$powCaptchaApiUrl}"
  data-sqr-captcha-challenge="{$challenge}"
  data-sqr-captcha-callback="myCaptchaCallback">
</div>
