<?php

namespace App\Service;

use App\DTO\AiResponse;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Caching wrapper around MetaAiClient to protect the Meta API credit budget
 */
#[AsAlias(id: AiClientInterface::class)]
class CachedAiService implements AiClientInterface
{
    private const string BUDGET_CACHE_KEY = 'ai_token_usage_totals';
    private const float CREDITS_PER_THOUSAND_TOKENS = 0.002;

    /**
     * @param MetaAiClient $metaAiClient Underlying Meta Model API client
     * @param CacheItemPoolInterface $aiCache Dedicated PSR-6 cache pool for AI responses
     * @param LoggerInterface $logger Application logger for token and budget tracking
     * @param bool $cacheEnabled Global flag enabling or bypassing the cache
     * @param float $budgetCredits Total allocated Meta credit budget
     */
    public function __construct(
        private MetaAiClient $metaAiClient,
        #[Autowire(service: 'ai.cache')]
        private CacheItemPoolInterface $aiCache,
        private LoggerInterface $logger,
        #[Autowire('%app.ai.cache_enabled%')]
        private bool $cacheEnabled = true,
        #[Autowire('%app.ai.budget_credits%')]
        private float $budgetCredits = 50.0,
    ) {
    }

    /**
     * Returns the deterministic SHA-256 cache key for a prompt and parameter set
     *
     * @param string $systemPrompt System instructions
     * @param string $userPrompt User payload
     * @param array<string, mixed> $options Model parameters
     * @return string Deterministic cache key
     */
    public function computeCacheKey(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        unset($options['force_refresh']);

        if (isset($options['image_path']) && is_string($options['image_path']) && is_file($options['image_path'])) {
            $options['image_sha256'] = hash_file('sha256', $options['image_path']);
            unset($options['image_path']);
        }

        ksort($options);

        $payload = json_encode([
            'model' => $options['model'] ?? $this->metaAiClient->getModel(),
            'system' => $systemPrompt,
            'user' => $userPrompt,
            'options' => $options,
        ], JSON_THROW_ON_ERROR);

        return 'ai_resp_' . hash('sha256', $payload);
    }

    /**
     * Executes a chat request using the local cache unless force-refreshed or disabled
     */
    public function chat(string $systemPrompt, string $userPrompt, array $options = []): AiResponse
    {
        $forceRefresh = (bool) ($options['force_refresh'] ?? false);
        $cacheKey = $this->computeCacheKey($systemPrompt, $userPrompt, $options);

        if ($this->cacheEnabled && !$forceRefresh) {
            $item = $this->aiCache->getItem($cacheKey);
            if ($item->isHit()) {
                $cachedData = $item->get();
                if (is_array($cachedData) && isset($cachedData['content'], $cachedData['model'])) {
                    $this->logger->info('AI response served from local cache', [
                        'cache_key' => $cacheKey,
                        'model' => $cachedData['model'],
                    ]);

                    return new AiResponse(
                        content: (string) $cachedData['content'],
                        model: (string) $cachedData['model'],
                        promptTokens: (int) ($cachedData['promptTokens'] ?? 0),
                        completionTokens: (int) ($cachedData['completionTokens'] ?? 0),
                        cached: true,
                    );
                }
            }
        }

        $response = $this->metaAiClient->chat($systemPrompt, $userPrompt, $options);

        // Stocker : Enregistrer la réponse dans le pool de cache dédié
        if ($this->cacheEnabled) {
            $item = $this->aiCache->getItem($cacheKey);
            $item->set([
                'content' => $response->content,
                'model' => $response->model,
                'promptTokens' => $response->promptTokens,
                'completionTokens' => $response->completionTokens,
            ]);
            $this->aiCache->save($item);
        }

        $this->recordTokenUsage($response);

        return $response;
    }

    /**
     * Executes a structured JSON request using the local cache and validates required keys
     */
    public function completeJson(string $systemPrompt, string $userPrompt, array $jsonSchema = [], array $options = []): array
    {
        $schemaInstruction = "You MUST respond with valid, raw JSON only. Do not wrap the JSON in markdown fences or add explanations.";
        if ($jsonSchema !== []) {
            $schemaInstruction .= "\nExpected JSON schema:\n" . json_encode($jsonSchema, JSON_UNESCAPED_SLASHES);
            $options['response_format'] ??= ['type' => 'json_object'];
        }

        $response = $this->chat($systemPrompt . "\n\n" . $schemaInstruction, $userPrompt, $options);

        return $this->metaAiClient->decodeAndValidateJson($response->content, $jsonSchema);
    }

    /**
     * Returns cumulative token usage and estimated remaining budget
     *
     * @return array{totalTokens: int, spentCredits: float, remainingCredits: float, budgetCredits: float}
     */
    public function getBudgetSummary(): array
    {
        $item = $this->aiCache->getItem(self::BUDGET_CACHE_KEY);
        $totalTokens = $item->isHit() ? (int) $item->get() : 0;
        $spentCredits = round(($totalTokens / 1000.0) * self::CREDITS_PER_THOUSAND_TOKENS, 4);
        $remainingCredits = max(0.0, round($this->budgetCredits - $spentCredits, 4));

        return [
            'totalTokens' => $totalTokens,
            'spentCredits' => $spentCredits,
            'remainingCredits' => $remainingCredits,
            'budgetCredits' => $this->budgetCredits,
        ];
    }

    /**
     * Updates cumulative token counters and logs estimated remaining budget
     */
    private function recordTokenUsage(AiResponse $response): void
    {
        $item = $this->aiCache->getItem(self::BUDGET_CACHE_KEY);
        $previousTokens = $item->isHit() ? (int) $item->get() : 0;
        $newTotal = $previousTokens + $response->totalTokens();
        $item->set($newTotal);
        $this->aiCache->save($item);

        $summary = $this->getBudgetSummary();
        $this->logger->info('Meta AI tokens consumed', [
            'model' => $response->model,
            'request_tokens' => $response->totalTokens(),
            'cumulative_tokens' => $summary['totalTokens'],
            'remaining_credits' => $summary['remainingCredits'],
        ]);
    }
}
