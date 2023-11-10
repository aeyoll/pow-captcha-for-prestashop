<style>
.sqr-captcha-hidden {
  display: none !important;
}
</style>

<script async defer src="{$powCaptchaJavascriptUrl}"></script>
<script async defer>
  const url = "{$link->getModuleLink('pow_captcha', 'ajax')}";
  const selector = '.pow-captcha-placeholder';

  // When the captcha challenge is resolved, insert the nonce to each form
  window.myCaptchaCallback = (nonce) => {
    Array.from(document.querySelectorAll("input[name='nonce']")).forEach(e => e.value = nonce);
    Array.from(document.querySelectorAll("input[type='submit']")).forEach(e => e.disabled = false);
    Array.from(document.querySelectorAll("button[type='submit']")).forEach(e => e.disabled = false);
  };

  // When the page has finished loading
  document.addEventListener('DOMContentLoaded', async function() {
    const captchas = Array.from(document.querySelectorAll(selector));

    // If there's no captcha on the page, abort
    if (captchas.length <= 0) {
      return;
    }

    let captchaHtml = '';

    // Fetch captcha content from the API
    await fetch(url)
      .then(response => response.text())
      .then(html => {
        captchaHtml = html;
      })
      .catch(error => {
        console.error('Error:', error);
      });

    // Assign captcha content to each captcha on the page
    captchas.forEach((captcha) => {
      captcha.innerHTML = captchaHtml;
    });

    // Init the captcha
    window.sqrCaptchaInit();
  });
</script>
