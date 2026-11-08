(function () {
  let resolveScript;
  window.skalingaTurnstileReady = new Promise((resolve, reject) => {
    resolveScript = resolve;
  });
  window.skalingaTurnstileLoaded = () => resolveScript(true);
  window.skalingaTurnstileLoadFailed = () => resolveScript(false);

  let configPromise;
  window.prepareTurnstileForm = async (form, action, button, message) => {
    let widgetId = null;
    let token = '';
    let expiresTimer = null;
    let verificationEnabled = false;
    const field = form.querySelector('.turnstile-field');
    const slot = field.querySelector('.turnstile-slot');

    const setMessage = text => {
      message.textContent = text;
    };
    const reset = () => {
      if (!verificationEnabled) {
        return;
      }
      token = '';
      if (expiresTimer !== null) {
        clearTimeout(expiresTimer);
        expiresTimer = null;
      }
      button.disabled = true;
      if (widgetId !== null && window.turnstile) {
        window.turnstile.reset(widgetId);
      }
      setMessage('Please complete the security verification.');
    };
    const challenge = {
      token: () => token,
      reset,
    };

    button.disabled = true;
    try {
      if (!configPromise) {
        configPromise = fetch('api/public-config.php', { cache: 'no-store' }).then(async response => {
          if (!response.ok) {
            throw new Error('Public configuration request failed.');
          }
          return response.json();
        });
      }
      const config = await configPromise;
      if (!config.turnstileEnabled) {
        field.hidden = true;
        button.disabled = false;
        return challenge;
      }
      verificationEnabled = true;
      if (!config.turnstileSiteKey) {
        throw new Error('Turnstile site key is not configured.');
      }

      const scriptReady = await window.skalingaTurnstileReady;
      if (!scriptReady) {
        throw new Error('Turnstile script failed to load.');
      }
      widgetId = window.turnstile.render(slot, {
        sitekey: config.turnstileSiteKey,
        theme: 'auto',
        action,
        size: 'normal',
        callback: value => {
          token = value;
          button.disabled = false;
          setMessage('Verification complete.');
          expiresTimer = setTimeout(reset, 4 * 60 * 1000);
        },
        'expired-callback': () => {
          token = '';
          button.disabled = true;
          setMessage('Verification expired. Please complete it again.');
        },
        'error-callback': () => {
          token = '';
          button.disabled = true;
          setMessage('Verification could not load. Please try again.');
        },
      });
    } catch (error) {
      console.error('[Turnstile] Unable to initialize verification.', error);
      setMessage('Verification is unavailable. Please refresh and try again.');
    }

    return challenge;
  };
})();
