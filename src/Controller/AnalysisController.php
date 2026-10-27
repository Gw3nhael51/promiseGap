<?php

namespace App\Controller;

use App\Entity\Analysis;
use App\Enum\DocumentType;
use App\Form\AnalysisType;
use App\Repository\AnalysisRepository;
use App\Service\DocumentUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller managing commercial commitment audit workspaces and document ingestion
 */
class AnalysisController extends AbstractController
{
    /**
     * Lists existing analysis workspaces ordered by creation date
     *
     * @param AnalysisRepository $analysisRepository Repository for querying analysis records
     * @return Response HTTP response rendering the index template
     */
    #[Route('/analyses', name: 'app_analysis_index', methods: ['GET'])]
    public function index(AnalysisRepository $analysisRepository): Response
    {
        $analyses = $analysisRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('analysis/index.html.twig', [
            'analyses' => $analyses,
        ]);
    }

    /**
     * Handles the creation of a new audit workspace and uploads attached documents
     *
     * @param Request $request Current HTTP request
     * @param EntityManagerInterface $entityManager Doctrine entity manager for persistence
     * @param DocumentUploader $documentUploader Service handling secure document uploads
     * @return Response HTTP response with redirect or form display
     */
    #[Route('/analysis/new', name: 'app_analysis_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        DocumentUploader $documentUploader,
    ): Response {
        $analysis = new Analysis();
        $form = $this->createForm(AnalysisType::class, $analysis);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $proposalFile */
            $proposalFile = $form->get('proposalFile')->getData();
            if ($proposalFile !== null) {
                $proposalDoc = $documentUploader->upload($proposalFile, DocumentType::PROPOSAL);
                $analysis->addDocument($proposalDoc);
            }

            /** @var array<UploadedFile> $technicalSpecFiles */
            $technicalSpecFiles = $form->get('technicalSpecFiles')->getData() ?? [];
            foreach ($technicalSpecFiles as $techFile) {
                if ($techFile !== null) {
                    $techDoc = $documentUploader->upload($techFile, DocumentType::TECHNICAL_SPEC);
                    $analysis->addDocument($techDoc);
                }
            }

            $entityManager->persist($analysis);
            $entityManager->flush();

            $this->addFlash('success', 'Analysis workspace created successfully.');

            return $this->redirectToRoute('app_analysis_show', [
                'id' => $analysis->getId(),
            ]);
        }

        return $this->render('analysis/new.html.twig', [
            'analysis' => $analysis,
            'form' => $form,
        ]);
    }

    /**
     * Displays a specific audit workspace with its attached documents
     *
     * @param Analysis $analysis Analysis workspace entity matching the route id
     * @return Response HTTP response rendering the workspace details
     */
    #[Route('/analysis/{id}', name: 'app_analysis_show', methods: ['GET'])]
    public function show(Analysis $analysis): Response
    {
        return $this->render('analysis/show.html.twig', [
            'analysis' => $analysis,
        ]);
    }

    /**
     * Deletes an audit workspace and removes associated physical files
     *
     * @param Request $request Current HTTP request containing CSRF token
     * @param Analysis $analysis Analysis workspace entity to delete
     * @param EntityManagerInterface $entityManager Doctrine entity manager
     * @param DocumentUploader $documentUploader Service for physical file deletion
     * @return Response HTTP response redirecting to index
     */
    #[Route('/analysis/{id}/delete', name: 'app_analysis_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Analysis $analysis,
        EntityManagerInterface $entityManager,
        DocumentUploader $documentUploader,
    ): Response {
        $token = $request->getPayload()->getString('_token');
        if ($this->isCsrfTokenValid('delete' . $analysis->getId(), $token)) {
            foreach ($analysis->getDocuments() as $document) {
                $documentUploader->remove($document);
            }

            $entityManager->remove($analysis);
            $entityManager->flush();

            $this->addFlash('success', 'Analysis workspace deleted successfully.');
        }

        return $this->redirectToRoute('app_analysis_index');
    }
}
