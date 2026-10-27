<?php

namespace App\Tests\Controller;

use App\Entity\Analysis;
use App\Enum\AnalysisStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Validates routes and functional behavior for analysis workspace controller
 */
class AnalysisControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    /**
     * Initializes the test client, entity manager, and authenticates as administrator
     */
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $this->client->request('GET', '/login');
        $this->client->submitForm('Sign in', [
            '_username' => 'admin@promisegap.ai',
            '_password' => 'admin123',
        ]);
    }

    /**
     * Verifies that the index page displays the list of analysis workspaces
     */
    public function testIndexDisplaysAnalysesList(): void
    {
        $this->client->request('GET', '/analyses');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Commercial Commitment Audits');
    }

    /**
     * Verifies that the creation page displays the audit workspace creation form
     */
    public function testNewPageDisplaysCreationForm(): void
    {
        $crawler = $this->client->request('GET', '/analysis/new');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="analysis"]');
        $this->assertCount(1, $crawler->filter('input[name="analysis[title]"]'));
        $this->assertCount(1, $crawler->filter('input[name="analysis[proposalFile]"]'));
    }

    /**
     * Verifies creation of an analysis workspace without any uploaded documents
     */
    public function testCreateAnalysisWithoutFiles(): void
    {
        $this->client->request('GET', '/analysis/new');

        $this->client->submitForm('Run Audit', [
            'analysis[title]' => 'Enterprise SaaS Audit Q4',
        ]);

        $this->assertResponseRedirects();
        $crawler = $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Enterprise SaaS Audit Q4');
    }

    /**
     * Verifies creation of an analysis workspace with uploaded proposal and technical specifications
     */
    public function testCreateAnalysisWithUploadedFiles(): void
    {
        $proposalPath = tempnam(sys_get_temp_dir(), 'test_prop_') . '.pdf';
        file_put_contents($proposalPath, '%PDF-1.4 test proposal');

        $specPath = tempnam(sys_get_temp_dir(), 'test_spec_') . '.md';
        file_put_contents($specPath, '# Technical Architecture Spec');

        $proposalFile = new UploadedFile($proposalPath, 'commercial_offer.pdf', 'application/pdf', null, true);
        $specFile = new UploadedFile($specPath, 'architecture_spec.md', 'text/markdown', null, true);

        $this->client->request('GET', '/analysis/new');

        $this->client->submitForm('Run Audit', [
            'analysis[title]' => 'Multi Document Audit Test',
            'analysis[proposalFile]' => $proposalFile,
            'analysis[technicalSpecFiles]' => [$specFile],
        ]);

        $this->assertResponseRedirects();
        $crawler = $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Multi Document Audit Test');
        $this->assertSelectorTextContains('body', basename($proposalPath));
        $this->assertSelectorTextContains('body', basename($specPath));

        @unlink($proposalPath);
        @unlink($specPath);
    }

    /**
     * Verifies deletion of an existing analysis workspace
     */
    public function testDeleteAnalysis(): void
    {
        $analysis = new Analysis();
        $analysis->setTitle('Temporary Audit Workspace To Delete');
        $analysis->setStatus(AnalysisStatus::PENDING);

        $this->entityManager->persist($analysis);
        $this->entityManager->flush();

        $analysisId = $analysis->getId();

        $crawler = $this->client->request('GET', '/analysis/' . $analysisId);
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[action$="/delete"]')->form();
        $this->client->submit($form);

        $this->assertResponseRedirects('/analyses');
        $this->client->followRedirect();

        $this->entityManager->clear();
        $deletedAnalysis = $this->entityManager->find(Analysis::class, $analysisId);
        $this->assertNull($deletedAnalysis);
    }
}
