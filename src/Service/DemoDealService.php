<?php

namespace App\Service;

use App\Entity\Analysis;
use App\Enum\AnalysisStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Provides preloaded demonstration deals and evidence claims for instant evaluation
 */
class DemoDealService
{
    /**
     * Initializes the demo deal service with entity manager dependency
     *
     * @param EntityManagerInterface $entityManager Doctrine entity manager for database operations
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Returns the catalog of available preloaded demo deals
     *
     * @return array<string, array<string, mixed>> List of available deals with metadata
     */
    public function getCatalog(): array
    {
        return [
            'cloud-hosting' => [
                'id' => 'cloud-hosting',
                'title' => 'Cloud hosting contract',
                'tag' => 'CLOUD HOSTING',
                'risk' => 'High risk',
                'riskColor' => '#FF7B7B',
                'riskBg' => 'rgba(255,123,123,0.14)',
                'riskScore' => 78,
                'description' => '8 promises. The proposal sells 99.99% availability while the specs target 99.5%.',
                'proposalName' => 'Proposal_v3.pdf',
                'specsName' => 'TechSpecs_v7.pdf',
                'promisesCount' => 8,
                'passagesCount' => 142,
            ],
            'fintech-api' => [
                'id' => 'fintech-api',
                'title' => 'Core banking integration',
                'tag' => 'FINTECH / PAYMENT',
                'risk' => 'High risk',
                'riskColor' => '#FF7B7B',
                'riskBg' => 'rgba(255,123,123,0.14)',
                'riskScore' => 84,
                'description' => '6 promises. The proposal claims real-time settlement with zero fees; the architecture doc specifies T+1 batch processing.',
                'proposalName' => 'SalesProposal_v2.pdf',
                'specsName' => 'BankingAPI_Architecture_v4.pdf',
                'promisesCount' => 6,
                'passagesCount' => 98,
            ],
            'healthcare-saas' => [
                'id' => 'healthcare-saas',
                'title' => 'Healthcare patient portal',
                'tag' => 'HEALTHCARE / HIPAA',
                'risk' => 'Medium risk',
                'riskColor' => '#F5B04A',
                'riskBg' => 'rgba(245,176,74,0.14)',
                'riskScore' => 52,
                'description' => '10 promises. The proposal promises complete HIPAA & HDS compliance; data residency specs only confirm standard EU hosting.',
                'proposalName' => 'CommercialProposal_Health.md',
                'specsName' => 'Security_Specs_HIPAA.pdf',
                'promisesCount' => 10,
                'passagesCount' => 176,
            ],
        ];
    }

    /**
     * Returns structured promises and evidence findings for the cloud hosting demo contract
     *
     * @return array<string, mixed> Complete discrepancy and evidence dataset
     */
    public function getCloudHostingDealData(): array
    {
        return [
            'deal' => $this->getCatalog()['cloud-hosting'],
            'stats' => [
                'con' => 2,
                'uns' => 3,
                'amb' => 1,
                'sup' => 2,
            ],
            'claims' => [
                [
                    'id' => 'P1',
                    'st' => 'con',
                    'label' => 'Contradictory',
                    'sev' => 'Critical',
                    'conf' => '96%',
                    'sim' => '0.93',
                    'promise' => '99.99% availability with penalties',
                    'pb' => 'The platform delivers ',
                    'pm' => '99.99% availability',
                    'pa' => ' backed by financial penalties.',
                    'psrc' => 'Proposal §2.1 · p.4',
                    'hasEv' => true,
                    'eb' => 'Target monthly availability is ',
                    'em' => '99.5%',
                    'ea' => ', excluding planned maintenance.',
                    'esrc' => 'Tech Specs §4.2 SLA · p.31',
                    'why' => 'The proposal commits to 99.99% uptime. The specification only targets 99.5%, which allows about 3.6 hours of downtime per month instead of about 4 minutes.',
                ],
                [
                    'id' => 'P2',
                    'st' => 'con',
                    'label' => 'Contradictory',
                    'sev' => 'High',
                    'conf' => '91%',
                    'sim' => '0.89',
                    'promise' => 'Turnkey delivery in 3 months',
                    'pb' => 'Full go-live is ',
                    'pm' => 'guaranteed within 3 months',
                    'pa' => ' of signature.',
                    'psrc' => 'Proposal §1.4 · p.3',
                    'hasEv' => true,
                    'eb' => 'Phases 1 to 3 are estimated at ',
                    'em' => '7 months',
                    'ea' => ' including acceptance testing.',
                    'esrc' => 'Tech Specs §8 Roadmap · p.64',
                    'why' => 'The roadmap adds up to 7 months before acceptance, more than twice the delivery window promised in the proposal.',
                ],
                [
                    'id' => 'P3',
                    'st' => 'uns',
                    'label' => 'Unsupported',
                    'sev' => 'High',
                    'conf' => '88%',
                    'sim' => '0.41',
                    'promise' => 'Real-time backup of all data',
                    'pb' => 'All data is protected by ',
                    'pm' => 'real-time backup',
                    'pa' => ' with no loss window.',
                    'psrc' => 'Proposal §3.4 · p.7',
                    'hasEv' => false,
                    'esrc' => 'Closest section: Tech Specs §6.1 Storage · p.42',
                    'why' => 'No passage in the specifications describes backup frequency or recovery objectives. The closest section covers storage tiers only.',
                ],
                [
                    'id' => 'P4',
                    'st' => 'amb',
                    'label' => 'Ambiguous',
                    'sev' => 'Medium',
                    'conf' => '74%',
                    'sim' => '0.82',
                    'promise' => 'End-to-end encryption',
                    'pb' => 'Customer data benefits from ',
                    'pm' => 'end-to-end encryption',
                    'pa' => '.',
                    'psrc' => 'Proposal §3.2 · p.6',
                    'hasEv' => true,
                    'eb' => 'Data is encrypted with ',
                    'em' => 'TLS 1.3 in transit and AES-256 at rest',
                    'ea' => '.',
                    'esrc' => 'Tech Specs §5.3 Security · p.37',
                    'why' => 'Encryption in transit and at rest is documented, but nothing states that keys stay with the customer. Whether this meets end-to-end encryption depends on the contract definition.',
                ],
                [
                    'id' => 'P5',
                    'st' => 'uns',
                    'label' => 'Unsupported',
                    'sev' => 'High',
                    'conf' => '85%',
                    'sim' => '0.38',
                    'promise' => '24/7 support, 15-minute response',
                    'pb' => 'Our team offers ',
                    'pm' => '24/7 support with a 15-minute response time',
                    'pa' => '.',
                    'psrc' => 'Proposal §4.2 · p.9',
                    'hasEv' => false,
                    'esrc' => 'Closest section: Tech Specs §9 Operations · p.71',
                    'why' => 'The operations section describes the monitoring stack but defines no support hours or response times.',
                ],
                [
                    'id' => 'P6',
                    'st' => 'sup',
                    'label' => 'Supported',
                    'sev' => 'Low',
                    'conf' => '94%',
                    'sim' => '0.95',
                    'promise' => 'ISO 27001 certified hosting',
                    'pb' => 'Hosting runs in ',
                    'pm' => 'ISO 27001 certified data centers',
                    'pa' => '.',
                    'psrc' => 'Proposal §3.1 · p.6',
                    'hasEv' => true,
                    'eb' => 'Production is hosted in ',
                    'em' => 'ISO 27001 certified data centers',
                    'ea' => ' in Paris and Frankfurt.',
                    'esrc' => 'Tech Specs §3.1 Hosting · p.18',
                    'why' => 'The specification repeats the certification claim and names the hosting locations.',
                ],
                [
                    'id' => 'P7',
                    'st' => 'sup',
                    'label' => 'Supported',
                    'sev' => 'Low',
                    'conf' => '92%',
                    'sim' => '0.91',
                    'promise' => 'EU data residency (GDPR)',
                    'pb' => 'All customer data stays ',
                    'pm' => 'within the European Union',
                    'pa' => ', in line with GDPR.',
                    'psrc' => 'Proposal §3.3 · p.7',
                    'hasEv' => true,
                    'eb' => 'All production data is stored ',
                    'em' => 'in the eu-west-3 region',
                    'ea' => ' with no cross-border replication.',
                    'esrc' => 'Tech Specs §3.4 Data residency · p.21',
                    'why' => 'Storage region and replication rules in the specs support the residency promise.',
                ],
                [
                    'id' => 'P8',
                    'st' => 'uns',
                    'label' => 'Unsupported',
                    'sev' => 'Medium',
                    'conf' => '79%',
                    'sim' => '0.47',
                    'promise' => 'Unlimited scalability',
                    'pb' => 'The architecture offers ',
                    'pm' => 'unlimited scalability',
                    'pa' => ' as your usage grows.',
                    'psrc' => 'Proposal §2.3 · p.5',
                    'hasEv' => false,
                    'esrc' => 'Closest section: Tech Specs §4.5 Capacity · p.35',
                    'why' => 'The specs describe autoscaling but define no upper limit, so "unlimited" is not backed by any documented capacity.',
                ],
            ],
        ];
    }

    /**
     * Creates or retrieves a persistent demo analysis entity
     *
     * @param string $dealKey Key identifier of the demo deal
     * @return Analysis Hydrated Analysis entity
     */
    public function getOrCreateDemoAnalysis(string $dealKey = 'cloud-hosting'): Analysis
    {
        $deals = $this->getCatalog();
        $deal = $deals[$dealKey] ?? $deals['cloud-hosting'];

        $existing = $this->entityManager->getRepository(Analysis::class)->findOneBy(['title' => $deal['title']]);
        if ($existing !== null) {
            return $existing;
        }

        $analysis = new Analysis();
        $analysis->setTitle($deal['title']);
        $analysis->setStatus(AnalysisStatus::COMPLETED);

        $this->entityManager->persist($analysis);
        $this->entityManager->flush();

        return $analysis;
    }
}
