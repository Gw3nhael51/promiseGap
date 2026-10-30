<?php

namespace App\Controller;

use App\Entity\Analysis;
use App\Form\AnalysisType;
use App\Repository\AnalysisRepository;
use App\Security\AnalysisVoter;
use App\Service\AnalysisCreator;
use App\Service\DocumentUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller managing commercial commitment audit workspaces and document ingestion
 */
class AnalysisController extends AbstractController
{
    /**
     * Lists existing analysis workspaces ordered by creation date with pagination (administrators only)
     *
     * @param Request $request Current HTTP request containing optional page query parameter
     * @param AnalysisRepository $analysisRepository Repository for querying analysis records
     * @return Response HTTP response rendering the index template
     */
    #[Route('/analyses', name: 'app_analysis_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(Request $request, AnalysisRepository $analysisRepository): Response
    {
        $page = $request->query->getInt('page', 1);
        $pagination = $analysisRepository->findPaginated(null, $page, 9);

        return $this->render('analysis/index.html.twig', [
            'analyses' => $pagination['items'],
            'pagination' => $pagination,
        ]);
    }

    /**
     * Displays and handles the analysis workspace creation form
     *
     * @param Request $request Current HTTP request
     * @param AnalysisCreator $analysisCreator Service creating the analysis and its documents
     * @return Response HTTP response rendering the form or redirecting to the created analysis
     */
    #[Route('/analysis/new', name: 'app_analysis_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, AnalysisCreator $analysisCreator): Response
    {
        $analysis = new Analysis();
        $form = $this->createForm(AnalysisType::class, $analysis);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $analysisCreator->create(
                    $analysis,
                    $form->get('proposalFile')->getData(),
                    $form->get('technicalSpecFiles')->getData() ?? [],
                    $this->getUser()->getUserIdentifier(),
                );

                $this->addFlash('success', 'Analysis workspace created successfully.');

                return $this->redirectToRoute('app_analysis_show', [
                    'id' => $analysis->getId(),
                ]);
            } catch (\InvalidArgumentException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('analysis/new.html.twig', [
            'analysis' => $analysis,
            'form' => $form,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * Displays details of a single analysis workspace
     *
     * @param Analysis $analysis Analysis entity resolved from the route
     * @return Response HTTP response rendering the details template
     */
    #[Route('/analysis/{id}', name: 'app_analysis_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(AnalysisVoter::VIEW, subject: 'analysis')]
    public function show(Analysis $analysis): Response
    {
        return $this->render('analysis/show.html.twig', [
            'analysis' => $analysis,
        ]);
    }

    /**
     * Deletes an analysis workspace and its stored documents
     *
     * @param Request $request Current HTTP request containing the CSRF token
     * @param Analysis $analysis Analysis entity resolved from the route
     * @param EntityManagerInterface $entityManager Doctrine entity manager
     * @param DocumentUploader $documentUploader Service managing physical files
     * @return Response HTTP redirect to the audit list
     */
    #[Route('/analysis/{id}/delete', name: 'app_analysis_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(AnalysisVoter::DELETE, subject: 'analysis')]
    public function delete(
        Request $request,
        Analysis $analysis,
        EntityManagerInterface $entityManager,
        DocumentUploader $documentUploader,
    ): Response {
        $token = $request->getPayload()->getString('_token');
        if ($this->isCsrfTokenValid('delete' . $analysis->getId(), $token)) {
            $documents = $analysis->getDocuments()->toArray();

            $entityManager->remove($analysis);
            $entityManager->flush();

            foreach ($documents as $document) {
                $documentUploader->remove($document);
            }

            $this->addFlash('success', 'Analysis workspace deleted successfully.');
        }

        return $this->redirectToRoute($this->isGranted('ROLE_ADMIN') ? 'app_analysis_index' : 'app_cockpit');
    }

    /**
     * Deletes one or multiple selected analysis workspaces and their stored documents in bulk
     *
     * @param Request $request Current HTTP request containing the CSRF token and selected IDs
     * @param AnalysisRepository $analysisRepository Repository used to load selected analyses
     * @param EntityManagerInterface $entityManager Doctrine entity manager
     * @param DocumentUploader $documentUploader Service managing physical files
     * @return Response HTTP redirect to the admin audit list
     */
    #[Route('/analyses/bulk-delete', name: 'app_analysis_bulk_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function bulkDelete(
        Request $request,
        AnalysisRepository $analysisRepository,
        EntityManagerInterface $entityManager,
        DocumentUploader $documentUploader,
    ): Response {
        $token = $request->getPayload()->getString('_token');
        if (!$this->isCsrfTokenValid('bulk_delete_analyses', $token)) {
            $this->addFlash('error', 'Invalid security token for bulk deletion.');

            return $this->redirectToRoute('app_analysis_index');
        }

        /** @var array<int, scalar> $rawIds */
        $rawIds = $request->request->all('ids');
        $ids = array_values(array_unique(array_filter(array_map('intval', $rawIds), static fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            $this->addFlash('warning', 'Please select at least one audit workspace to delete.');

            return $this->redirectToRoute('app_analysis_index');
        }

        $analyses = $analysisRepository->findBy(['id' => $ids]);
        $documentsToRemove = [];
        $deletedCount = 0;

        foreach ($analyses as $analysis) {
            if (!$this->isGranted(AnalysisVoter::DELETE, $analysis)) {
                continue;
            }

            foreach ($analysis->getDocuments() as $document) {
                $documentsToRemove[] = $document;
            }

            $entityManager->remove($analysis);
            ++$deletedCount;
        }

        $entityManager->flush();

        foreach ($documentsToRemove as $document) {
            $documentUploader->remove($document);
        }

        if ($deletedCount > 0) {
            $this->addFlash('success', sprintf('%d audit workspace(s) deleted successfully.', $deletedCount));
        }

        return $this->redirectToRoute('app_analysis_index');
    }
}
