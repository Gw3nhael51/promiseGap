<?php

namespace App\Service;

use App\DTO\AiResponse;
use App\Exception\MetaAiException;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Bridge\Generic\Factory as GenericPlatformFactory;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Platform;
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
     * @param MetaAiSimulator|null $simulator Offline AI response simulator
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire('%app.ai.api_key%')]
        private string $apiKey = '',
        #[Autowire('%app.ai.endpoint%')]
        private string $endpoint = 'https://api.meta.ai/v1',
        #[Autowire('%app.ai.model%')]
        private string $model = 'muse-spark-1.3',
        #[Autowire('%app.ai.timeout%')]
        private int $timeout = 30,
        #[Autowire('%app.ai.max_retries%')]
        private int $maxRetries = 2,
        private ?MetaAiSimulator $simulator = null,
    ) {
        $this->simulator ??= new MetaAiSimulator();
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
     *
     * @param string $systemPrompt System instructions for the reasoning model
     * @param string $userPrompt User content or RAG context
     * @param array<string, mixed> $options Optional model parameters
     * @return AiResponse Normalized AI completion response
     */
    public function chat(string $systemPrompt, string $userPrompt, array $options = []): AiResponse
    {
        $model = (string) ($options['model'] ?? $this->model);
        $imagePath = isset($options['image_path']) && is_string($options['image_path'])
            ? $options['image_path']
            : null;

        if (!$this->isConfigured()) {
            return $this->simulator->simulateResponse($systemPrompt, $userPrompt, $model, $imagePath);
        }

        $platform = $this->createPlatform();
        $messages = $this->createMessageBag($systemPrompt, $userPrompt, $imagePath);
        $optionsPayload = $this->buildOptionsPayload($options);

        return $this->executeChatWithRetry($platform, $model, $messages, $optionsPayload, $systemPrompt . $userPrompt);
    }

    /**
     * Instantiates the Symfony AI generic platform for Meta Muse Spark
     *
     * @return Platform Configured platform instance
     */
    private function createPlatform(): Platform
    {
        $baseUrl = rtrim($this->endpoint, '/');
        $completionsPath = (str_ends_with($baseUrl, '/v1') || str_ends_with($baseUrl, '/compat/v1'))
            ? '/chat/completions'
            : '/v1/chat/completions';

        return GenericPlatformFactory::createPlatform(
            baseUrl: $baseUrl,
            apiKey: $this->apiKey,
            httpClient: $this->httpClient->withOptions(['timeout' => $this->timeout]),
            supportsCompletions: true,
            supportsEmbeddings: false,
            completionsPath: $completionsPath,
            name: 'meta-muse-spark',
        );
    }

    /**
     * Builds message bag containing system prompt and user text/image payload
     *
     * @param string $systemPrompt System instructions
     * @param string $userPrompt User text
     * @param ?string $imagePath Optional local path to an image file
     * @return MessageBag Assembled messages
     */
    private function createMessageBag(string $systemPrompt, string $userPrompt, ?string $imagePath): MessageBag
    {
        $userMessage = ($imagePath !== null && is_file($imagePath))
            ? Message::ofUser($userPrompt, Image::fromFile($imagePath))
            : Message::ofUser($userPrompt);

        return new MessageBag(
            Message::forSystem($systemPrompt),
            $userMessage,
        );
    }

    /**
     * Prepares the options dictionary sent to the model client
     *
     * @param array<string, mixed> $options Input options
     * @return array<string, mixed> Normalized options payload
     */
    private function buildOptionsPayload(array $options): array
    {
        $payload = [
            'temperature' => (float) ($options['temperature'] ?? 0.1),
            'reasoning_effort' => (string) ($options['reasoning_effort'] ?? 'low'),
        ];

        if (isset($options['response_format']) && is_array($options['response_format'])) {
            $payload['response_format'] = $options['response_format'];
        }

        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = (int) $options['max_tokens'];
        }

        return $payload;
    }

    /**
     * Executes the model invocation loop with exponential retry handling
     *
     * @param Platform $platform Configured AI platform
     * @param string $model Model identifier
     * @param MessageBag $messages Input messages
     * @param array<string, mixed> $optionsPayload Options payload
     * @param string $promptContext Full prompt text for token estimation fallback
     * @return AiResponse Normalized completion response
     */
    private function executeChatWithRetry(
        Platform $platform,
        string $model,
        MessageBag $messages,
        array $optionsPayload,
        string $promptContext,
    ): AiResponse {
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $this->maxRetries) {
            ++$attempt;

            try {
                $deferredResult = $platform->invoke($model, $messages, $optionsPayload);

                return $this->buildAiResponse($deferredResult, $model, $promptContext);
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

        throw new MetaAiException(
            sprintf('Meta AI request failed after %d attempts: %s', $attempt, $lastException?->getMessage() ?? 'unknown error'),
            0,
            $lastException,
        );
    }

    /**
     * Extracts text content and token telemetry from a deferred result
     *
     * @param mixed $deferredResult Platform invoke result
     * @param string $fallbackModel Target model identifier
     * @param string $promptContext Combined prompt context
     * @return AiResponse Normalized response
     */
    private function buildAiResponse(mixed $deferredResult, string $fallbackModel, string $promptContext): AiResponse
    {
        $content = $deferredResult->asText();
        $tokenUsage = $deferredResult->getMetadata()->get('token_usage');

        $promptTokens = $tokenUsage instanceof TokenUsageInterface && $tokenUsage->getPromptTokens() !== null
            ? $tokenUsage->getPromptTokens()
            : $this->estimateTokens($promptContext);

        $completionTokens = $tokenUsage instanceof TokenUsageInterface && $tokenUsage->getCompletionTokens() !== null
            ? $tokenUsage->getCompletionTokens()
            : $this->estimateTokens($content);

        $resolvedModel = $tokenUsage instanceof TokenUsageInterface && $tokenUsage->getModel() !== null
            ? $tokenUsage->getModel()
            : $fallbackModel;

        return new AiResponse(
            content: $content,
            model: $resolvedModel,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
        );
    }

    /**
     * Requests a structured JSON output from the model and validates required top-level keys
     *
     * @param string $systemPrompt System instructions for the reasoning model
     * @param string $userPrompt User content or RAG context
     * @param array<string, mixed> $jsonSchema Optional expected JSON schema
     * @param array<string, mixed> $options Optional model parameters
     * @return array<string, mixed> Validated decoded JSON payload
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
        $cleaned = trim($raw);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/is', $cleaned, $matches) === 1) {
            $cleaned = trim($matches[1]);
        }

        $decoded = json_decode($cleaned, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw new MetaAiException('Model response is not valid JSON: ' . json_last_error_msg());
        }

        $required = $jsonSchema['required'] ?? [];
        if (is_array($required)) {
            foreach ($required as $requiredKey) {
                if (!array_key_exists((string) $requiredKey, $decoded)) {
                    throw new MetaAiException(sprintf('Model JSON response is missing required key "%s".', $requiredKey));
                }
            }
        }

        return $decoded;
    }

    /**
     * Estimates token count using a 4-characters-per-token heuristic
     */
    private function estimateTokens(string $text): int
    {
        return max(1, (int) ceil(mb_strlen($text) / 4));
    }
}
