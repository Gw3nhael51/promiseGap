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
        $this->assertCount(1, $crawler->filter('input[name="analysis[proposalFile][]"]'));
    }

    /**
     * Verifies that creating an analysis without the commercial proposal is rejected
     */
    public function testCreateAnalysisWithoutProposalIsRejected(): void
    {
        $this->client->request('GET', '/analysis/new');

        $this->client->submitForm('Run Audit', [
            'analysis[title]' => 'Enterprise SaaS Audit Q4',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('body', 'Please upload the commercial proposal.');
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
            'analysis[proposalFile]' => [$proposalFile],
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

    /**
     * Verifies bulk deletion of multiple selected analysis workspaces from /analyses
     */
    public function testBulkDeleteAnalyses(): void
    {
        $first = new Analysis();
        $first->setTitle('Bulk Delete Workspace 1');
        $first->setStatus(AnalysisStatus::PENDING);

        $second = new Analysis();
        $second->setTitle('Bulk Delete Workspace 2');
        $second->setStatus(AnalysisStatus::COMPLETED);

        $keep = new Analysis();
        $keep->setTitle('Keep Workspace 3');
        $keep->setStatus(AnalysisStatus::COMPLETED);

        $this->entityManager->persist($first);
        $this->entityManager->persist($second);
        $this->entityManager->persist($keep);
        $this->entityManager->flush();

        $firstId = $first->getId();
        $secondId = $second->getId();
        $keepId = $keep->getId();

        $crawler = $this->client->request('GET', '/analyses');
        $this->assertResponseIsSuccessful();

        $token = $crawler->filter('#bulk-delete-form input[name="_token"]')->attr('value');
        $this->client->request('POST', '/analyses/bulk-delete', [
            '_token' => $token,
            'ids' => [(string) $firstId, (string) $secondId],
        ]);

        $this->assertResponseRedirects('/analyses');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', '2 audit workspace(s) deleted successfully.');

        $this->entityManager->clear();
        $this->assertNull($this->entityManager->find(Analysis::class, $firstId));
        $this->assertNull($this->entityManager->find(Analysis::class, $secondId));
        $this->assertNotNull($this->entityManager->find(Analysis::class, $keepId));
    }

    /**
     * Verifies pagination navigation and out-of-bounds page clamping on /analyses
     */
    public function testAnalysesPaginationAndBoundaryClamping(): void
    {
        for ($i = 1; $i <= 11; ++$i) {
            $analysis = new Analysis();
            $analysis->setTitle(sprintf('Paginated Admin Workspace %02d', $i));
            $analysis->setStatus(AnalysisStatus::COMPLETED);
            $this->entityManager->persist($analysis);
        }
        $this->entityManager->flush();

        // Page 1 displays pagination bar
        $this->client->request('GET', '/analyses?page=1');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('nav[aria-label="Pagination"]');
        $this->assertSelectorTextContains('nav[aria-label="Pagination"]', 'Page 1 /');

        // Page 2 renders remaining workspaces
        $this->client->request('GET', '/analyses?page=2');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('nav[aria-label="Pagination"]', 'Page 2 /');

        // Out-of-bounds page=0 and page=999 clamp safely without 500 or empty error
        $this->client->request('GET', '/analyses?page=0');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('nav[aria-label="Pagination"]', 'Page 1 /');

        $this->client->request('GET', '/analyses?page=999');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('nav[aria-label="Pagination"]');
    }
}
