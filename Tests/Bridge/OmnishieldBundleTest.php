<?php

namespace Omnishield\Tests\Bridge;

use Omnishield\Bridge\Symfony\Controller\ChallengeController;
use Omnishield\Bridge\Symfony\EventListener\ScriptListener;
use Omnishield\Bridge\Symfony\Form\ChallengeType;
use Omnishield\Bridge\Symfony\OmnishieldBundle;
use Omnishield\Bridge\Symfony\Validator\PassesChallengeValidator;
use Omnishield\Bridge\Twig\OmnishieldExtension;
use Omnishield\ChallengeInterface;
use Omnishield\GatewayInterface;
use Omnishield\Registry;
use Omnishield\Replay\CacheReplayStore;
use Omnishield\Replay\InMemoryReplayStore;
use Omnishield\Replay\ReplayStoreInterface;
use Omnishield\ReputationInterface;
use Omnishield\Testing\FixedGateway;
use Omnishield\Tests\StubFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OmnishieldBundleTest extends TestCase
{
    /** @param array<string, mixed> $config */
    private function container(array $config, bool $pool = true, bool $contact = false, bool $keepUnused = false): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('http_client', MockHttpClient::class)->setPublic(true);
        $container->setAlias(HttpClientInterface::class, 'http_client');
        $container->register('request_stack', RequestStack::class)->setPublic(true);
        if ($pool) {
            $container->register('cache.app', ArrayAdapter::class);
        }
        // An application's own gateway, autoconfigured and autowired.
        $container->register(StubFactory::class)->setAutoconfigured(true)->setAutowired(true);
        if ($contact) {
            $container->register(Contact::class)->setAutowired(true)->setPublic(true);
        }
        $bundle = new OmnishieldBundle();
        $container->registerExtension($bundle->getContainerExtension());
        $container->loadFromExtension('omnishield', $config);
        if ($keepUnused) {
            // What the bundle registers, before unused private services are removed.
            $container->getCompilerPassConfig()->setRemovingPasses([]);
            $container->getCompilerPassConfig()->setAfterRemovingPasses([]);
        }
        $container->compile();

        return $container;
    }

    public function testTheGatewaysConfiguredAreBuiltAndInjectableByNameAndQuestion(): void
    {
        $container = $this->container(['gateways' => [
            'forms' => ['factory' => 'stub', 'options' => ['secret' => 's3cret']],
            'lists' => ['factory' => 'fixed', 'options' => ['pass' => false]],
        ]], contact: true);

        $registry = $container->get(Registry::class);
        self::assertSame(['forms', 'lists'], array_keys($registry->all()));
        self::assertContains('fixed', $registry->factories(), 'the fixed gateway is always there, for tests');

        $contact = $container->get(Contact::class);
        self::assertSame($registry->get('forms'), $contact->forms);
        self::assertSame($registry->get('lists'), $contact->lists);
        self::assertSame($registry->get('lists'), $contact->gateway);
        self::assertInstanceOf(FixedGateway::class, $contact->lists);
        self::assertSame('forms', $container->getParameter('omnishield.challenge.gateway'), 'the first gateway is the default captcha');
    }

    public function testAFactoryIsGivenTheApplicationsStoreAndClient(): void
    {
        $container = $this->container(['gateways' => ['forms' => ['factory' => 'stub', 'options' => ['secret' => 's']]]]);
        $gateway = $container->get(Registry::class)->get('forms');

        self::assertInstanceOf(CacheReplayStore::class, $gateway->replays, 'spent tokens in cache.app');
        self::assertSame($container->get('http_client'), $gateway->http);
    }

    public function testWithoutACachePoolSpentTokensStayInMemoryAndAStoreOfTheApplicationsWins(): void
    {
        $gateway = $this->container(['gateways' => ['forms' => ['factory' => 'stub', 'options' => ['secret' => 's']]]], pool: false)->get(Registry::class)->get('forms');
        self::assertInstanceOf(InMemoryReplayStore::class, $gateway->replays);

        $container = new ContainerBuilder();
        $container->register('app.spent_tokens', AtomicStore::class);
        $container->register(StubFactory::class)->setAutoconfigured(true)->setAutowired(true);
        $container->registerExtension((new OmnishieldBundle())->getContainerExtension());
        $container->loadFromExtension('omnishield', ['gateways' => ['forms' => ['factory' => 'stub', 'options' => ['secret' => 's']]], 'replay' => ['service' => 'app.spent_tokens']]);
        $container->compile();
        self::assertInstanceOf(AtomicStore::class, $container->get(Registry::class)->get('forms')->replays);
    }

    public function testTheFormTypeTheValidatorTheRouteAndTheTwigFunctionAreRegistered(): void
    {
        $container = $this->container(['gateways' => ['forms' => ['factory' => 'stub', 'options' => ['secret' => 's']]], 'challenge' => ['unreachable' => 'accept']], keepUnused: true);

        foreach ([ChallengeType::class => 'form.type', PassesChallengeValidator::class => 'validator.constraint_validator', ChallengeController::class => 'controller.service_arguments', OmnishieldExtension::class => 'twig.extension'] as $id => $tag) {
            self::assertTrue($container->getDefinition($id)->hasTag($tag), $id);
        }
        self::assertTrue($container->getDefinition(PassesChallengeValidator::class)->getArgument(3), 'unreachable: accept');
    }

    public function testTheRouteServesAFreshChallengeAndNothingForAGatewayThatIssuesNone(): void
    {
        $container = $this->container(['gateways' => ['forms' => ['factory' => 'stub', 'options' => ['secret' => 's']], 'lists' => ['factory' => 'fixed']]]);
        $controller = $container->get(ChallengeController::class);

        $response = $controller('forms', Request::create('/omnishield/forms/challenge?action=contact'));
        self::assertSame(['token' => 'good-1', 'action' => 'contact'], json_decode((string) $response->getContent(), true));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertSame('good-2', json_decode((string) $controller('forms', Request::create('/'))->getContent(), true)['token'], 'never the same');

        $this->expectException(NotFoundHttpException::class);
        $controller('lists', Request::create('/omnishield/lists/challenge'));
    }

    public function testTheAltchaWidgetIsServedByTheSiteAndReachesNobody(): void
    {
        if (!class_exists(\Omnishield\Altcha\AltchaGatewayFactory::class)) {
            self::markTestSkipped('omnishield/altcha is not installed.');
        }
        $altcha = \Omnishield\Altcha\AltchaGatewayFactory::class;
        $container = $this->container(['gateways' => ['forms' => ['factory' => 'altcha', 'options' => ['hmac_key' => 'a-long-secret', 'cost' => 10]]]]);

        $widget = $container->get(Registry::class)->challenge('forms')->widget('contact');
        self::assertSame($altcha::SCRIPT_PATH, $widget->script, 'the site\'s own address');
        self::assertSame([], $widget->origins);
        self::assertFalse($widget->reachesOthers(), 'nobody else is reached: no consent to ask');
        self::assertStringStartsWith('<script src="/omnishield/altcha/', $widget->html());

        // The address answers with the package's file, cached for a year; nothing else is touched.
        $listener = $container->get(ScriptListener::class);
        $kernel = $this->createMock(\Symfony\Component\HttpKernel\HttpKernelInterface::class);
        $event = new \Symfony\Component\HttpKernel\Event\RequestEvent($kernel, Request::create($altcha::SCRIPT_PATH), \Symfony\Component\HttpKernel\HttpKernelInterface::MAIN_REQUEST);
        $listener($event);
        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringStartsWith('text/javascript', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        self::assertSame(realpath($altcha::SCRIPT_FILE), realpath($response->getFile()->getPathname()));
        $other = new \Symfony\Component\HttpKernel\Event\RequestEvent($kernel, Request::create('/contact'), \Symfony\Component\HttpKernel\HttpKernelInterface::MAIN_REQUEST);
        $listener($other);
        self::assertNull($other->getResponse());

        // A script the application names is its own; serve_scripts: false, the package's default (the CDN).
        $named = $this->container(['gateways' => ['forms' => ['factory' => 'altcha', 'options' => ['hmac_key' => 'k', 'script' => '/js/altcha.min.js']]]]);
        self::assertSame('/js/altcha.min.js', $named->get(Registry::class)->challenge('forms')->widget()->script);
        $cdn = $this->container(['serve_scripts' => false, 'gateways' => ['forms' => ['factory' => 'altcha', 'options' => ['hmac_key' => 'k']]]]);
        self::assertSame($altcha::SCRIPT, $cdn->get(Registry::class)->challenge('forms')->widget()->script);
        self::assertFalse($cdn->has(ScriptListener::class));
    }

    public function testTwigIsToldWhereTheFormThemeIs(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new TwigConfigCatcher());
        $bundle = new OmnishieldBundle();
        $container->registerExtension($bundle->getContainerExtension());
        $bundle->getContainerExtension()->prepend($container);

        $twig = $container->getExtensionConfig('twig')[0];
        self::assertSame(['@Omnishield/form.html.twig'], $twig['form_themes']);
        self::assertFileExists(array_key_first($twig['paths']).'/form.html.twig');
    }
}

final class Contact
{
    public function __construct(
        public readonly ChallengeInterface $forms,
        public readonly ReputationInterface $lists,
        #[\Symfony\Component\DependencyInjection\Attribute\Target('lists')] public readonly GatewayInterface $gateway,
    ) {
    }
}

final class AtomicStore implements ReplayStoreInterface
{
    public function spend(string $id, \DateTimeImmutable $until): bool
    {
        return true;
    }
}

/** Stands for TwigBundle's extension: the bundle prepends to whatever is registered as "twig". */
final class TwigConfigCatcher extends \Symfony\Component\DependencyInjection\Extension\Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
    }

    public function getAlias(): string
    {
        return 'twig';
    }
}
