<?php

namespace App\Service;

use App\DTO\AiResponse;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Bridge\Generic\Factory as GenericPlatformFactory;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Communicates with the Meta Model API (Muse Spark) with timeout, retry and structured JSON support
 */
class MetaAiClient implements AiClientInterface
{
    /**
     * @param HttpClientInterface $httpClient Symfony HTTP client for API calls
     * @param LoggerInterface $logger Application logger
     * @param string $apiKey Meta Model API bearer key
     * @param string $endpoint Base URL of the Meta Model API
     * @param string $model Default reasoning model identifier (Muse Spark)
     * @param int $timeout Request timeout in seconds
     * @param int $maxRetries Maximum number of retries on transient failures
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire('%app.ai.api_key%')]
        private string $apiKey = '',
        #[Autowire('%app.ai.endpoint%')]
        private string $endpoint = 'https://api.llama.com/compat/v1',
        #[Autowire('%app.ai.model%')]
        private string $model = 'Muse-Spark',
        #[Autowire('%app.ai.timeout%')]
        private int $timeout = 30,
        #[Autowire('%app.ai.max_retries%')]
        private int $maxRetries = 2,
    ) {
    }

    /**
     * Indicates whether the client is configured with a live API key
     *
     * @return bool True when a non-empty API key is configured
     */
    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->apiKey !== 'mock';
    }

    /**
     * Returns the configured reasoning model identifier
     *
     * @return string Model identifier
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Sends a chat completion request to Meta Muse Spark with retry and fallback handling
     */
    public function chat(string $systemPrompt, string $userPrompt, array $options = []): AiResponse
    {
        $model = (string) ($options['model'] ?? $this->model);
        $temperature = (float) ($options['temperature'] ?? 0.1);

        if (!$this->isConfigured()) {
            return $this->simulateResponse($systemPrompt, $userPrompt, $model);
        }

        $optionsPayload = [
            'temperature' => $temperature,
        ];

        if (isset($options['response_format']) && is_array($options['response_format'])) {
            $optionsPayload['response_format'] = $options['response_format'];
        }

        if (isset($options['max_tokens'])) {
            $optionsPayload['max_tokens'] = (int) $options['max_tokens'];
        }

        $baseUrl = rtrim($this->endpoint, '/');
        $completionsPath = (str_ends_with($baseUrl, '/v1') || str_ends_with($baseUrl, '/compat/v1'))
            ? '/chat/completions'
            : '/v1/chat/completions';

        $platform = GenericPlatformFactory::createPlatform(
            baseUrl: $baseUrl,
            apiKey: $this->apiKey,
            httpClient: $this->httpClient->withOptions(['timeout' => $this->timeout]),
            supportsCompletions: true,
            supportsEmbeddings: false,
            completionsPath: $completionsPath,
            name: 'meta-muse-spark',
        );

        $messages = new MessageBag(
            Message::forSystem($systemPrompt),
            Message::ofUser($userPrompt),
        );

        $attempt = 0;
        $lastException = null;

        while ($attempt <= $this->maxRetries) {
            ++$attempt;

            try {
                // Invoquer : Appeler Meta Muse Spark via Symfony AI Platform (Generic OpenAI-compatible bridge)
                $deferredResult = $platform->invoke($model, $messages, $optionsPayload);
                $content = $deferredResult->asText();

                $tokenUsage = $deferredResult->getMetadata()->get('token_usage');
                $promptTokens = $tokenUsage instanceof TokenUsageInterface && $tokenUsage->getPromptTokens() !== null
                    ? $tokenUsage->getPromptTokens()
                    : $this->estimateTokens($systemPrompt . $userPrompt);
                $completionTokens = $tokenUsage instanceof TokenUsageInterface && $tokenUsage->getCompletionTokens() !== null
                    ? $tokenUsage->getCompletionTokens()
                    : $this->estimateTokens($content);
                $resolvedModel = $tokenUsage instanceof TokenUsageInterface && $tokenUsage->getModel() !== null
                    ? $tokenUsage->getModel()
                    : $model;

                return new AiResponse(
                    content: $content,
                    model: $resolvedModel,
                    promptTokens: $promptTokens,
                    completionTokens: $completionTokens,
                );
            } catch (AuthenticationException|BadRequestException $exception) {
                throw new \InvalidArgumentException(sprintf('Meta API rejected the request: %s', $exception->getMessage()), 0, $exception);
            } catch (\Throwable $exception) {
                $lastException = $exception;
                $this->logger->warning('Meta AI request attempt failed', [
                    'attempt' => $attempt,
                    'max_retries' => $this->maxRetries,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        throw new \RuntimeException(
            sprintf('Meta AI request failed after %d attempts: %s', $attempt, $lastException?->getMessage() ?? 'unknown error'),
            0,
            $lastException,
        );
    }

    /**
     * Requests a structured JSON output from the model and validates required top-level keys
     */
    public function completeJson(string $systemPrompt, string $userPrompt, array $jsonSchema = [], array $options = []): array
    {
        $schemaInstruction = "You MUST respond with valid, raw JSON only. Do not wrap the JSON in markdown fences or add explanations.";
        if ($jsonSchema !== []) {
            $schemaInstruction .= "\nExpected JSON schema:\n" . json_encode($jsonSchema, JSON_UNESCAPED_SLASHES);
            $options['response_format'] ??= ['type' => 'json_object'];
        }

        $response = $this->chat($systemPrompt . "\n\n" . $schemaInstruction, $userPrompt, $options);

        return $this->decodeAndValidateJson($response->content, $jsonSchema);
    }

    /**
     * Parses a JSON string (stripping optional markdown fences) and checks required schema keys
     *
     * @param string $raw Raw model output
     * @param array<string, mixed> $jsonSchema Optional JSON schema with a 'required' key list
     * @return array<string, mixed> Parsed associative array
     * @throws \RuntimeException When JSON is malformed or misses required properties
     */
    public function decodeAndValidateJson(string $raw, array $jsonSchema = []): array
    {
        // Nettoyer : Retirer les éventuelles balises markdown autour du JSON
        $cleaned = trim($raw);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $cleaned, $matches) === 1) {
            $cleaned = trim($matches[1]);
        }

        $decoded = json_decode($cleaned, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Model response is not valid JSON: ' . json_last_error_msg());
        }

        $required = $jsonSchema['required'] ?? [];
        if (is_array($required)) {
            foreach ($required as $requiredKey) {
                if (!array_key_exists((string) $requiredKey, $decoded)) {
                    throw new \RuntimeException(sprintf('Model JSON response is missing required key "%s".', $requiredKey));
                }
            }
        }

        return $decoded;
    }

    /**
     * Generates a deterministic local response when no live API key is configured
     */
    private function simulateResponse(string $systemPrompt, string $userPrompt, string $model): AiResponse
    {
        $combined = strtolower($systemPrompt . "\n" . $userPrompt);

        if (str_contains($combined, '"commitments"') || str_contains($combined, 'extract commercial commitments')) {
            $content = $this->simulateCommitmentExtraction($userPrompt);
        } elseif (str_contains($combined, '"status"') && (str_contains($combined, 'supported') || str_contains($combined, 'contradictory'))) {
            $content = $this->simulateDiscrepancyQualification($userPrompt);
        } elseif (str_contains($combined, 'json')) {
            $content = json_encode([
                'status' => 'ok',
                'model' => $model,
                'mode' => 'local_simulation',
                'summary' => 'Muse Spark diagnostic check succeeded.',
            ], JSON_THROW_ON_ERROR);
        } else {
            $content = sprintf('[%s local simulation] Ready to audit commercial commitments.', $model);
        }

        return new AiResponse(
            content: $content,
            model: $model,
            promptTokens: $this->estimateTokens($systemPrompt . $userPrompt),
            completionTokens: $this->estimateTokens($content),
        );
    }

    /**
     * Produces deterministic commitment extraction JSON from a proposal text in offline mode
     */
    private function simulateCommitmentExtraction(string $userPrompt): string
    {
        $commitments = [];
        $currentPage = 1;

        foreach (preg_split('/\R+/', $userPrompt) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            if (preg_match('/^\[Page\s+(\d+)\]$/i', $trimmed, $pageMatch) === 1) {
                $currentPage = (int) $pageMatch[1];
                continue;
            }
            if (str_starts_with($trimmed, '#') || mb_strlen($trimmed) < 20) {
                continue;
            }

            // Extraire : Repérer les phrases contenant un engagement commercial ou technique
            if (preg_match('/\b(guarantee\w*|sla|uptime|availability|99\.\d+%|response time|within \d+|shall|will provide|commit\w*|deliver\w*|support|rpo|rto|latency|backup\w*|encrypt\w*|iso|soc\s*2|gdpr|hipaa|penalt\w*|credit\w*|optimal|best[- ]effort\w*|seamless\w*|world[- ]class|real[- ]time|unlimited)\b/i', $trimmed) === 1) {
                $cleanSentence = ltrim($trimmed, "-*• \t");
                $commitments[] = [
                    'verbatimText' => $cleanSentence,
                    'category' => $this->guessCommitmentCategory($cleanSentence),
                    'confidenceScore' => 0.94,
                    'sourcePage' => $currentPage,
                ];
            }
        }

        if ($commitments === []) {
            $fallback = trim(preg_replace('/\[Page\s+\d+\]/i', '', $userPrompt) ?? $userPrompt);
            if ($fallback !== '') {
                $commitments[] = [
                    'verbatimText' => mb_substr($fallback, 0, 280),
                    'category' => 'SLA',
                    'confidenceScore' => 0.85,
                    'sourcePage' => $currentPage,
                ];
            }
        }

        return json_encode(['commitments' => $commitments], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Classifies a commitment category from keywords in offline simulation mode
     */
    private function guessCommitmentCategory(string $sentence): string
    {
        $lower = strtolower($sentence);

        return match (true) {
            str_contains($lower, 'uptime'), str_contains($lower, 'availability'), str_contains($lower, 'sla'), str_contains($lower, 'rpo'), str_contains($lower, 'rto'), str_contains($lower, 'latency') => 'SLA',
            str_contains($lower, 'support'), str_contains($lower, 'response time'), str_contains($lower, '24/7'), str_contains($lower, 'ticket') => 'SUPPORT',
            str_contains($lower, 'iso'), str_contains($lower, 'soc'), str_contains($lower, 'gdpr'), str_contains($lower, 'hipaa'), str_contains($lower, 'encrypt') => 'SECURITY',
            str_contains($lower, 'deliver'), str_contains($lower, 'within'), str_contains($lower, 'week'), str_contains($lower, 'day'), str_contains($lower, 'migration') => 'DELIVERY',
            default => 'GENERAL',
        };
    }

    /**
     * Produces deterministic discrepancy qualification JSON from a commitment and retrieved chunks
     */
    private function simulateDiscrepancyQualification(string $userPrompt): string
    {
        $commitment = '';
        if (preg_match('/COMMITMENT:\s*(.+?)(?:\n\n|\nRETRIEVED_CHUNKS:)/is', $userPrompt, $m) === 1) {
            $commitment = trim($m[1]);
        }

        $chunksJson = '[]';
        if (preg_match('/RETRIEVED_CHUNKS:\s*(\[.*\])\s*$/is', $userPrompt, $m) === 1) {
            $chunksJson = $m[1];
        }
        $chunks = json_decode($chunksJson, true);
        if (!is_array($chunks)) {
            $chunks = [];
        }

        // Détecter : Signaler d'emblée les promesses vagues dépourvues de métriques vérifiables
        $hasMetric = preg_match('/(\d+(\.\d+)?\s*(%|ms|s|sec|seconds|min|minutes|hours|hrs|days|weeks|months|gb|tb|mbps|req\/s|iso\s*\d+|soc\s*2|aes-\d+|tls\s*1\.\d)|\b(24\/7|24x7|zero[- ]data[- ]loss|daily|hourly|weekly|monthly|annual|soc\s*2|gdpr|hipaa|pci[- ]dss|fedramp|sso|saml|oidc|multi[- ]region|active[- ]active)\b)/i', $commitment) === 1;
        $isVague = preg_match('/\b(optimal|best[- ]in[- ]class|world[- ]class|seamless\w*|cutting[- ]edge|industry[- ]leading|effortless\w*|ultra[- ]fast|near[- ]instant\w*|as soon as possible|reasonable efforts|high[- ]speed|robust performance|flexible scalability|state[- ]of[- ]the[- ]art|maximum reliability|superior quality|next[- ]generation)\b/i', $commitment) === 1;

        if ($isVague && !$hasMetric) {
            $topChunk = $chunks[0] ?? null;
            return json_encode([
                'status' => 'AMBIGUOUS',
                'rationale' => 'The commitment relies on subjective or non-measurable phrasing without concrete acceptance criteria in the technical specifications.',
                'evidence' => $topChunk !== null ? [[
                    'documentId' => $topChunk['documentId'] ?? null,
                    'quote' => $topChunk['text'] ?? '',
                    'sectionTitle' => $topChunk['sectionTitle'] ?? 'Technical Specification',
                    'pageNumber' => $topChunk['pageNumber'] ?? 1,
                    'similarityScore' => $topChunk['similarityScore'] ?? 0.5,
                ]] : [],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }

        if ($chunks === []) {
            return json_encode([
                'status' => 'UNSUPPORTED',
                'rationale' => 'No matching section or technical basis was found in the reference specifications for this commercial commitment.',
                'evidence' => [],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }

        $topChunk = $chunks[0];
        $chunkText = (string) ($topChunk['text'] ?? '');
        $similarity = (float) ($topChunk['similarityScore'] ?? 0.0);

        if ($similarity < 0.18 || trim($chunkText) === '') {
            return json_encode([
                'status' => 'UNSUPPORTED',
                'rationale' => 'The technical reference documents do not contain any provision covering this commitment.',
                'evidence' => [],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }

        $contradiction = $this->detectContradiction($commitment, $chunkText);
        if ($contradiction !== null) {
            return json_encode([
                'status' => 'CONTRADICTORY',
                'rationale' => $contradiction,
                'evidence' => [[
                    'documentId' => $topChunk['documentId'] ?? null,
                    'quote' => $chunkText,
                    'sectionTitle' => $topChunk['sectionTitle'] ?? 'Technical Specification',
                    'pageNumber' => $topChunk['pageNumber'] ?? 1,
                    'similarityScore' => $similarity,
                ]],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }

        if (preg_match('/\b(tbd|to be determined|subject to change|not yet finalized|optional add-on|best effort)\b/i', $chunkText) === 1) {
            return json_encode([
                'status' => 'AMBIGUOUS',
                'rationale' => 'The technical specification leaves the target metric open, optional, or non-binding.',
                'evidence' => [[
                    'documentId' => $topChunk['documentId'] ?? null,
                    'quote' => $chunkText,
                    'sectionTitle' => $topChunk['sectionTitle'] ?? 'Technical Specification',
                    'pageNumber' => $topChunk['pageNumber'] ?? 1,
                    'similarityScore' => $similarity,
                ]],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        }

        return json_encode([
            'status' => 'SUPPORTED',
            'rationale' => 'The commercial commitment is directly backed by the technical specification with aligned parameters.',
            'evidence' => [[
                'documentId' => $topChunk['documentId'] ?? null,
                'quote' => $chunkText,
                'sectionTitle' => $topChunk['sectionTitle'] ?? 'Technical Specification',
                'pageNumber' => $topChunk['pageNumber'] ?? 1,
                'similarityScore' => $similarity,
            ]],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Checks whether a commitment and a specification passage contain conflicting metrics or negations
     */
    private function detectContradiction(string $commitment, string $specText): ?string
    {
        // Comparer : Vérifier les pourcentages (ex: 99.99% vendu vs 99.5% spécifié)
        if (preg_match_all('/(\d+(?:\.\d+)?)\s*%/', $commitment, $cPct) >= 1
            && preg_match_all('/(\d+(?:\.\d+)?)\s*%/', $specText, $sPct) >= 1
        ) {
            if ((float) $cPct[1][0] !== (float) $sPct[1][0]) {
                return sprintf(
                    'The commercial proposal promises %s%% whereas the technical specification states %s%%.',
                    $cPct[1][0],
                    $sPct[1][0],
                );
            }
        }

        // Comparer : Vérifier les durées et délais numériques (minutes, heures, jours, ms, Go/To)
        $unitPattern = '/(\d+(?:\.\d+)?)\s*(ms|milliseconds?|seconds?|sec|minutes?|min|hours?|hrs?|days?|weeks?|months?|gb|tb|req\/s|rps|years?)\b/i';
        if (preg_match($unitPattern, $commitment, $cUnit) === 1
            && preg_match($unitPattern, $specText, $sUnit) === 1
        ) {
            $cVal = $this->normalizeUnitValue((float) $cUnit[1], $cUnit[2]);
            $sVal = $this->normalizeUnitValue((float) $sUnit[1], $sUnit[2]);
            if ($cVal !== null && $sVal !== null && $cVal[0] === $sVal[0] && abs($cVal[1] - $sVal[1]) > 0.0001) {
                return sprintf(
                    'The proposal commits to %s %s while the technical specification defines %s %s.',
                    $cUnit[1],
                    $cUnit[2],
                    $sUnit[1],
                    $sUnit[2],
                );
            }
        }

        // Comparer : Vérifier les oppositions directes (24/7 vs business hours, zero data loss vs RPO > 0, etc.)
        $cLower = strtolower($commitment);
        $sLower = strtolower($specText);

        if ((str_contains($cLower, '24/7') || str_contains($cLower, '24x7'))
            && (str_contains($sLower, 'business hours') || str_contains($sLower, '8x5') || str_contains($sLower, '9am') || str_contains($sLower, 'monday to friday') || str_contains($sLower, 'weekdays'))
        ) {
            return 'The proposal promises 24/7 coverage whereas the specification restricts support to standard business hours.';
        }

        if (str_contains($cLower, 'zero data loss')
            && preg_match('/rpo\b[^.]*\b([1-9]\d*)\s*(minutes?|hours?|sec|seconds?)/i', $specText) === 1
        ) {
            return 'The proposal claims zero data loss (RPO = 0) while the technical specification defines a non-zero Recovery Point Objective.';
        }

        if ((str_contains($cLower, 'dedicated') || str_contains($cLower, 'single-tenant') || str_contains($cLower, 'isolated'))
            && (str_contains($sLower, 'multi-tenant') || str_contains($sLower, 'shared infrastructure') || str_contains($sLower, 'shared cluster'))
        ) {
            return 'The proposal promises dedicated or single-tenant isolation whereas the technical architecture uses shared multi-tenant infrastructure.';
        }

        if (preg_match('/\b(not supported|excluded|out of scope|no longer provided|does not include|not included|disabled)\b/i', $specText) === 1
            && preg_match('/\b(not supported|excluded|out of scope|does not include)\b/i', $commitment) !== 1
        ) {
            return 'The commercial commitment is explicitly excluded or marked as unsupported in the technical specification.';
        }

        return null;
    }

    /**
     * Converts a numeric value with unit into a canonical dimension and base value
     *
     * @return array{0: string, 1: float}|null Dimension name and normalized value
     */
    private function normalizeUnitValue(float $value, string $unit): ?array
    {
        $u = strtolower(rtrim($unit, 's'));

        return match ($u) {
            'ms', 'millisecond' => ['time_sec', $value / 1000.0],
            'sec', 'second' => ['time_sec', $value],
            'min', 'minute' => ['time_sec', $value * 60.0],
            'hr', 'hour' => ['time_sec', $value * 3600.0],
            'day' => ['time_sec', $value * 86400.0],
            'week' => ['time_sec', $value * 604800.0],
            'month' => ['time_sec', $value * 2592000.0],
            'year' => ['time_sec', $value * 31536000.0],
            'gb' => ['storage_gb', $value],
            'tb' => ['storage_gb', $value * 1024.0],
            'req/s', 'rp' => ['rate_rps', $value],
            default => null,
        };
    }

    /**
     * Estimates token count using a 4-characters-per-token heuristic
     */
    private function estimateTokens(string $text): int
    {
        return max(1, (int) ceil(mb_strlen($text) / 4));
    }
}
