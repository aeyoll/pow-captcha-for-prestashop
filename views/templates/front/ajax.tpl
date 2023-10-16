
<input type="hidden" name="challenge" value="{$challenge}" />
<input type="hidden" name="nonce" />
<script>
  window.myCaptchaCallback{$id} = (nonce) => {
    document.querySelector("{$form} input[name='nonce']").value = nonce;
    document.querySelector("{$form} input[type='submit']").disabled = false;
  };
</script>

<div class="captcha-container"
  data-sqr-captcha-url="{$powCaptchaApiUrl}"
  data-sqr-captcha-challenge="{$challenge}"
  data-sqr-captcha-callback="myCaptchaCallback{$id}">
</div>
