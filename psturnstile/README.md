# psturnstile — Cloudflare Turnstile for PrestaShop 9

Protects PrestaShop front-office forms (registration, login, contact, and any
custom form) with [Cloudflare Turnstile](https://developers.cloudflare.com/turnstile/).

- Server-side token verification against `https://challenges.cloudflare.com/turnstile/v0/siteverify`.
- Registration protection works out of the box (widget injected through the
  `displayCustomerAccountForm` hook, rendered by both official themes inside
  the customer form).
- Login / contact / custom form protection is enforced server-side by the
  module, but **the widget itself must be placed in your theme templates by
  you** — the module deliberately does not patch or auto-inject into
  theme-specific markup.

## Requirements

- PrestaShop 9.0+
- PHP 8.1+
- A Turnstile site key + secret key from the Cloudflare dashboard

## Installation

1. Copy the `psturnstile` folder into `modules/`.
2. Run `composer dump-autoload -o` inside `modules/psturnstile` if `vendor/`
   is not shipped.
3. Install the module from the Back Office (Module Manager).
4. Open the module configuration page and enter your site key and secret key.

The module is inactive (renders nothing, blocks nothing) until **both** keys
are configured.

## Configuration

| Setting | Meaning |
|---|---|
| Site key / Secret key | Cloudflare Turnstile credentials. The stored secret is never echoed back to the browser; leaving the field empty on save keeps the current secret. |
| Protect customer registration | Validates account creation on the registration page, the authentication page, and during checkout. Widget injected automatically. |
| Protect customer login | Validates `submitLogin` on the authentication page. **Requires a theme snippet (below).** |
| Protect contact form | Validates `submitMessage` on the contact page. **Requires a theme snippet (below).** |
| Fail open | If the Cloudflare verification API is unreachable, accept the submission instead of rejecting it. |
| Load Cloudflare api.js automatically | Registers `https://challenges.cloudflare.com/turnstile/v0/api.js` on pages matched by an active rule. Disable if your theme loads it itself. |
| Widget theme / size | Passed to the widget as `data-theme` / `data-size`. |
| Custom form rules (JSON) | Additional protected forms, see below. |

> **Warning:** enabling login or contact protection **before** adding the
> widget snippet to your theme will block those forms for all visitors,
> because submissions without a token are rejected server-side.

## Displaying the widget in your theme

The module exposes the widget two equivalent ways. Place either snippet
**inside the `<form>` element** you want to protect:

```smarty
{* Recommended: widget invocation *}
{widget name='psturnstile'}
```

```smarty
{* Equivalent: custom display hook *}
{hook h='displayPsTurnstileWidget'}
```

Both render (only when the module is fully configured):

```html
<div class="psturnstile-widget cf-turnstile"
     data-sitekey="..." data-theme="auto" data-size="normal"
     data-response-field-name="cf-turnstile-response"></div>
```

Cloudflare's `api.js` finds the `cf-turnstile` container, renders the
challenge, and adds a hidden `cf-turnstile-response` input to the surrounding
form. The module reads and verifies that token server-side.

### Login form (classic theme example)

Override `templates/customer/_partials/login-form.tpl` in your child theme and
add the widget before the submit section:

```smarty
{extends file='parent:customer/_partials/login-form.tpl'}

{block name='login_form_fields' append}
  {widget name='psturnstile'}
{/block}
```

(If your theme's block names differ, simply paste `{widget name='psturnstile'}`
inside the `<form id="login-form">` element of the copied template.)

### Contact form (classic theme example)

Override `modules/contactform/views/templates/widget/contactform.tpl` (theme
path: `themes/<yourtheme>/modules/contactform/views/templates/widget/contactform.tpl`)
and add inside the form, before the footer/submit button:

```smarty
{widget name='psturnstile'}
```

### Any other form

Add `{widget name='psturnstile'}` inside the form, then add a custom rule (see
below) so the submission is verified and `api.js` is loaded on that page.

## Custom rules

The configuration page accepts a JSON array. Each rule describes one protected
form:

| Field | Required | Meaning |
|---|---|---|
| `name` | no | Label for your own reference. |
| `enabled` | no (default `true`) | Toggle the rule without deleting it. |
| `submit_parameter` | **yes** | The POST parameter that identifies the form submission (e.g. `submitNewsletter`). On failure the module removes this parameter so the controller skips the action. |
| `controller` | no | Match the controller short name (`controller_name`, lowercase compare). |
| `php_self` | no | Match the front controller's `php_self` (e.g. `index`, `cms`, `module-mymodule-myaction`). |
| `uri_contains` | no | Substring match against the request URI. |

All provided context fields must match (logical AND). A rule with **no**
context field still validates the submission wherever `submit_parameter` is
posted, but cannot trigger automatic `api.js` loading (the page cannot be
predicted) — give it a context if you rely on automatic script loading.

### Examples

Protect the newsletter signup block on the home page:

```json
[
  {
    "name": "Newsletter (homepage)",
    "enabled": true,
    "php_self": "index",
    "submit_parameter": "submitNewsletter"
  }
]
```

Protect a custom module's front controller form plus a CMS-page form:

```json
[
  {
    "name": "Quote request",
    "enabled": true,
    "php_self": "module-myquotes-request",
    "submit_parameter": "submitQuoteRequest"
  },
  {
    "name": "B2B signup landing page",
    "enabled": true,
    "uri_contains": "/content/b2b-signup",
    "submit_parameter": "submitB2bSignup"
  }
]
```

> Note: other modules may process their own submit parameters in early hooks.
> The module neutralizes the submit parameter during
> `actionFrontControllerInitAfter`; modules that consume their POST data even
> earlier cannot be reliably blocked this way.

## How enforcement works

1. `actionFrontControllerInitAfter` runs before the controller's
   `postProcess()`.
2. If an active rule matches the request and the `cf-turnstile-response` token
   is missing or fails Cloudflare verification, the module appends an error
   notification and unsets the submit parameter, so the protected action never
   executes.
3. Tokens are single-use; verification results are cached per request to avoid
   double `siteverify` calls.

Registration is covered by the same mechanism via the always-present hidden
`submitCreate` field of the customer form. The identity (profile edit) page is
deliberately excluded so logged-in customers are not challenged.

## Uninstall

Uninstalling removes all `PSTURNSTILE_*` configuration values.
