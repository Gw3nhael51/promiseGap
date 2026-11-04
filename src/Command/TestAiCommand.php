<?php

namespace App\Command;

use App\Service\CachedAiService;
use App\Service\MetaAiClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Diagnostic CLI command verifying Meta Muse Spark connectivity, structured JSON output and caching
 */
#[AsCommand(
    name: 'app:test-ai',
    description: 'Verifies Meta Muse Spark AI connectivity, JSON schema enforcement, and token caching',
)]
final class TestAiCommand extends Command
{
    /**
     * @param CachedAiService $cachedAiService Cached AI client service
     * @param MetaAiClient $metaAiClient Raw Meta AI client for configuration inspection
     */
    public function __construct(
        private CachedAiService $cachedAiService,
        private MetaAiClient $metaAiClient,
    ) {
        parent::__construct();
    }

    /**
     * Configures CLI options for prompt customization and cache bypass
     */
    protected function configure(): void
    {
        $this
            ->addOption('prompt', 'p', InputOption::VALUE_OPTIONAL, 'Custom diagnostic prompt to send', 'Verify Muse Spark readiness and return JSON status.')
            ->addOption('force-refresh', 'f', InputOption::VALUE_NONE, 'Bypass local AI cache and force a fresh model call');
    }

    /**
     * Runs the diagnostic request and prints model, cache and budget telemetry
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('PromiseGap — Meta Muse Spark AI Diagnostic');

        $forceRefresh = (bool) $input->getOption('force-refresh');
        $userPrompt = (string) $input->getOption('prompt');

        $mode = $this->metaAiClient->isConfigured() ? 'Live Meta API' : 'Local Deterministic Simulation (no API key set)';
        $io->definitionList(
            ['Model' => $this->metaAiClient->getModel()],
            ['Mode' => $mode],
            ['Force Refresh' => $forceRefresh ? 'Yes (bypass cache)' : 'No (use cache if available)'],
        );

        $schema = [
            'type' => 'object',
            'required' => ['status', 'model', 'summary'],
        ];

        $result = $this->cachedAiService->completeJson(
            'You are the PromiseGap diagnostic agent. Return a JSON health check object with keys: status, model, summary.',
            $userPrompt,
            $schema,
            ['force_refresh' => $forceRefresh],
        );

        $budget = $this->cachedAiService->getBudgetSummary();

        $io->section('Structured JSON Output');
        $io->writeln((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $io->section('Token & Credit Budget Telemetry');
        $io->table(
            ['Metric', 'Value'],
            [
                ['Cumulative Tokens', (string) $budget['totalTokens']],
                ['Estimated Spent Credits', sprintf('%.4f / %.2f', $budget['spentCredits'], $budget['budgetCredits'])],
                ['Estimated Remaining Credits', sprintf('%.4f', $budget['remainingCredits'])],
            ],
        );

        $io->success('Meta AI diagnostic completed successfully.');

        return Command::SUCCESS;
    }
}
