<?php

namespace App\Command;

use App\Service\EvaluationRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * CLI command running the 24 synthetic B2B contract evaluation scenarios and exporting eval_report.json (#19)
 */
#[AsCommand(
    name: 'app:eval-benchmark',
    description: 'Runs the RAG evaluation benchmark suite and verifies Precision >= 85% and Recall >= 80%',
)]
final class EvalBenchmarkCommand extends Command
{
    public function __construct(
        private EvaluationRunner $evaluationRunner,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'output',
            'o',
            InputOption::VALUE_OPTIONAL,
            'Path to write the JSON evaluation report',
            'eval_report.json',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('PromiseGap — Automated RAG Discrepancy Benchmark Suite');

        $report = $this->evaluationRunner->runSuite();

        $rows = [];
        foreach ($report['perCategory'] as $category => $metrics) {
            $rows[] = [
                $category,
                (string) $metrics['tp'],
                (string) $metrics['fp'],
                (string) $metrics['fn'],
                sprintf('%.1f%%', $metrics['precision'] * 100),
                sprintf('%.1f%%', $metrics['recall'] * 100),
                sprintf('%.1f%%', $metrics['f1'] * 100),
            ];
        }

        $rows[] = [
            'MACRO AVERAGE',
            '-',
            '-',
            '-',
            sprintf('%.1f%%', $report['macroPrecision'] * 100),
            sprintf('%.1f%%', $report['macroRecall'] * 100),
            sprintf('%.1f%%', $report['macroF1'] * 100),
        ];

        $io->section(sprintf('Category Metrics (%d Ground-Truth Scenarios)', $report['totalScenarios']));
        $io->table(['Category', 'TP', 'FP', 'FN', 'Precision', 'Recall', 'F1-Score'], $rows);

        $outputOption = (string) $input->getOption('output');
        $reportPath = str_starts_with($outputOption, '/')
            ? $outputOption
            : ($this->projectDir . '/' . ltrim($outputOption, '/'));

        file_put_contents(
            $reportPath,
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        );

        $io->writeln(sprintf('Saved detailed evaluation report to <info>%s</info>', $reportPath));

        if (!$report['passesAcceptanceCriteria']) {
            $io->error(sprintf(
                'Benchmark failed thresholds: Precision %.1f%% (min 85.0%%), Recall %.1f%% (min 80.0%%).',
                $report['macroPrecision'] * 100,
                $report['macroRecall'] * 100,
            ));

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Benchmark passed! Precision: %.1f%% (>= 85%%), Recall: %.1f%% (>= 80%%), F1: %.1f%% (%d/%d scenarios matched).',
            $report['macroPrecision'] * 100,
            $report['macroRecall'] * 100,
            $report['macroF1'] * 100,
            $report['correctPredictions'],
            $report['totalScenarios'],
        ));

        return Command::SUCCESS;
    }
}
