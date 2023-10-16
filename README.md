# Pow Captcha for PrestaShop

This plugin allows you to validate the contact form using Pow Captcha.

Installation
---

```sh
composer require aeyoll/pow_captcha
```

Usage
---

In the contact form template, add the following above the submit button:

```
{hook h='displayBeforeContactFormSubmit' m='pow_captcha' id='contactform' form='#contact-form'}
```

- `ìd` must be an unique id for the captcha
- `form` must be a css selector for the form containing the captcha
