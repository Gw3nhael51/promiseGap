<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Generates normalized vector embeddings for semantic indexing and retrieval with pgvector (#12)
 */
#[Autoconfigure(public: true)]
class EmbeddingGenerator
{
    /**
     * Domain-specific semantic concept clusters to boost alignment between commercial and technical phrasings
     *
     * @var array<int, string[]>
     */
    private const array SEMANTIC_CONCEPTS = [
        0 => ['availability', 'uptime', 'sla', '99.99', '99.9', '99.5', '99.0', 'monthly', 'downtime', 'outage'],
        1 => ['response', 'support', 'ticket', 'p1', 'critical', 'incident', 'escalation', '24/7', 'business', 'hours', 'acknowledge'],
        2 => ['rpo', 'rto', 'recovery', 'disaster', 'failover', 'backup', 'restore', 'data', 'loss', 'zero', 'snapshot', 'replication'],
        3 => ['latency', 'performance', 'throughput', 'millisecond', 'ms', 'p95', 'p99', 'speed', 'rate', 'limit', 'requests', 'rps'],
        4 => ['security', 'encryption', 'aes', 'tls', 'kms', 'key', 'at-rest', 'in-transit', 'cipher', 'hsm'],
        5 => ['compliance', 'certification', 'soc', 'soc2', 'iso', '27001', 'gdpr', 'hipaa', 'pci', 'fedramp', 'audit'],
        6 => ['delivery', 'implementation', 'onboarding', 'migration', 'timeline', 'deadline', 'milestone', 'weeks', 'days', 'go-live'],
        7 => ['architecture', 'tenant', 'single-tenant', 'multi-tenant', 'dedicated', 'isolated', 'shared', 'cluster', 'region', 'vpc'],
        8 => ['retention', 'log', 'logging', 'archive', 'storage', 'telemetry', 'siem', 'days', 'months', 'years'],
        9 => ['authentication', 'sso', 'saml', 'oidc', 'mfa', 'rbac', 'iam', 'identity', 'access', 'directory'],
        10 => ['penalty', 'credit', 'remedy', 'breach', 'refund', 'cap', 'liability', 'indemnity', 'termination'],
        11 => ['api', 'webhook', 'sdk', 'graphql', 'rest', 'integration', 'connector', 'export', 'import', 'batch'],
        12 => ['custom', 'bespoke', 'feature', 'roadmap', 'plugin', 'white-label', 'branding', 'sso'],
        13 => ['training', 'documentation', 'workshop', 'csm', 'manager', 'account', 'quarterly', 'review'],
        14 => ['bandwidth', 'egress', 'ingress', 'storage', 'gb', 'tb', 'quota', 'overage', 'concurrent', 'users'],
        15 => ['maintenance', 'window', 'scheduled', 'patch', 'upgrade', 'notice', 'advance', 'weekend'],
    ];

    private const array STOP_WORDS = [
        'the' => true, 'and' => true, 'for' => true, 'with' => true, 'that' => true,
        'this' => true, 'from' => true, 'are' => true, 'will' => true, 'shall' => true,
        'our' => true, 'all' => true, 'any' => true, 'per' => true, 'via' => true,
    ];

    /**
     * @param int $dimensions Vector embedding dimensionality (must match pgvector column)
     */
    public function __construct(
        #[Autowire('%app.ai_store.dimensions%')]
        private int $dimensions = 128,
    ) {
    }

    /**
     * Computes a L2-normalized embedding vector of the configured dimensionality
     *
     * @param string $text Input text to embed
     * @return float[] Normalized float vector
     */
    public function embed(string $text): array
    {
        $vector = array_fill(0, $this->dimensions, 0.0);
        $lower = strtolower($text);
        $tokens = preg_split('/[^a-z0-9.%\/-]+/', $lower, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $this->applySemanticConcepts($lower, $vector);
        $this->applyFeatureHashing($tokens, $vector);

        return $this->normalize($vector);
    }

    /**
     * Projects domain concept clusters onto the first vector dimensions
     *
     * @param string $lower Lowercase input text
     * @param float[] $vector Reference to embedding vector
     * @return void
     */
    private function applySemanticConcepts(string $lower, array &$vector): void
    {
        foreach (self::SEMANTIC_CONCEPTS as $conceptIdx => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    $vector[$conceptIdx % $this->dimensions] += 3.0;
                    $vector[($conceptIdx + 16) % $this->dimensions] += 1.5;
                }
            }
        }
    }

    /**
     * Applies signed feature hashing for lexical unigram stems and bigrams
     *
     * @param string[] $tokens Tokenized lexical words
     * @param float[] $vector Reference to embedding vector
     * @return void
     */
    private function applyFeatureHashing(array $tokens, array &$vector): void
    {
        $stems = [];
        $availableBuckets = max(1, $this->dimensions - 32);

        foreach ($tokens as $token) {
            $trimmed = trim($token, '.-/');
            if (strlen($trimmed) < 2 || isset(self::STOP_WORDS[$trimmed])) {
                continue;
            }
            if (preg_match('/^\d+(\.\d+)?%?$/', $trimmed) === 1) {
                continue;
            }
            $stem = $this->stemToken($trimmed);
            $stems[] = $stem;

            $hash = crc32($stem);
            $bucket = 32 + ($hash % $availableBuckets);
            $sign = (($hash >> 16) & 1) === 1 ? 1.0 : -1.0;
            $vector[$bucket] += $sign * 1.2;
        }

        $count = count($stems);
        for ($i = 0; $i < $count - 1; ++$i) {
            $bigram = $stems[$i] . '_' . $stems[$i + 1];
            $hash = crc32($bigram);
            $bucket = 32 + ($hash % $availableBuckets);
            $sign = (($hash >> 16) & 1) === 1 ? 1.0 : -1.0;
            $vector[$bucket] += $sign * 0.6;
        }
    }

    /**
     * Formats a float vector into pgvector literal representation "[0.1,0.2,...]"
     *
     * @param float[] $vector Embedding vector
     * @return string pgvector literal string
     */
    public function toPgVectorLiteral(array $vector): string
    {
        return '[' . implode(',', array_map(static fn (float $v): string => sprintf('%.6f', $v), $vector)) . ']';
    }

    /**
     * Applies lightweight suffix stripping so related words collide in the same vector bucket
     *
     * @param string $token Token to strip suffixes from
     * @return string Stemmed token
     */
    private function stemToken(string $token): string
    {
        foreach (['ations', 'ation', 'ments', 'ment', 'ities', 'ity', 'ing', 'ed', 'es', 's'] as $suffix) {
            if (strlen($token) > strlen($suffix) + 3 && str_ends_with($token, $suffix)) {
                return substr($token, 0, -strlen($suffix));
            }
        }

        return $token;
    }

    /**
     * L2-normalizes a float vector so inner product equals cosine similarity
     *
     * @param float[] $vector Raw vector
     * @return float[] Unit vector
     */
    private function normalize(array $vector): array
    {
        $normSq = 0.0;
        foreach ($vector as $val) {
            $normSq += $val * $val;
        }

        if ($normSq <= 1e-12) {
            $vector[0] = 1.0;

            return $vector;
        }

        $norm = sqrt($normSq);

        return array_map(static fn (float $val): float => $val / $norm, $vector);
    }
}
