# Pow Captcha for PrestaShop

This plugin allows you to validate the contact form using Pow Captcha. Tested on PrestaShop 8.1+.

Installation
---

```sh
composer require aeyoll/pow_captcha
```

Usage
---

In the contact form template, add the following above the submit button:

```
{hook h='displayBeforeContactFormSubmit' m='pow_captcha'}
```
