<div
  class="pow-captcha-placeholder"
  data-form="{$pow_captcha_form}"
  data-id="{$pow_captcha_id}">
</div>

<script>
  window.myCaptchaCallback{$pow_captcha_id} = (nonce) => {
    document.querySelector("{$pow_captcha_form} input[name='nonce']").value = nonce;
    document.querySelector("{$pow_captcha_form} input[type='submit']").disabled = false;
  };
</script>
