<script src="{$powCaptchaJavascriptUrl}"></script>
<script>
  const url = "{$link->getModuleLink('pow_captcha', 'ajax')}";
  const selector = '.pow-captcha-placeholder';

  document.addEventListener('DOMContentLoaded', function() {
    const captchas = document.querySelectorAll(selector);
    let count = 0;

    [].forEach.call(captchas, function(captcha) {
      const id = captcha.dataset.id;
      const form = captcha.dataset.form;

      const params = new URLSearchParams({
        id,
        form,
      });

      const fullUrl = url + '?' + params.toString();

      fetch(fullUrl)
      .then(response => response.text())
      .then(html => {
        captcha.innerHTML = html;
        count++;

        // Ensure sqrCaptchaInit is called only once
        if (count === captchas.length) {
          window.sqrCaptchaInit();
        }
      })
      .catch(error => {
        console.error('Error:', error);
      });
    });
  });
</script>
