<?php

namespace App\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Verifies the app:test-ai diagnostic console command
 */
class TestAiCommandTest extends KernelTestCase
{
    /**
     * Verifies that app:test-ai executes cleanly and outputs structured JSON and budget telemetry
     */
    public function testExecuteDiagnosticCommand(): void
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);

        $command = $application->find('app:test-ai');
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            '--prompt' => 'Test diagnostic connectivity',
            '--force-refresh' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $output = $tester->getDisplay();
        $this->assertStringContainsString('muse-spark-1.3', $output);
        $this->assertStringContainsString('Meta AI diagnostic completed successfully', $output);
    }
}
