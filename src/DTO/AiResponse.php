<?php

namespace App\DTO;

/**
 * Value object representing a normalized response from the AI model
 */
final readonly class AiResponse
{
    /**
     * @param string $content Raw textual or JSON content returned by the model
     * @param string $model Model identifier used for generation
     * @param int $promptTokens Number of input prompt tokens consumed
     * @param int $completionTokens Number of output completion tokens generated
     * @param bool $cached Whether the response was served from local cache
     */
    public function __construct(
        public string $content,
        public string $model,
        public int $promptTokens = 0,
        public int $completionTokens = 0,
        public bool $cached = false,
    ) {
    }

    /**
     * Returns the total number of tokens consumed by the request
     *
     * @return int Total tokens
     */
    public function totalTokens(): int
    {
        return $this->promptTokens + $this->completionTokens;
    }

    /**
     * Returns a copy of the response marked as served from the cache
     *
     * @return self Cached response copy
     */
    public function asCached(): self
    {
        return new self(
            content: $this->content,
            model: $this->model,
            promptTokens: $this->promptTokens,
            completionTokens: $this->completionTokens,
            cached: true,
        );
    }
}
