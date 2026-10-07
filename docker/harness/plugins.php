<?php

/**
 * Every gateway package: its slug (omniguard/<slug>, github.com/glitchr-studio/omniguard-<slug>),
 * its tests' namespace and its factory class.
 */
return [
    'altcha' => ['Omniguard\\Altcha\\Tests\\', 'Omniguard\\Altcha\\AltchaGatewayFactory'],
    'turnstile' => ['Omniguard\\Turnstile\\Tests\\', 'Omniguard\\Turnstile\\TurnstileGatewayFactory'],
    'recaptcha' => ['Omniguard\\Recaptcha\\Tests\\', 'Omniguard\\Recaptcha\\RecaptchaGatewayFactory'],
    'akismet' => ['Omniguard\\Akismet\\Tests\\', 'Omniguard\\Akismet\\AkismetGatewayFactory'],
    'stopforumspam' => ['Omniguard\\Stopforumspam\\Tests\\', 'Omniguard\\Stopforumspam\\StopforumspamGatewayFactory'],
    'disposable' => ['Omniguard\\Disposable\\Tests\\', 'Omniguard\\Disposable\\DisposableGatewayFactory'],
];
