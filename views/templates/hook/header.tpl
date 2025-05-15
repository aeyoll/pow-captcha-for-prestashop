<style>
.sqr-captcha-hidden {
  display: none !important;
}
</style>

<script async defer src="{$powCaptchaJavascriptUrl}"></script>
<script async defer>
  const url = "{$link->getModuleLink('pow_captcha', 'ajax')}";
  const selector = '.pow-captcha-placeholder';

  let captchaFetched = false;

  // When the captcha challenge is resolved, insert the nonce to each form
  window.myCaptchaCallback = (nonce) => {
    Array.from(document.querySelectorAll("input[name='nonce']")).forEach(e => e.value = nonce);
    Array.from(document.querySelectorAll("input[type='submit']")).forEach(e => e.disabled = false);
    Array.from(document.querySelectorAll("button[type='submit']")).forEach(e => e.disabled = false);
  };

  // Function to fetch and initialize captcha
  async function fetchAndInitCaptcha() {
    if (captchaFetched) {
      return;
    }

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
    captchaFetched = true;
  }

  // When the page has finished loading
  document.addEventListener('DOMContentLoaded', function() {
    // Find all forms containing captcha placeholders
    const captchaForms = Array.from(document.querySelectorAll(selector))
      .map(placeholder => placeholder.closest('form'))
      .filter(form => form !== null);

    if (captchaForms.length <= 0) {
      return;
    }

    // Add focus event listeners to all inputs in these forms
    captchaForms.forEach(form => {
      const inputs = form.querySelectorAll('input, textarea, select');
      inputs.forEach(input => {
        input.addEventListener('focus', fetchAndInitCaptcha);
      });
    });
  });
</script>
