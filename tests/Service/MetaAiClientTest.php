<?php

namespace App\Tests\Service;

use App\Service\MetaAiClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Validates MetaAiClient HTTP handling, retries, structured JSON parsing and local simulation
 */
class MetaAiClientTest extends TestCase
{
    /**
     * Verifies that live API requests parse chat completions and token usage
     */
    public function testChatWithLiveApiKeyParsesResponse(): void
    {
        $mockResponse = new MockResponse((string) json_encode([
            'model' => 'Muse-Spark',
            'choices' => [
                ['message' => ['content' => '{"status":"ok","model":"Muse-Spark","summary":"Ready"}']],
            ],
            'usage' => [
                'prompt_tokens' => 42,
                'completion_tokens' => 18,
            ],
        ]));

        $client = new MetaAiClient(
            new MockHttpClient([$mockResponse]),
            new NullLogger(),
            apiKey: 'live-secret-key',
            endpoint: 'https://api.llama.com/compat/v1',
            model: 'Muse-Spark',
        );

        $this->assertTrue($client->isConfigured());

        $response = $client->chat('System prompt', 'User prompt');
        $this->assertSame('Muse-Spark', $response->model);
        $this->assertSame(42, $response->promptTokens);
        $this->assertSame(18, $response->completionTokens);
        $this->assertSame(60, $response->totalTokens());
        $this->assertFalse($response->cached);
    }

    /**
     * Verifies that transient HTTP 500 errors trigger retry and succeed on subsequent attempt
     */
    public function testChatRetriesOnTransientServerError(): void
    {
        $responses = [
            new MockResponse('{"error":{"message":"Upstream overload"}}', ['http_code' => 503]),
            new MockResponse((string) json_encode([
                'model' => 'Muse-Spark',
                'choices' => [
                    ['message' => ['content' => '{"status":"recovered"}']],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ])),
        ];

        $client = new MetaAiClient(
            new MockHttpClient($responses),
            new NullLogger(),
            apiKey: 'live-secret-key',
            maxRetries: 2,
        );

        $result = $client->completeJson('System', 'User', ['required' => ['status']]);
        $this->assertSame('recovered', $result['status']);
    }

    /**
     * Verifies that markdown code fences around JSON are stripped and required schema keys are enforced
     */
    public function testDecodeAndValidateJsonStripsMarkdownAndChecksRequiredKeys(): void
    {
        $client = new MetaAiClient(new MockHttpClient(), new NullLogger());

        $parsed = $client->decodeAndValidateJson("```json\n{\"status\":\"ok\",\"count\":3}\n```", [
            'required' => ['status', 'count'],
        ]);

        $this->assertSame('ok', $parsed['status']);
        $this->assertSame(3, $parsed['count']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('missing required key "missingField"');
        $client->decodeAndValidateJson('{"status":"ok"}', ['required' => ['status', 'missingField']]);
    }

    /**
     * Verifies that local deterministic simulation works when no API key is configured
     */
    public function testLocalSimulationModeProducesValidJson(): void
    {
        $client = new MetaAiClient(new MockHttpClient(), new NullLogger(), apiKey: '');

        $this->assertFalse($client->isConfigured());

        $data = $client->completeJson(
            'Diagnostic JSON check',
            'Check health',
            ['required' => ['status', 'model', 'summary']],
        );

        $this->assertSame('ok', $data['status']);
        $this->assertSame('Muse-Spark', $data['model']);
    }
}
