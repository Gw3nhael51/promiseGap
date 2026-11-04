<?php

namespace App\Tests\Controller;

use App\Tests\Support\PdfFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Validates the end-to-end user flow from Cockpit document upload to RAG discrepancy Results view
 */
class AuditPipelineFlowTest extends WebTestCase
{
    /**
     * Verifies that uploading a proposal and specification in the Cockpit runs extraction, pgvector and RAG qualification
     */
    public function testCockpitUploadRunsFullRagPipelineAndRendersResults(): void
    {
        $client = static::createClient();

        $client->request('GET', '/login');
        $client->submitForm('Sign in', [
            '_username' => 'auditor@promisegap.ai',
            '_password' => 'password123',
        ]);
        $client->followRedirect();

        $proposalPdf = PdfFactory::write([
            'We guarantee 99.99% monthly platform availability across all production regions.',
        ]);

        $specMd = tempnam(sys_get_temp_dir(), 'spec_') . '.md';
        file_put_contents($specMd, "# SLA Specification\nMonthly platform availability target is 99.5% across production regions.\n");

        $proposalFile = new UploadedFile($proposalPdf, 'sales_proposal.pdf', 'application/pdf', null, true);
        $specFile = new UploadedFile($specMd, 'tech_specs.md', 'text/markdown', null, true);

        $client->request('GET', '/cockpit');
        $client->submitForm('Run Audit', [
            'analysis[title]' => 'End-to-End RAG Cloud Deal',
            'analysis[proposalFile]' => [$proposalFile],
            'analysis[technicalSpecFiles]' => [$specFile],
        ]);

        $this->assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        $this->assertMatchesRegularExpression('#^/audit/\d+/progress$#', $location);

        // Follow to /audit/{id}/progress (triggers synchronous fallback extraction + RAG pipeline)
        $client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Request /audit/{id}/results and verify extracted commitment & contradictory finding are rendered
        $resultsUrl = str_replace('/progress', '/results', $location);
        $client->request('GET', $resultsUrl);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'End-to-End RAG Cloud Deal');
        $this->assertSelectorTextContains('body', '99.99%');
        $this->assertSelectorTextContains('body', 'Contradictory');

        @unlink($proposalPdf);
        @unlink($specMd);
    }
}
