<?php

namespace App\Service;

use App\DTO\AiResponse;

/**
 * Contract for interacting with the AI reasoning and embedding platform
 */
interface AiClientInterface
{
    /**
     * Sends a system and user prompt to the reasoning model and returns a raw text response
     *
     * @param string $systemPrompt System instructions defining role and constraints
     * @param string $userPrompt User content or document payload to analyze
     * @param array<string, mixed> $options Optional model parameters (temperature, max_tokens, force_refresh)
     * @return AiResponse Normalized AI response with token usage
     */
    public function chat(string $systemPrompt, string $userPrompt, array $options = []): AiResponse;

    /**
     * Requests a structured JSON response conforming to the provided JSON schema
     *
     * @param string $systemPrompt System instructions defining role and JSON schema rules
     * @param string $userPrompt User content or document payload to analyze
     * @param array<string, mixed> $jsonSchema Expected JSON schema definition
     * @param array<string, mixed> $options Optional model parameters (temperature, max_tokens, force_refresh)
     * @return array<string, mixed> Decoded and schema-validated associative array
     */
    public function completeJson(string $systemPrompt, string $userPrompt, array $jsonSchema = [], array $options = []): array;
}
