<?php

namespace Omniguard\Tests\Bridge;

use Omniguard\Bridge\Symfony\Controller\ChallengeController;
use Omniguard\Bridge\Symfony\Form\ChallengeType;
use Omniguard\Bridge\Symfony\OmniguardBundle;
use Omniguard\Bridge\Symfony\Validator\PassesChallengeValidator;
use Omniguard\Bridge\Twig\OmniguardExtension;
use Omniguard\ChallengeInterface;
use Omniguard\GatewayInterface;
use Omniguard\Registry;
use Omniguard\Replay\CacheReplayStore;
use Omniguard\Replay\InMemoryReplayStore;
use Omniguard\Replay\ReplayStoreInterface;
use Omniguard\ReputationInterface;
use Omniguard\Testing\FixedGateway;
use Omniguard\Tests\StubFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OmniguardBundleTest extends TestCase
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
        $bundle = new OmniguardBundle();
        $container->registerExtension($bundle->getContainerExtension());
        $container->loadFromExtension('omniguard', $config);
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
        self::assertSame('forms', $container->getParameter('omniguard.challenge.gateway'), 'the first gateway is the default captcha');
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
        $container->registerExtension((new OmniguardBundle())->getContainerExtension());
        $container->loadFromExtension('omniguard', ['gateways' => ['forms' => ['factory' => 'stub', 'options' => ['secret' => 's']]], 'replay' => ['service' => 'app.spent_tokens']]);
        $container->compile();
        self::assertInstanceOf(AtomicStore::class, $container->get(Registry::class)->get('forms')->replays);
    }

    public function testTheFormTypeTheValidatorTheRouteAndTheTwigFunctionAreRegistered(): void
    {
        $container = $this->container(['gateways' => ['forms' => ['factory' => 'stub', 'options' => ['secret' => 's']]], 'challenge' => ['unreachable' => 'accept']], keepUnused: true);

        foreach ([ChallengeType::class => 'form.type', PassesChallengeValidator::class => 'validator.constraint_validator', ChallengeController::class => 'controller.service_arguments', OmniguardExtension::class => 'twig.extension'] as $id => $tag) {
            self::assertTrue($container->getDefinition($id)->hasTag($tag), $id);
        }
        self::assertTrue($container->getDefinition(PassesChallengeValidator::class)->getArgument(3), 'unreachable: accept');
    }

    public function testTheRouteServesAFreshChallengeAndNothingForAGatewayThatIssuesNone(): void
    {
        $container = $this->container(['gateways' => ['forms' => ['factory' => 'stub', 'options' => ['secret' => 's']], 'lists' => ['factory' => 'fixed']]]);
        $controller = $container->get(ChallengeController::class);

        $response = $controller('forms', Request::create('/omniguard/forms/challenge?action=contact'));
        self::assertSame(['token' => 'good-1', 'action' => 'contact'], json_decode((string) $response->getContent(), true));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertSame('good-2', json_decode((string) $controller('forms', Request::create('/'))->getContent(), true)['token'], 'never the same');

        $this->expectException(NotFoundHttpException::class);
        $controller('lists', Request::create('/omniguard/lists/challenge'));
    }

    public function testTwigIsToldWhereTheFormThemeIs(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new TwigConfigCatcher());
        $bundle = new OmniguardBundle();
        $container->registerExtension($bundle->getContainerExtension());
        $bundle->getContainerExtension()->prepend($container);

        $twig = $container->getExtensionConfig('twig')[0];
        self::assertSame(['@Omniguard/form.html.twig'], $twig['form_themes']);
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
