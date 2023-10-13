<div id="pow-captcha-placeholder"></div>

<script>
  window.myCaptchaCallback = (nonce) => {
    document.querySelector("form input[name='nonce']").value = nonce;
    document.querySelector("form input[type='submit']").disabled = false;
  };

  const url = "{$link->getModuleLink('pow_captcha', 'ajax')}";
  const elementId = 'pow-captcha-placeholder';

  fetch(url)
  .then(response => response.text())
  .then(html => {
    const element = document.getElementById(elementId);
    element.innerHTML = html;
    window.sqrCaptchaInit();
  })
  .catch(error => {
    console.error('Error:', error);
  });
</script>
