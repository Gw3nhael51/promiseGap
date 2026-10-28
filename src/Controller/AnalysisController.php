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
     * Lists existing analysis workspaces ordered by creation date (administrators only)
     *
     * @param AnalysisRepository $analysisRepository Repository for querying analysis records
     * @return Response HTTP response rendering the index template
     */
    #[Route('/analyses', name: 'app_analysis_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(AnalysisRepository $analysisRepository): Response
    {
        $analyses = $analysisRepository->findBy([], ['createdAt' => 'DESC']);

        return $this->render('analysis/index.html.twig', [
            'analyses' => $analyses,
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

            // Supprimer : Effacer les fichiers uniquement après la suppression en base
            foreach ($documents as $document) {
                $documentUploader->remove($document);
            }

            $this->addFlash('success', 'Analysis workspace deleted successfully.');
        }

        return $this->redirectToRoute($this->isGranted('ROLE_ADMIN') ? 'app_analysis_index' : 'app_cockpit');
    }
}
