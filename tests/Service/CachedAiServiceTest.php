<?php

namespace App\Tests\Service;

use App\Service\CachedAiService;
use App\Service\MetaAiClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Validates deterministic caching, cache bypass and credit budget tracking in CachedAiService
 */
class CachedAiServiceTest extends TestCase
{
    /**
     * Verifies that identical requests hit the cache without calling the API twice
     */
    public function testIdenticalPromptsAreServedFromCacheAndForceRefreshBypassesIt(): void
    {
        $callCount = 0;
        $httpClient = new MockHttpClient(function () use (&$callCount): MockResponse {
            ++$callCount;

            return new MockResponse((string) json_encode([
                'model' => 'Muse-Spark',
                'choices' => [
                    ['message' => ['content' => sprintf('{"call":%d}', $callCount)]],
                ],
                'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
            ]));
        });

        $metaClient = new MetaAiClient($httpClient, new NullLogger(), apiKey: 'test-key');
        $cachePool = new ArrayAdapter();
        $service = new CachedAiService($metaClient, $cachePool, new NullLogger(), cacheEnabled: true, budgetCredits: 50.0);

        $first = $service->chat('Sys', 'User prompt');
        $this->assertFalse($first->cached);
        $this->assertSame('{"call":1}', $first->content);
        $this->assertSame(1, $callCount);

        $second = $service->chat('Sys', 'User prompt');
        $this->assertTrue($second->cached);
        $this->assertSame('{"call":1}', $second->content);
        $this->assertSame(1, $callCount);

        $refreshed = $service->chat('Sys', 'User prompt', ['force_refresh' => true]);
        $this->assertFalse($refreshed->cached);
        $this->assertSame('{"call":2}', $refreshed->content);
        $this->assertSame(2, $callCount);

        $budget = $service->getBudgetSummary();
        $this->assertSame(300, $budget['totalTokens']);
        $this->assertLessThan(50.0, $budget['remainingCredits']);
    }

    /**
     * Verifies that cache keys are deterministic regardless of option ordering
     */
    public function testComputeCacheKeyIsDeterministic(): void
    {
        $metaClient = new MetaAiClient(new MockHttpClient(), new NullLogger());
        $service = new CachedAiService($metaClient, new ArrayAdapter(), new NullLogger());

        $keyA = $service->computeCacheKey('System', 'User', ['temperature' => 0.1, 'max_tokens' => 500]);
        $keyB = $service->computeCacheKey('System', 'User', ['max_tokens' => 500, 'temperature' => 0.1, 'force_refresh' => true]);

        $this->assertSame($keyA, $keyB);
    }
}
