<?php

namespace Omnishield\Harness;

use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use Omnishield\Altcha\AltchaGatewayFactory;
use Omnishield\ChallengeIssuerInterface;
use Omnishield\Exception\InvalidConfigException;
use Omnishield\Exception\OmnishieldException;
use Omnishield\Model\Attempt;
use Omnishield\Model\Identity;
use Omnishield\Model\Submission;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The console that asks every gateway: gateways, widget, solve, verify,
 * classify, lookup. The providers are called for real unless --recorded;
 * a gateway whose settings are not in .env runs on its example ones.
 */
final class Console
{
    public static function create(): Application
    {
        $gateway = new InputArgument('gateway', InputArgument::REQUIRED, 'A configured gateway: altcha, turnstile, recaptcha, akismet, stopforumspam, disposable, fixed');
        $recorded = new InputOption('recorded', null, InputOption::VALUE_NONE, 'The providers\' recorded answers instead of the providers');
        $ip = new InputOption('ip', null, InputOption::VALUE_REQUIRED, 'The visitor\'s address');
        $action = new InputOption('action', 'a', InputOption::VALUE_REQUIRED, 'What the form is for');

        $app = new Application('omnishield', '1.x');
        $app->addCommand(self::command('gateways', 'Which gateways are installed and configured, and what each answers', [$recorded], static fn ($in, $out, $g) => self::gateways($out, $g)));
        $app->addCommand(self::command('widget', 'What a page shows for a captcha: its parts (JSON) and its markup', [$gateway, $action, $recorded], static fn ($in, $out, $g) => self::widget($in, $out, $g)));
        $app->addCommand(self::command('solve', 'altcha: a challenge issued and solved here, as the widget would - the token to post (or to verify)', [$gateway, $action, $recorded], static fn ($in, $out, $g) => self::solve($in, $out, $g)));
        $app->addCommand(self::command('verify', 'Whether a token holds (JSON)', [$gateway, new InputArgument('token', InputArgument::REQUIRED, 'What the widget posted'), $ip, $action, new InputOption('hostname', null, InputOption::VALUE_REQUIRED, 'The host the widget must have been shown on'), $recorded], static fn ($in, $out, $g) => self::verify($in, $out, $g)));
        $app->addCommand(self::command('classify', 'Whether a text is spam (JSON)', [
            $gateway, new InputArgument('text', InputArgument::REQUIRED, 'What was written'), $ip,
            new InputOption('author', null, InputOption::VALUE_REQUIRED, 'The name given'),
            new InputOption('email', null, InputOption::VALUE_REQUIRED, 'The e-mail given'),
            new InputOption('type', null, InputOption::VALUE_REQUIRED, 'comment, contact-form, signup...', Submission::COMMENT),
            new InputOption('report', null, InputOption::VALUE_REQUIRED, 'Teach the classifier instead: spam or ham'),
            $recorded,
        ], static fn ($in, $out, $g) => self::classify($in, $out, $g)));
        $app->addCommand(self::command('lookup', 'What a list knows of an identity (JSON)', [$gateway, $ip, new InputOption('email', null, InputOption::VALUE_REQUIRED, 'An e-mail'), new InputOption('name', null, InputOption::VALUE_REQUIRED, 'A name'), $recorded], static fn ($in, $out, $g) => self::lookup($in, $out, $g)));

        return $app;
    }

    /** @param list<InputArgument|InputOption> $definition */
    private static function command(string $name, string $description, array $definition, \Closure $code): Command
    {
        $command = new Command($name);
        $command->setDescription($description)->setDefinition($definition);
        $command->setCode(static function (InputInterface $in, OutputInterface $out) use ($code): int {
            try {
                return $code($in, $out, new Gateways(!$in->getOption('recorded'))) ?? Command::SUCCESS;
            } catch (InvalidConfigException|\InvalidArgumentException|\ValueError $e) {
                $out->writeln('<error>'.$e->getMessage().'</error>');

                return Command::INVALID;
            } catch (OmnishieldException $e) {
                $out->writeln('<error>'.$e::class.': '.$e->getMessage().'</error>');

                return Command::FAILURE;
            }
        });

        return $command;
    }

    private static function gateways(OutputInterface $out, Gateways $gateways): void
    {
        $table = new Table($out);
        $table->setHeaders(['Gateway', 'Factory', 'Installed', 'Settings', 'Answers', 'Third party', 'Cookies', 'Scores', 'Reports', 'Reads']);
        foreach ($gateways->config as $name => $gateway) {
            $installed = isset($gateways->factories[$gateway['factory']]);
            $missing = $gateways->missing($gateway);
            $row = [$name, $gateway['factory'], $installed ? '<info>yes</info>' : '<comment>no</comment>', !$installed ? '' : ($missing ? '<comment>example (needs '.implode(', ', $missing).')</comment>' : ($gateway['needs'] ? '<info>.env</info>' : 'none needed'))];
            if ($installed) {
                $c = $gateways->registry->get($name)->capabilities();
                $yes = static fn (bool $v): string => $v ? 'yes' : 'no';
                $row = [...$row, implode(', ', $c->questions()), $yes($c->thirdParty), $yes($c->cookies), $yes($c->scores), $yes($c->reports), implode(', ', $c->reads)];
            }
            $table->addRow($row);
        }
        $table->render();
        $out->writeln('Factories installed: '.implode(', ', array_keys($gateways->factories)).'; providers: '.($gateways->live ? 'live' : 'recorded answers'));
    }

    private static function widget(InputInterface $in, OutputInterface $out, Gateways $gateways): void
    {
        $widget = $gateways->registry->challenge((string) $in->getArgument('gateway'))->widget($in->getOption('action'));
        $out->writeln(self::json(['field' => $widget->field, 'script' => $widget->script, 'tag' => $widget->tag, 'attributes' => $widget->attributes, 'site_key' => $widget->siteKey, 'action' => $widget->action, 'third_party' => $widget->thirdParty, 'cookies' => $widget->cookies, 'origins' => $widget->origins]));
        $out->writeln($widget->html(), OutputInterface::OUTPUT_RAW);
    }

    private static function solve(InputInterface $in, OutputInterface $out, Gateways $gateways): int
    {
        $name = (string) $in->getArgument('gateway');
        $gateway = $gateways->registry->challenge($name);
        if (!$gateway instanceof ChallengeIssuerInterface || !class_exists(Altcha::class)) {
            $out->writeln('<comment>Only a captcha the site issues itself (altcha) can be solved here; the others need a browser.</comment>');

            return Command::FAILURE;
        }
        $challenge = Challenge::fromArray($gateway->issue($in->getOption('action')));
        $algorithm = AltchaGatewayFactory::algorithm((string) ($gateways->registry->options($name)['algorithm'] ?? 'PBKDF2/SHA-256'));
        $solution = (new Altcha())->solveChallenge(new SolveChallengeOptions(challenge: $challenge, algorithm: $algorithm));
        if (null === $solution) {
            $out->writeln('<error>Not solved in time.</error>');

            return Command::FAILURE;
        }
        $out->writeln(\sprintf('<comment>Solved in %.3fs (counter %d).</comment>', $solution->time, $solution->counter), OutputInterface::VERBOSITY_VERBOSE);
        $out->writeln((new Payload($challenge, $solution))->toBase64());

        return Command::SUCCESS;
    }

    private static function verify(InputInterface $in, OutputInterface $out, Gateways $gateways): int
    {
        $verdict = $gateways->registry->challenge((string) $in->getArgument('gateway'))->verify(new Attempt((string) $in->getArgument('token'), $in->getOption('ip'), $in->getOption('action'), $in->getOption('hostname')));
        $out->writeln(self::json($verdict->toArray()));

        return $verdict->passed ? Command::SUCCESS : Command::FAILURE;
    }

    private static function classify(InputInterface $in, OutputInterface $out, Gateways $gateways): void
    {
        $classifier = $gateways->registry->classifier((string) $in->getArgument('gateway'));
        $submission = new Submission((string) $in->getArgument('text'), $in->getOption('author'), $in->getOption('email'), ip: $in->getOption('ip'), type: (string) $in->getOption('type'), date: new \DateTimeImmutable());
        if (null !== $report = $in->getOption('report')) {
            $classifier->report($submission, 'spam' === $report);
            $out->writeln(self::json(['reported' => 'spam' === $report ? 'spam' : 'ham']));

            return;
        }
        $classification = $classifier->classify($submission);
        $out->writeln(self::json(['label' => $classification->label->value, 'spam' => $classification->isSpam(), 'reasons' => $classification->reasons, 'data' => $classification->data]));
    }

    private static function lookup(InputInterface $in, OutputInterface $out, Gateways $gateways): void
    {
        $reputation = $gateways->registry->reputation((string) $in->getArgument('gateway'))->lookup(new Identity($in->getOption('ip'), $in->getOption('email'), $in->getOption('name')));
        $out->writeln(self::json($reputation->toArray()));
    }

    private static function json(mixed $data): string
    {
        return (string) json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }
}
