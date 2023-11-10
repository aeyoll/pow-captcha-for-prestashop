<style>
.sqr-captcha-hidden {
  display: block !important;
}
</style>

<script async defer>
  window.myCaptchaCallback = (nonce) => {
    Array.from(document.querySelectorAll("input[name='nonce']")).forEach(e => e.value = nonce);
    Array.from(document.querySelectorAll("input[type='submit']")).forEach(e => e.disabled = false);
  };
</script>

<script async defer src="{$powCaptchaJavascriptUrl}"></script>
<script async defer>
  const url = "{$link->getModuleLink('pow_captcha', 'ajax')}";
  const selector = '.pow-captcha-placeholder';

  document.addEventListener('DOMContentLoaded', async function() {
    const captchas = Array.from(document.querySelectorAll(selector));

    if (captchas.length <= 0) {
      return;
    }

    let captchaHtml = '';

    await fetch(url)
      .then(response => response.text())
      .then(html => {
        captchaHtml = html;
      })
      .catch(error => {
        console.error('Error:', error);
      });

    captchas.forEach((captcha) => {
      captcha.innerHTML = captchaHtml;
    });

    window.sqrCaptchaInit();
  });
</script>
