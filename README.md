# Pow Captcha for PrestaShop

This plugin allows you to validate the contact form using Pow Captcha. Tested on PrestaShop 1.6 to 8.1.

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

Then, you need to override this Controller class ()`classes/controller/Controller.php`), so the `actionControllerInitAfter` is called:

```diff
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

        Hook::exec(
            'actionControllerInitAfter',
            [
                'controller' => $this,
            ]
        );
    }
```

Usage
---

In each form template, add the following above the submit button:

```
{hook h='displayBeforeContactFormSubmit' m='pow_captcha'}
```
