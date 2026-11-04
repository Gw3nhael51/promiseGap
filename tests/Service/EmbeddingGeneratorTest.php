<?php

namespace App\Tests\Service;

use App\Service\EmbeddingGenerator;
use PHPUnit\Framework\TestCase;

class EmbeddingGeneratorTest extends TestCase
{
    public function testEmbedReturnsNormalizedVectorOfConfiguredDimension(): void
    {
        $generator = new EmbeddingGenerator(128);
        $vector = $generator->embed('99.99% uptime availability SLA guarantee');

        $this->assertCount(128, $vector);

        $normSq = 0.0;
        foreach ($vector as $val) {
            $normSq += $val * $val;
        }

        $this->assertEqualsWithDelta(1.0, sqrt($normSq), 1e-4);
    }

    public function testToPgVectorLiteral(): void
    {
        $generator = new EmbeddingGenerator(3);
        $literal = $generator->toPgVectorLiteral([0.1234567, -0.654321, 0.0]);

        $this->assertStringStartsWith('[', $literal);
        $this->assertStringEndsWith(']', $literal);
        $this->assertStringContainsString('0.123457', $literal);
        $this->assertStringContainsString('-0.654321', $literal);
    }

    public function testEmptyStringYieldsValidNormalizedVector(): void
    {
        $generator = new EmbeddingGenerator(64);
        $vector = $generator->embed('');

        $this->assertCount(64, $vector);
        $this->assertEquals(1.0, $vector[0]);
    }
}
