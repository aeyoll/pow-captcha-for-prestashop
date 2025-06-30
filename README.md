# Pow Captcha for PrestaShop

This plugin allows you to validate the contact form using Pow Captcha. Tested on PrestaShop 1.6 to 8.1.

Requirements
---

PHP 7.1+ and php_curl is needed to use this module.

Installation
---

### PrestaShop 1.7+

For PrestaShop 1.7+, require the plugin with Composer using the following command:

```sh
composer require aeyoll/pow_captcha
```

### PrestaShop 1.6

For Prestashop 1.6, this minimum composer.json file is required at the root of your project.

```json
{
    "name": "project-name/project-name",
    "require": {
        "aeyoll/pow_captcha": "dev-main",
        "composer/installers": "^1.0.21"
    },
    "config": {
        "allow-plugins": {
            "composer/installers": true
        },
        "sort-packages": true
    },
    "minimum-stability": "dev"
}
```

Then, you need to override this Controller class (`classes/controller/Controller.php`), so the `actionControllerInitAfter` is called:

```php
/**
  * Initialize the page
  */
public function init()
{
    if (_PS_MODE_DEV_ && $this->controller_type == 'admin') {
        set_error_handler(array(__CLASS__, 'myErrorHandler'));
    }

    if (!defined('_PS_BASE_URL_')) {
        define('_PS_BASE_URL_', Tools::getShopDomain(true));
    }

    if (!defined('_PS_BASE_URL_SSL_')) {
        define('_PS_BASE_URL_SSL_', Tools::getShopDomainSsl(true));
    }

    // Override
    Hook::exec(
        'actionControllerInitAfter',
        [
            'controller' => $this,
        ]
    );
    // /Override
}
```

Usage
---

In each form template, add the following above the submit button:

```
{hook h='displayBeforeContactFormSubmit' m='pow_captcha'}
```

Configuration
---

You can override the default values for GetChallenge timeout and cache lifetime by defining the following constants in your `defines.inc.php` file:

To override GetChallenge timeout:

```php
<?php
define('POW_CAPTCHA_TIMEOUT', 10.0);
```

To override GetChallenge cache lifetime:

```php
define('POW_CAPTCHA_CACHE_LIFETIME', 3600);
```

Compatibility
---

### Registration

Add this in `themes/your_theme/templates/customer/_partials/customer-form.tpl`

```diff
  <footer class="row">
    <input type="hidden" name="submitCreate" value="1">

+   {hook h='displayBeforeContactFormSubmit' m='pow_captcha'}

    {block "form_buttons"}
      <div class="form-group text-center">
```

### Contactform

Add this in `themes/your_theme/modules/contactform/views/templates/widget/contactform.tpl`:

```diff
  <input type="hidden" name="token" value="{$token}" />
  <input type="hidden" name="id_contact" value="2" />

+ {hook h='displayBeforeContactFormSubmit' m='pow_captcha'}

  <div class="offset-md-3">
    <input class="btn btn-primary" type="submit" name="submitMessage" value="{l s='Send' d='Shop.Theme.Actions'}">
  </div>
```

In contactform.php, add this override:

```php
} elseif ($url !== ''
    || empty($serverToken)
    || $clientToken !== $serverToken
    || $clientTokenTTL < time()
) {
    $this->context->controller->errors[] = $this->trans(
        'An error occurred while sending the message, please try again.',
        [],
        'Modules.Contactform.Shop'
    );
    $this->createNewToken();
// OVERRIDE: Add this elseif to prevent the message from being logged in the SAV
} elseif (count($this->context->controller->errors) > 0) {
    // do nothing, usefull for captcha
// OVERRIDE
} else {
    $customer = $this->context->customer;

    if (!$customer->id) {
        $customer->getByEmail($from);
    }
```

### Ps_Emailsubscription

Add this in `override/modules/ps_emailsubscription/ps_emailsubscription.php`:

```php
<?php

class Ps_EmailsubscriptionOverride extends Ps_Emailsubscription
{
    public function getWidgetVariables($hookName = null, array $configuration = [])
    {
        $variables = [];
        $variables['value'] = '';
        $variables['msg'] = '';
        $variables['conditions'] = Configuration::get('NW_CONDITIONS', $this->context->language->id);

        if (Tools::isSubmit('submitNewsletter')) {
            $this->error = $this->valid = false;

            // OVERRIDE: check errors from captcha
            $context = Context::getContext();
            if ($context->controller->errors) {
                $this->error = $context->controller->errors[0];
            } else {
                $this->newsletterRegistration($hookName);
            }
            /// /OVERRIDE

            /* @phpstan-ignore-next-line */
            if ($this->error) {
                $variables['value'] = Tools::getValue('email', '');
                $variables['msg'] = $this->error;
                $variables['nw_error'] = true;
            } elseif ($this->valid) { /* @phpstan-ignore-line */
                $variables['value'] = Tools::getValue('email', '');
                $variables['msg'] = $this->valid;
                $variables['nw_error'] = false;
            }
        }

        return $variables;
    }
}
```
