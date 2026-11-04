<?php

namespace App\Service;

use App\DTO\AiResponse;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Deterministic offline AI response simulator used for evaluations and test fixtures without API credentials
 */
#[Autoconfigure(public: true)]
class MetaAiSimulator
{
    private const string DEFAULT_SECTION_TITLE = 'Technical Specification';

    /**
     * Simulates structured model responses based on recognized prompt patterns
     *
     * @param string $systemPrompt System instructions
     * @param string $userPrompt User content
     * @param string $model Model name
     * @param ?string $imagePath Optional path to image
     * @return AiResponse Synthetic AI response
     */
    public function simulateResponse(
        string $systemPrompt,
        string $userPrompt,
        string $model,
        ?string $imagePath = null,
    ): AiResponse {
        $combined = strtolower($systemPrompt . "\n" . $userPrompt);

        if ($imagePath !== null || str_contains($combined, 'multimodal document vision') || str_contains($combined, 'transcribe this document image')) {
            $filenameHint = $imagePath !== null ? basename($imagePath) : 'document_scan.png';
            $content = implode("\n\n", [
                '[Page 1]',
                '# Scanned Contractual & Technical Document (' . $filenameHint . ')',
                '## 1. Service Level Agreement & Availability',
                '- The platform guarantees 99.95% monthly uptime availability across production clusters.',
                '- Recovery Point Objective (RPO) is strictly bounded to 15 minutes with automated encrypted backups.',
                '## 2. Security & Support Commitments',
                '- All customer data is encrypted at rest using AES-256 and in transit via TLS 1.3.',
                '- Critical severity incidents receive 24/7 engineering support with a 30-minute initial response time.',
            ]);
        } elseif (str_contains($combined, '"translations"') || str_contains($combined, 'translate the following contract audit claims')) {
            $decodedInput = json_decode($userPrompt, true);
            $items = is_array($decodedInput['items'] ?? null) ? $decodedInput['items'] : [];
            $content = json_encode(['translations' => $items], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } elseif (str_contains($combined, '"commitments"') || str_contains($combined, 'extract commercial commitments')) {
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
     * Estimates token count using a 4-characters-per-token heuristic
     *
     * @param string $text Text to estimate tokens for
     * @return int Estimated token count
     */
    private function estimateTokens(string $text): int
    {
        return max(1, (int) ceil(mb_strlen($text) / 4));
    }

    /**
     * Extracts synthetic commitment items from an input prompt in offline simulation mode
     *
     * @param string $userPrompt User prompt containing document text lines
     * @return string Serialized JSON payload containing extracted commitments
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

            if ($this->isCommitmentCandidate($trimmed)) {
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
     * Checks if a proposal line qualifies as a commercial commitment statement
     *
     * @param string $text Proposal line text
     * @return bool True if line contains contractual commitment keywords
     */
    private function isCommitmentCandidate(string $text): bool
    {
        $lower = strtolower($text);
        $staticKeywords = [
            'sla', 'uptime', 'availability', 'support', 'rpo', 'rto',
            'latency', 'backup', 'encrypt', 'iso', 'soc 2', 'gdpr',
            'hipaa', 'optimal', 'seamless', 'real-time', 'real time',
            'unlimited', 'shall', 'will provide',
        ];

        foreach ($staticKeywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }

        if (preg_match('/\b(guarantee\w*|commit\w*|deliver\w*|penalt\w*|credit\w*)\b/i', $text) === 1) {
            return true;
        }

        return (bool) preg_match('/\b(99\.\d+%|within \d+|response time)\b/i', $text);
    }

    /**
     * Classifies a commitment category from keywords in offline simulation mode
     *
     * @param string $sentence Commercial commitment statement
     * @return string Contractual category identifier (e.g. SLA, Security, Timeline)
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
     *
     * @param string $userPrompt User prompt containing commitment and retrieved specification chunks
     * @return string Serialized JSON payload containing status, rationale and evidence
     */
    private function simulateDiscrepancyQualification(string $userPrompt): string
    {
        $inputs = $this->extractPromptInputs($userPrompt);
        $qualification = $this->computeSimulatedQualification($inputs['commitment'], $inputs['chunks']);

        return json_encode($qualification, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Extracts commitment text and retrieved chunks from prompt
     *
     * @param string $userPrompt Input prompt
     * @return array{commitment: string, chunks: array<int, array<string, mixed>>} Extracted prompt data
     */
    private function extractPromptInputs(string $userPrompt): array
    {
        $commitment = '';
        if (preg_match('/COMMITMENT:\s*(.+?)(?:\n\n|\nRETRIEVED_CHUNKS:)/is', $userPrompt, $m) === 1) {
            $commitment = trim($m[1]);
        }

        $chunks = [];
        if (preg_match('/RETRIEVED_CHUNKS:\s*(\[.*\])\s*$/is', $userPrompt, $m) === 1) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) {
                $chunks = $decoded;
            }
        }

        return ['commitment' => $commitment, 'chunks' => $chunks];
    }

    /**
     * Computes the structured qualification array for simulated offline mode
     *
     * @param string $commitment Commercial commitment statement
     * @param array<int, array<string, mixed>> $chunks Retrieved specification chunks
     * @return array{status: string, rationale: string, evidence: array<int, array<string, mixed>>}
     */
    private function computeSimulatedQualification(string $commitment, array $chunks): array
    {
        if ($this->isVagueCommitment($commitment) && !$this->hasSimulationMetric($commitment)) {
            $topChunk = $chunks[0] ?? null;

            return [
                'status' => 'AMBIGUOUS',
                'rationale' => 'The commitment relies on subjective or non-measurable phrasing without concrete acceptance criteria in the technical specifications.',
                'evidence' => $topChunk !== null ? [$this->formatChunkEvidence($topChunk, 0.5)] : [],
            ];
        }

        if ($chunks === [] || (float) ($chunks[0]['similarityScore'] ?? 0.0) < 0.18 || trim((string) ($chunks[0]['text'] ?? '')) === '') {
            return [
                'status' => 'UNSUPPORTED',
                'rationale' => $chunks === []
                    ? 'No matching section or technical basis was found in the reference specifications for this commercial commitment.'
                    : 'The technical reference documents do not contain any provision covering this commitment.',
                'evidence' => [],
            ];
        }

        $topChunk = $chunks[0];
        $chunkText = (string) ($topChunk['text'] ?? '');
        $similarity = (float) ($topChunk['similarityScore'] ?? 0.0);

        return $this->evaluateChunkAlignment($commitment, $topChunk, $chunkText, $similarity);
    }

    /**
     * Evaluates whether a chunk contradicts, leaves ambiguous, or supports a commitment
     *
     * @param string $commitment Commercial commitment statement
     * @param array<string, mixed> $topChunk Top retrieved chunk
     * @param string $chunkText Verbatim chunk text
     * @param float $similarity Similarity score
     * @return array{status: string, rationale: string, evidence: array<int, array<string, mixed>>}
     */
    private function evaluateChunkAlignment(
        string $commitment,
        array $topChunk,
        string $chunkText,
        float $similarity,
    ): array {
        $contradiction = $this->detectContradiction($commitment, $chunkText);
        if ($contradiction !== null) {
            return [
                'status' => 'CONTRADICTORY',
                'rationale' => $contradiction,
                'evidence' => [$this->formatChunkEvidence($topChunk, $similarity)],
            ];
        }

        if (preg_match('/\b(tbd|to be determined|subject to change|not yet finalized|optional add-on|best effort)\b/i', $chunkText) === 1) {
            return [
                'status' => 'AMBIGUOUS',
                'rationale' => 'The technical specification leaves the target metric open, optional, or non-binding.',
                'evidence' => [$this->formatChunkEvidence($topChunk, $similarity)],
            ];
        }

        return [
            'status' => 'SUPPORTED',
            'rationale' => 'The commercial commitment is directly backed by the technical specification with aligned parameters.',
            'evidence' => [$this->formatChunkEvidence($topChunk, $similarity)],
        ];
    }

    /**
     * Formats a raw chunk into an evidence array item
     *
     * @param array<string, mixed> $chunk Raw chunk dictionary
     * @param float $similarity Similarity score
     * @return array<string, mixed> Formatted evidence dictionary
     */
    private function formatChunkEvidence(array $chunk, float $similarity): array
    {
        return [
            'documentId' => $chunk['documentId'] ?? null,
            'quote' => $chunk['text'] ?? '',
            'sectionTitle' => $chunk['sectionTitle'] ?? self::DEFAULT_SECTION_TITLE,
            'pageNumber' => $chunk['pageNumber'] ?? 1,
            'similarityScore' => $similarity,
        ];
    }

    /**
     * Checks if a commitment mentions numerical units, standards, or quantitative metrics
     *
     * @param string $commitment Commitment text
     * @return bool True if a metric was detected
     */
    private function hasSimulationMetric(string $commitment): bool
    {
        $lower = strtolower($commitment);
        $keywords = [
            '24/7', '24x7', 'zero data loss', 'zero-data-loss', 'daily',
            'hourly', 'weekly', 'monthly', 'annual', 'soc 2', 'gdpr',
            'hipaa', 'pci-dss', 'fedramp', 'sso', 'saml', 'oidc',
            'multi-region', 'active-active',
        ];

        foreach ($keywords as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }

        if (preg_match('/\b(iso\s*\d+|soc\s*2|aes-\d+|tls\s*1\.\d)/i', $commitment) === 1) {
            return true;
        }

        return (bool) preg_match('/\b\d+(?:\.\d+)?\s*(%|ms|s|sec|min|hour|hr|day|week|month|gb|tb|mbps|req\/s)/i', $commitment);
    }

    /**
     * Checks if a commitment relies on vague, subjective, or non-measurable superlative phrasing
     *
     * @param string $commitment Commitment text
     * @return bool True if vague phrasing was detected
     */
    private function isVagueCommitment(string $commitment): bool
    {
        $lower = strtolower($commitment);
        $vaguePhrases = [
            'optimal', 'best-in-class', 'world-class', 'seamless',
            'cutting-edge', 'industry-leading', 'effortless', 'ultra-fast',
            'near-instant', 'as soon as possible', 'reasonable efforts',
            'high-speed', 'robust performance', 'flexible scalability',
            'state-of-the-art', 'maximum reliability', 'superior quality',
            'next-generation',
        ];

        foreach ($vaguePhrases as $phrase) {
            if (str_contains($lower, $phrase)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks whether a commitment and a specification passage contain conflicting metrics or negations
     *
     * @param string $commitment Commercial commitment statement
     * @param string $specText Technical reference specification text excerpt
     * @return string|null Contradiction rationale if in direct conflict, null otherwise
     */
    private function detectContradiction(string $commitment, string $specText): ?string
    {
        $metricConflict = $this->detectMetricContradiction($commitment, $specText);
        if ($metricConflict !== null) {
            return $metricConflict;
        }

        return $this->detectSemanticContradiction($commitment, $specText);
    }

    /**
     * Detects numeric percentage or unit-based value conflicts between commitment and spec
     *
     * @param string $commitment Commercial commitment statement
     * @param string $specText Technical specification text
     * @return ?string Contradiction rationale or null
     */
    private function detectMetricContradiction(string $commitment, string $specText): ?string
    {
        if (preg_match_all('/(\d+(?:\.\d+)?)\s*%/', $commitment, $cPct) >= 1
            && preg_match_all('/(\d+(?:\.\d+)?)\s*%/', $specText, $sPct) >= 1
            && (float) $cPct[1][0] !== (float) $sPct[1][0]
        ) {
            return sprintf(
                'The commercial proposal promises %s%% whereas the technical specification states %s%%.',
                $cPct[1][0],
                $sPct[1][0],
            );
        }

        $cMetric = $this->extractFirstMetric($commitment);
        $sMetric = $this->extractFirstMetric($specText);

        if ($cMetric !== null && $sMetric !== null) {
            $cVal = $cMetric[2];
            $sVal = $sMetric[2];
            if ($cVal[0] === $sVal[0] && abs($cVal[1] - $sVal[1]) > 0.0001) {
                return sprintf(
                    'The proposal commits to %s %s while the technical specification defines %s %s.',
                    $cMetric[0],
                    $cMetric[1],
                    $sMetric[0],
                    $sMetric[1],
                );
            }
        }

        return null;
    }

    /**
     * Extracts the first recognized numeric metric and its normalized canonical value
     *
     * @param string $text Input text
     * @return array{0: string, 1: string, 2: array{0: string, 1: float}}|null
     */
    private function extractFirstMetric(string $text): ?array
    {
        if (preg_match_all('/(\d+(?:\.\d+)?)\s*([a-z\/]+)/i', $text, $matches, PREG_SET_ORDER) === 0) {
            return null;
        }

        foreach ($matches as $match) {
            $normalized = $this->normalizeUnitValue((float) $match[1], $match[2]);
            if ($normalized !== null) {
                return [$match[1], $match[2], $normalized];
            }
        }

        return null;
    }

    /**
     * Detects semantic, operational, or tenancy discrepancies between commitment and spec
     *
     * @param string $commitment Commercial commitment statement
     * @param string $specText Technical specification text
     * @return ?string Contradiction rationale or null
     */
    private function detectSemanticContradiction(string $commitment, string $specText): ?string
    {
        $cLower = strtolower($commitment);
        $sLower = strtolower($specText);

        if ((str_contains($cLower, '24/7') || str_contains($cLower, '24x7'))
            && (str_contains($sLower, 'business hours') || str_contains($sLower, '8x5') || str_contains($sLower, '9am') || str_contains($sLower, 'monday to friday') || str_contains($sLower, 'weekdays'))
        ) {
            return 'The proposal promises 24/7 coverage whereas the specification restricts support to standard business hours.';
        }

        if (str_contains($cLower, 'synchronous') && str_contains($sLower, 'asynchronous')) {
            return 'The proposal commits to synchronous replication whereas the technical specification defines asynchronous replication.';
        }

        return $this->detectArchitectureContradiction($commitment, $specText, $cLower, $sLower);
    }

    /**
     * Detects RPO, multi-tenancy, or explicit feature exclusion contradictions
     *
     * @param string $commitment Commercial commitment statement
     * @param string $specText Technical specification text
     * @param string $cLower Lowercase commitment
     * @param string $sLower Lowercase spec text
     * @return ?string Contradiction rationale or null
     */
    private function detectArchitectureContradiction(
        string $commitment,
        string $specText,
        string $cLower,
        string $sLower,
    ): ?string {
        if (str_contains($cLower, 'zero data loss')
            && (
                preg_match('/rpo\b[^.]*\b([1-9]\d*)\s*(minutes?|hours?|sec|seconds?)/i', $specText) === 1
                || preg_match('/([1-9]\d*)\s*[- ]\s*(minutes?|hours?|sec|seconds?)\s*rpo/i', $specText) === 1
            )
        ) {
            return 'The proposal claims zero data loss (RPO = 0) while the technical specification defines a non-zero Recovery Point Objective.';
        }

        if ((str_contains($cLower, 'dedicated') || str_contains($cLower, 'single-tenant') || str_contains($cLower, 'isolated'))
            && (str_contains($sLower, 'multi-tenant') || str_contains($sLower, 'shared infrastructure') || str_contains($sLower, 'shared cluster'))
        ) {
            return 'The proposal promises dedicated or single-tenant isolation whereas the technical architecture uses shared multi-tenant infrastructure.';
        }

        return $this->detectExclusionContradiction($commitment, $specText);
    }

    /**
     * Detects explicit feature exclusion or unsupported claim in specification
     *
     * @param string $commitment Commercial commitment statement
     * @param string $specText Technical specification text
     * @return ?string Contradiction rationale or null
     */
    private function detectExclusionContradiction(string $commitment, string $specText): ?string
    {
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
     * @param float $value Numeric quantity
     * @param string $unit Unit name or abbreviation
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
}
