<?php

namespace App\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Validates the automated RAG evaluation benchmark runner (#18, #19)
 */
class EvalBenchmarkCommandTest extends KernelTestCase
{
    /**
     * Verifies that all 24 benchmark scenarios run and exceed Precision >= 85% and Recall >= 80%
     */
    public function testEvalBenchmarkCommandExceedsThresholds(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $reportFile = sys_get_temp_dir() . '/eval_report_' . bin2hex(random_bytes(4)) . '.json';
        $command = $application->find('app:eval-benchmark');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['--output' => $reportFile]);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($reportFile);

        $data = json_decode((string) file_get_contents($reportFile), true);
        $this->assertIsArray($data);
        $this->assertSame(24, $data['totalScenarios']);
        $this->assertGreaterThanOrEqual(0.85, $data['macroPrecision']);
        $this->assertGreaterThanOrEqual(0.80, $data['macroRecall']);
        $this->assertTrue($data['passesAcceptanceCriteria']);

        @unlink($reportFile);
    }
}
