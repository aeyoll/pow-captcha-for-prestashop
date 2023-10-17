{* <style>
.sqr-captcha-hidden {
  display: none !important;
}
</style> *}

<script async defer src="{$powCaptchaJavascriptUrl}"></script>
<script async defer>
  const url = "{$link->getModuleLink('pow_captcha', 'ajax')}";
  const selector = '.pow-captcha-placeholder';

  document.addEventListener('DOMContentLoaded', function() {
    const captchas = document.querySelectorAll(selector);
    const fetchPromises = [];

    [].forEach.call(captchas, function(captcha) {
      const id = captcha.dataset.id;

      const params = new URLSearchParams({
        id,
      });

      const fullUrl = url + '?' + params.toString();

      const fetchPromise = fetch(fullUrl)
        .then(response => response.text())
        .then(html => {
          captcha.innerHTML = html;
        })
        .catch(error => {
          console.error('Error:', error);
        });

      fetchPromises.push(fetchPromise);
    });

    // Wait for all fetch requests to complete
    Promise
      .all(fetchPromises)
      .then(() => {
        window.sqrCaptchaInit();
      });
  });
</script>
