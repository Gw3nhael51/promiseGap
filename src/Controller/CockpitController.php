<?php

namespace App\Controller;

use App\Entity\Analysis;
use App\Enum\AnalysisStatus;
use App\Enum\DocumentType;
use App\Form\AnalysisType;
use App\Repository\AnalysisRepository;
use App\Service\DemoDealService;
use App\Service\DocumentUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller handling the dual dropzone audit cockpit and contract submission
 */
class CockpitController extends AbstractController
{
    /**
     * Renders the cockpit workbench and handles document submissions
     *
     * @param Request $request Current HTTP request
     * @param EntityManagerInterface $entityManager Doctrine entity manager for persistence
     * @param DocumentUploader $documentUploader Service for secure file uploads
     * @param AnalysisRepository $analysisRepository Repository to fetch past audits
     * @return Response HTTP response rendering the cockpit or redirecting to audit progress
     */
    #[Route('/cockpit', name: 'app_cockpit', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        DocumentUploader $documentUploader,
        AnalysisRepository $analysisRepository,
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

            $analysis->setStatus(AnalysisStatus::PROCESSING);
            $entityManager->persist($analysis);
            $entityManager->flush();

            return $this->redirectToRoute('app_audit_progress', [
                'id' => $analysis->getId(),
            ]);
        }

        $pastAudits = $analysisRepository->findBy([], ['createdAt' => 'DESC'], 5);

        return $this->render('cockpit/Main.html.twig', [
            'form' => $form,
            'pastAudits' => $pastAudits,
        ]);
    }

    /**
     * Instantly loads the default demo deal and redirects to the audit progress view
     *
     * @param DemoDealService $demoDealService Service providing preloaded contract data
     * @return Response HTTP redirect response to the progress view
     */
    #[Route('/cockpit/demo', name: 'app_cockpit_demo', methods: ['GET', 'POST'])]
    public function loadDemo(DemoDealService $demoDealService): Response
    {
        $demoAnalysis = $demoDealService->getOrCreateDemoAnalysis('cloud-hosting');

        return $this->redirectToRoute('app_audit_progress', [
            'id' => $demoAnalysis->getId(),
        ]);
    }
}
