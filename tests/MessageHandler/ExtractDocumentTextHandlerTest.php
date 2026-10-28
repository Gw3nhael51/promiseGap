<?php

namespace App\Tests\MessageHandler;

use App\Entity\Analysis;
use App\Entity\Document;
use App\Enum\ExtractionStatus;
use App\Message\ExtractDocumentText;
use App\MessageHandler\ExtractDocumentTextHandler;
use App\Service\AnalysisCreator;
use App\Service\DocumentUploader;
use App\Tests\Support\PdfFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Validates the full flow from upload to extracted text through Messenger
 */
class ExtractDocumentTextHandlerTest extends KernelTestCase
{
    /**
     * Creates an analysis through the real creator and returns it with the messages sent
     *
     * @param UploadedFile $proposal Proposal file
     * @param UploadedFile[] $specs Technical specification files
     * @return Analysis Persisted analysis
     */
    private function createAnalysis(UploadedFile $proposal, array $specs = []): Analysis
    {
        $analysis = new Analysis();
        $analysis->setTitle('Extraction flow ' . bin2hex(random_bytes(4)));
        static::getContainer()->get(AnalysisCreator::class)->create($analysis, $proposal, $specs, 'auditor@promisegap.ai');

        return $analysis;
    }

    /**
     * Verifies that creating an analysis queues one extraction message per document
     */
    public function testCreatorQueuesOneMessagePerDocument(): void
    {
        self::bootKernel();
        $pdf = PdfFactory::write(['Proposal promises ninety nine point nine nine availability']);
        $md = tempnam(sys_get_temp_dir(), 'spec_');
        file_put_contents($md, '# Spec');

        $this->createAnalysis(
            new UploadedFile($pdf, 'proposal.pdf', 'application/pdf', null, true),
            [new UploadedFile($md, 'spec.md', 'text/markdown', null, true)],
        );

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $messages = array_map(static fn ($envelope) => $envelope->getMessage(), $transport->getSent());
        $this->assertContainsOnlyInstancesOf(ExtractDocumentText::class, $messages);
        $this->assertGreaterThanOrEqual(2, count($messages));
    }

    /**
     * Verifies that the handler fills the text, counters and status of PDF and Markdown documents
     */
    public function testHandlerExtractsPdfAndMarkdown(): void
    {
        self::bootKernel();
        $pdf = PdfFactory::write(['Proposal promises ninety nine point nine nine availability']);
        $md = tempnam(sys_get_temp_dir(), 'spec_');
        file_put_contents($md, "# Specs\n- Target 99.5%");

        $analysis = $this->createAnalysis(
            new UploadedFile($pdf, 'proposal.pdf', 'application/pdf', null, true),
            [new UploadedFile($md, 'spec.md', 'text/markdown', null, true)],
        );

        $handler = static::getContainer()->get(ExtractDocumentTextHandler::class);
        foreach ($analysis->getDocuments() as $document) {
            $handler(new ExtractDocumentText($document->getId()));
        }

        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $documents = static::getContainer()->get(EntityManagerInterface::class)->getRepository(Document::class)->findBy(['analysis' => $analysis->getId()]);

        $this->assertCount(2, $documents);
        foreach ($documents as $document) {
            $this->assertSame(ExtractionStatus::DONE, $document->getExtractionStatus());
            $this->assertNotEmpty($document->getExtractedText());
            $this->assertGreaterThan(0, $document->getCharacterCount());
            $this->assertGreaterThan(0, $document->getEstimatedTokens());
            $this->assertNull($document->getExtractionError());
        }
    }

    /**
     * Verifies that a scanned PDF is marked as failed with an explanation instead of crashing
     */
    public function testScannedPdfIsMarkedAsFailed(): void
    {
        self::bootKernel();
        $pdf = PdfFactory::write(['']);

        $analysis = $this->createAnalysis(new UploadedFile($pdf, 'scan.pdf', 'application/pdf', null, true));
        $document = $analysis->getDocuments()->first();

        static::getContainer()->get(ExtractDocumentTextHandler::class)(new ExtractDocumentText($document->getId()));

        $this->assertSame(ExtractionStatus::FAILED, $document->getExtractionStatus());
        $this->assertStringContainsString('scanned', $document->getExtractionError());
        $this->assertNull($document->getExtractedText());
    }

    /**
     * Verifies that the handler ignores deleted documents and already processed documents
     */
    public function testHandlerIgnoresMissingAndProcessedDocuments(): void
    {
        self::bootKernel();
        $handler = static::getContainer()->get(ExtractDocumentTextHandler::class);

        $handler(new ExtractDocumentText(999999999));

        $md = tempnam(sys_get_temp_dir(), 'spec_');
        file_put_contents($md, '# Once');
        $analysis = $this->createAnalysis(new UploadedFile($md, 'once.md', 'text/markdown', null, true));
        $document = $analysis->getDocuments()->first();
        $handler(new ExtractDocumentText($document->getId()));
        $document->setExtractedText('# Edited after extraction');
        $handler(new ExtractDocumentText($document->getId()));

        $this->assertSame('# Edited after extraction', $document->getExtractedText());
        $this->assertNotNull(static::getContainer()->get(DocumentUploader::class)->getTargetDirectory());
    }
}
