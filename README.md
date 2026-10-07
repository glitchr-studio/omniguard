# glitchr/omniguard

One contract for guarding a form, whoever answers. Three questions, and a gateway answers the one
that concerns it:

- **is this token valid?** - a captcha: ALTCHA, Cloudflare Turnstile, Google reCAPTCHA;
- **is this content spam?** - a classifier: Akismet;
- **are this address, this e-mail, this name known for abuse?** - a list: StopForumSpam, the
  disposable e-mail domains.

```php
$widget = $registry->challenge('forms')->widget('contact');      // what the page shows: script, element, field
echo $widget->html();                                             // inside the <form>

$verdict = $registry->challenge('forms')->verify(Attempt::fromPost($_POST, $widget, $ip));
$verdict->passed;                                                 // false: $verdict->reasons - missing, invalid, duplicate...

$registry->classifier('comments')->classify($submission)->isSpam();
$registry->reputation('emails')->lookup(new Identity($ip, $email))->known;
```

This package holds the contract (`ChallengeInterface`, `ClassifierInterface`,
`ReputationInterface`, `GatewayFactory`, `Registry`), the models (`Widget`, `Attempt`, `Verdict`,
`Submission`, `Classification`, `Identity`, `Reputation`, `Capabilities`), the store of spent
tokens (`ReplayStoreInterface`), `Testing\FixedGateway` for an application's tests, and a bridge
for Symfony. It needs no framework: it requires PHP and `symfony/http-client-contracts` (the
interfaces, no client). Each gateway is a package of its own:

| Package | It answers | Third party | Cookies |
|---|---|---|---|
| [`omniguard/altcha`](https://github.com/glitchr-studio/omniguard-altcha) | is this token valid: a proof of work the site issues and checks itself | none (the widget's script: yours, or jsDelivr's) | none |
| [`omniguard/turnstile`](https://github.com/glitchr-studio/omniguard-turnstile) | is this token valid: Cloudflare's widget, Cloudflare's siteverify | Cloudflare | none |
| [`omniguard/recaptcha`](https://github.com/glitchr-studio/omniguard-recaptcha) | is this token valid: v2 checkbox and invisible, v3 score, Enterprise | Google | `_GRECAPTCHA` |
| [`omniguard/akismet`](https://github.com/glitchr-studio/omniguard-akismet) | is this content spam: ham, spam, flagrant; reports back | Akismet (Automattic) | - |
| [`omniguard/stopforumspam`](https://github.com/glitchr-studio/omniguard-stopforumspam) | is this identity known: the public database, a threshold of the site's | StopForumSpam | - |
| [`omniguard/disposable`](https://github.com/glitchr-studio/omniguard-disposable) | is this e-mail's domain disposable: a free list shipped in the package | none | - |

## What a gateway is, and is not

A gateway answers. It does not decide what a refusal means for the form, nor what to do when its
provider does not answer: the application does. **A refusal is a `Verdict`, a `Classification`, a
`Reputation` - never an exception**; a provider that did not answer is an
`UnreachableException`, a key it refused an `InvalidKeyException`, and neither is ever taken
for "fine".

It is told strings - a token, an address, a text, a name - and never an account, an entity or a
request.

What a gateway cannot do is a `NotSupportedException`, and its `Capabilities` say so beforehand:
a captcha classifies nothing, a list without a key takes no report.

## Documentation

- [Installation and a first form](docs/installation.md)
- [Models](docs/models.md)
- [The three questions: the contracts, the gateways, writing one](docs/gateways.md)
- [Symfony: the bundle, the form field, the constraint, the route, Twig](docs/symfony.md)
- [Privacy: who sees what, cookies, consent](docs/privacy.md)
- [The Docker harness: the console, bare PHP](docs/harness.md)

## Plain PHP

```sh
composer require glitchr/omniguard omniguard/altcha omniguard/disposable
```

```php
use Omniguard\Altcha\AltchaGatewayFactory;
use Omniguard\Disposable\DisposableGatewayFactory;
use Omniguard\Registry;

$registry = new Registry([new AltchaGatewayFactory($spentTokens), new DisposableGatewayFactory()], [
    'forms' => ['factory' => 'altcha', 'options' => ['hmac_key' => getenv('ALTCHA_HMAC_KEY')]],
    'emails' => ['factory' => 'disposable'],
]);
```

No bundle, no container: a factory per gateway package, the registry built by hand.
[docs/installation.md](docs/installation.md) opens on a whole script that runs as it is.

## Symfony

`Omniguard\Bridge\Symfony\OmniguardBundle` does that wiring in a Symfony application, and adds a
form field, a constraint, the route of the ALTCHA challenge and a Twig function
([docs/symfony.md](docs/symfony.md)); none of their components is required by this package.

```yaml
omniguard:
    gateways:
        forms: { factory: altcha, options: { hmac_key: '%env(ALTCHA_HMAC_KEY)%' } }
        emails: { factory: disposable }
```

```php
$builder->add('captcha', ChallengeType::class, ['action' => 'contact']);
```

## Docker: every gateway, bare PHP

```sh
cd docker && cp .env.dist .env
docker compose run --rm omniguard gateways
docker compose run --rm omniguard bare          # plain PHP: no bundle, no container, and what PHP loaded
docker compose run --rm omniguard bare --live   # the same against the providers, with their testing keys
docker compose run --rm omniguard test
```

License: LGPL-3.0-or-later.
