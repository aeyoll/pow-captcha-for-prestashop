<style>
.sqr-captcha-hidden {
  display: none !important;
}
</style>

<script async defer src="{$powCaptchaJavascriptUrl}"></script>
<script async defer>
  const url = "{$link->getModuleLink('pow_captcha', 'ajax')}";
  const selector = '.pow-captcha-placeholder';

  let captchaData = null;
  let solvedNonce = null;
  let captchaFetchPromise = null;

  // When the captcha challenge is resolved, insert the nonce to each form
  window.myCaptchaCallback = (nonce) => {
    solvedNonce = nonce;
    Array.from(document.querySelectorAll("input[name='nonce']")).forEach(e => e.value = nonce);
    Array.from(document.querySelectorAll("input[type='submit']")).forEach(e => e.disabled = false);
    Array.from(document.querySelectorAll("button[type='submit']")).forEach(e => e.disabled = false);
  };

  function buildCaptchaMarkup(challenge, apiUrl, nonce) {
    const challengeInput = document.createElement('input');
    challengeInput.type = 'hidden';
    challengeInput.name = 'challenge';
    challengeInput.value = challenge;

    const nonceInput = document.createElement('input');
    nonceInput.type = 'hidden';
    nonceInput.name = 'nonce';
    nonceInput.value = nonce || '';

    const fragment = document.createDocumentFragment();
    fragment.appendChild(challengeInput);
    fragment.appendChild(nonceInput);

    // Already solved: hidden fields only, no widget
    if (nonce) {
      return fragment;
    }

    const container = document.createElement('div');
    container.className = 'captcha-container';
    container.dataset.sqrCaptchaUrl = apiUrl;
    container.dataset.sqrCaptchaChallenge = challenge;
    container.dataset.sqrCaptchaCallback = 'myCaptchaCallback';
    fragment.appendChild(container);

    return fragment;
  }

  function uninitializedPlaceholders() {
    return Array.from(document.querySelectorAll(selector)).filter(
      (captcha) => !captcha.querySelector('input[name="challenge"]')
    );
  }

  function setFormSubmitsDisabled(form, disabled) {
    form.querySelectorAll('input[type="submit"], button[type="submit"]').forEach((button) => {
      button.disabled = disabled;
    });
  }

  // captcha.js is async; poll briefly if init is not ready yet
  function initSqrCaptcha() {
    if (typeof window.sqrCaptchaInit === 'function') {
      window.sqrCaptchaInit();

      return;
    }

    const started = Date.now();
    const timer = setInterval(() => {
      if (typeof window.sqrCaptchaInit === 'function') {
        clearInterval(timer);
        window.sqrCaptchaInit();
      } else if (Date.now() - started > 10000) {
        clearInterval(timer);
      }
    }, 50);
  }

  // Server consumes challenge on submit; drop client cache so a later form cannot reuse it
  function invalidateCaptchaAfterSubmit(form) {
    solvedNonce = null;
    captchaData = null;
    captchaFetchPromise = null;
    // Defer wipe so this submit still includes challenge/nonce in the POST body
    setTimeout(() => {
      form.querySelectorAll(selector).forEach((placeholder) => {
        placeholder.innerHTML = '';
      });
      setFormSubmitsDisabled(form, true);
    }, 0);
  }

  function onCaptchaFormSubmit(event) {
    const form = event.currentTarget;
    const submitButtons = form.querySelectorAll('input[type="submit"], button[type="submit"]');

    submitButtons.forEach((button) => {
      // Create an hidden input with the same name and value as the submit button
      // If we don't do that, since the submit button is disabled, the form will not be
      // processed.
      const hiddenInput = document.createElement('input');
      hiddenInput.type = 'hidden';
      hiddenInput.name = button.name;
      hiddenInput.value = button.value;
      form.appendChild(hiddenInput);

      button.disabled = true;
    });

    invalidateCaptchaAfterSubmit(form);
  }

  async function fetchAndInitCaptcha() {
    const captchas = uninitializedPlaceholders();

    if (captchas.length <= 0) {
      return;
    }

    if (!captchaData) {
      if (!captchaFetchPromise) {
        captchaFetchPromise = fetch(url)
          .then((response) => response.json())
          .then((data) => {
            if (data && data.challenge && data.apiUrl) {
              captchaData = data;
            } else {
              captchaFetchPromise = null;
            }
          })
          .catch((error) => {
            console.error('Error:', error);
            captchaFetchPromise = null;
          });
      }

      await captchaFetchPromise;
    }

    if (!captchaData) {
      return;
    }

    // Re-query: another caller may have filled placeholders while we awaited
    uninitializedPlaceholders().forEach((captcha) => {
      while (captcha.firstChild) {
        captcha.removeChild(captcha.firstChild);
      }
      captcha.appendChild(buildCaptchaMarkup(captchaData.challenge, captchaData.apiUrl, solvedNonce));
    });

    if (solvedNonce) {
      document.querySelectorAll(selector).forEach((placeholder) => {
        const form = placeholder.closest('form');

        if (form) {
          setFormSubmitsDisabled(form, false);
        }
      });

      return;
    }

    initSqrCaptcha();
  }

  function bindCaptchaForm(form) {
    if (!form) {
      return;
    }

    // Always refresh submit state (form element may survive an AJAX HTML swap)
    setFormSubmitsDisabled(form, !solvedNonce);

    if (!form.dataset.powCaptchaBound) {
      form.dataset.powCaptchaBound = '1';
      // focusin bubbles: covers inputs added after bind / after HTML swap
      form.addEventListener('focusin', fetchAndInitCaptcha);
      form.addEventListener('submit', onCaptchaFormSubmit);
    }

    if (solvedNonce) {
      fetchAndInitCaptcha();
    }
  }

  function bindCaptchaPlaceholders(root) {
    if (!root || root.nodeType !== 1) {
      return;
    }

    if (root.matches && root.matches(selector)) {
      bindCaptchaForm(root.closest('form'));
    }

    if (root.querySelectorAll) {
      Array.from(root.querySelectorAll(selector)).forEach((placeholder) => {
        bindCaptchaForm(placeholder.closest('form'));
      });
    }
  }

  function boot() {
    bindCaptchaPlaceholders(document.documentElement);

    new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
          bindCaptchaPlaceholders(node);
        });
      });
    }).observe(document.documentElement, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
</script>

