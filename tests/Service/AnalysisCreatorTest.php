<?php

namespace App\Tests\Service;

use App\Entity\Analysis;
use App\Service\AnalysisCreator;
use App\Service\DocumentUploader;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Validates the all-or-nothing behavior of analysis creation
 */
class AnalysisCreatorTest extends KernelTestCase
{
    /**
     * Builds an uploaded file from raw content
     *
     * @param string $content File content
     * @param string $name Client filename
     * @param string $mimeType Client MIME type
     * @return UploadedFile Test uploaded file
     */
    private function makeFile(string $content, string $name, string $mimeType): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'creator_');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, $mimeType, null, true);
    }

    /**
     * Lists stored files in the upload directory
     *
     * @return string[] Filenames present on disk
     */
    private function storedFiles(): array
    {
        $directory = static::getContainer()->get(DocumentUploader::class)->getTargetDirectory();

        return is_dir($directory) ? array_values(array_diff(scandir($directory), ['.', '..'])) : [];
    }

    /**
     * Verifies that files already stored are removed when a later file is rejected
     */
    public function testStoredFilesAreRemovedWhenALaterFileIsRejected(): void
    {
        self::bootKernel();
        $creator = static::getContainer()->get(AnalysisCreator::class);
        $before = $this->storedFiles();

        $analysis = new Analysis();
        $analysis->setTitle('Atomic creation test');

        try {
            $creator->create(
                $analysis,
                $this->makeFile('%PDF-1.4 proposal', 'proposal.pdf', 'application/pdf'),
                [$this->makeFile('MZ binary', 'malware.exe', 'application/x-msdownload')],
                'auditor@promisegap.ai',
            );
            $this->fail('An InvalidArgumentException was expected.');
        } catch (\InvalidArgumentException) {
            $this->assertSame($before, $this->storedFiles());
            $this->assertNull($analysis->getId());
        }
    }

    /**
     * Verifies that too many technical specification files are refused
     */
    public function testTooManyTechnicalSpecFilesAreRefused(): void
    {
        self::bootKernel();
        $creator = static::getContainer()->get(AnalysisCreator::class);
        $files = [];
        for ($i = 0; $i <= AnalysisCreator::MAX_TECHNICAL_SPEC_FILES; ++$i) {
            $files[] = $this->makeFile('# spec', "spec$i.md", 'text/markdown');
        }

        $this->expectException(\InvalidArgumentException::class);
        $creator->create(new Analysis(), null, $files, 'auditor@promisegap.ai');
    }
}
