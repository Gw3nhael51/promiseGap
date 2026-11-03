<?php

namespace App\Service;

use App\Entity\Analysis;
use App\Enum\AnalysisStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * Provides preloaded demonstration deals and evidence claims for instant evaluation
 */
class DemoDealService
{
    private const string COLOR_RISK_HIGH_FG = '#FF7B7B';
    private const string COLOR_RISK_HIGH_BG = 'rgba(255,123,123,0.14)';
    private const string SEVERITY_HIGH_FR = 'Élevée';
    private const string STATUS_UNSUPPORTED_FR = 'Non supporté';

    /**
     * Initializes the demo deal service with entity manager dependency
     *
     * @param EntityManagerInterface $entityManager Doctrine entity manager for database operations
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LockFactory $lockFactory,
    ) {
    }

    /**
     * Returns the catalog of available preloaded demo deals
     *
     * @param string $locale Active interface locale ('en' or 'fr')
     * @return array<string, array<string, mixed>> List of available deals with metadata
     */
    public function getCatalog(string $locale = 'en'): array
    {
        if ($locale === 'fr') {
            return [
                'cloud-hosting' => [
                    'id' => 'cloud-hosting',
                    'title' => 'Contrat d’hébergement Cloud',
                    'tag' => 'HÉBERGEMENT CLOUD',
                    'risk' => 'Risque élevé',
                    'riskColor' => self::COLOR_RISK_HIGH_FG,
                    'riskBg' => self::COLOR_RISK_HIGH_BG,
                    'riskScore' => 78,
                    'description' => '8 promesses. La proposition vend 99,99 % de disponibilité alors que la spec cible 99,5 %.',
                    'proposalName' => 'Proposition_v3.pdf',
                    'specsName' => 'SpecsTech_v7.pdf',
                    'promisesCount' => 8,
                    'passagesCount' => 142,
                ],
                'fintech-api' => [
                    'id' => 'fintech-api',
                    'title' => 'Intégration Core Banking',
                    'tag' => 'FINTECH / PAIEMENT',
                    'risk' => 'Risque élevé',
                    'riskColor' => self::COLOR_RISK_HIGH_FG,
                    'riskBg' => self::COLOR_RISK_HIGH_BG,
                    'riskScore' => 84,
                    'description' => '6 promesses. La proposition annonce un règlement temps réel sans frais ; l’architecture prévoit un batch J+1.',
                    'proposalName' => 'PropositionCommerciale_v2.pdf',
                    'specsName' => 'BankingAPI_Architecture_v4.pdf',
                    'promisesCount' => 6,
                    'passagesCount' => 98,
                ],
                'healthcare-saas' => [
                    'id' => 'healthcare-saas',
                    'title' => 'Portail patient Santé',
                    'tag' => 'SANTÉ / HDS & HIPAA',
                    'risk' => 'Risque modéré',
                    'riskColor' => '#F5B04A',
                    'riskBg' => 'rgba(245,176,74,0.14)',
                    'riskScore' => 52,
                    'description' => '10 promesses. La proposition promet la conformité HIPAA & HDS ; la spec ne confirme qu’un hébergement UE standard.',
                    'proposalName' => 'Proposition_Sante.md',
                    'specsName' => 'Specs_Securite_HDS.pdf',
                    'promisesCount' => 10,
                    'passagesCount' => 176,
                ],
            ];
        }

        return [
            'cloud-hosting' => [
                'id' => 'cloud-hosting',
                'title' => 'Cloud hosting contract',
                'tag' => 'CLOUD HOSTING',
                'risk' => 'High risk',
                'riskColor' => self::COLOR_RISK_HIGH_FG,
                'riskBg' => self::COLOR_RISK_HIGH_BG,
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
                'riskColor' => self::COLOR_RISK_HIGH_FG,
                'riskBg' => self::COLOR_RISK_HIGH_BG,
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
     * @param string $locale Active interface locale ('en' or 'fr')
     * @return array<string, mixed> Complete discrepancy and evidence dataset
     */
    public function getCloudHostingDealData(string $locale = 'en'): array
    {
        if ($locale === 'fr') {
            return [
                'deal' => $this->getCatalog('fr')['cloud-hosting'],
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
                        'label' => 'Contradictoire',
                        'sev' => 'Critique',
                        'conf' => '96%',
                        'sim' => '0.93',
                        'promise' => 'Disponibilité de 99,99 % avec pénalités',
                        'pb' => 'La plateforme garantit ',
                        'pm' => '99,99 % de disponibilité',
                        'pa' => ' assortie de pénalités financières.',
                        'psrc' => 'Proposition §2.1 · p.4',
                        'hasEv' => true,
                        'eb' => 'La disponibilité mensuelle cible est de ',
                        'em' => '99,5 %',
                        'ea' => ', hors maintenances planifiées.',
                        'esrc' => 'Specs Tech §4.2 SLA · p.31',
                        'why' => 'La proposition s’engage sur 99,99 % d’uptime. La spécification ne cible que 99,5 %, ce qui autorise environ 3h36 d’indisponibilité par mois au lieu de 4 minutes.',
                    ],
                    [
                        'id' => 'P2',
                        'st' => 'con',
                        'label' => 'Contradictoire',
                        'sev' => self::SEVERITY_HIGH_FR,
                        'conf' => '91%',
                        'sim' => '0.89',
                        'promise' => 'Livraison clé en main en 3 mois',
                        'pb' => 'La mise en production complète est ',
                        'pm' => 'garantie sous 3 mois',
                        'pa' => ' après signature.',
                        'psrc' => 'Proposition §1.4 · p.3',
                        'hasEv' => true,
                        'eb' => 'Les phases 1 à 3 sont estimées à ',
                        'em' => '7 mois',
                        'ea' => ' incluant la recette.',
                        'esrc' => 'Specs Tech §8 Planning · p.64',
                        'why' => 'La feuille de route technique prévoit 7 mois avant recette, soit plus du double du délai promis par le commercial.',
                    ],
                    [
                        'id' => 'P3',
                        'st' => 'uns',
                        'label' => self::STATUS_UNSUPPORTED_FR,
                        'sev' => self::SEVERITY_HIGH_FR,
                        'conf' => '88%',
                        'sim' => '0.41',
                        'promise' => 'Sauvegarde temps réel de toutes les données',
                        'pb' => 'Toutes les données sont protégées par une ',
                        'pm' => 'sauvegarde en temps réel',
                        'pa' => ' sans fenêtre de perte.',
                        'psrc' => 'Proposition §3.4 · p.7',
                        'hasEv' => false,
                        'esrc' => 'Section la plus proche : Specs Tech §6.1 Stockage · p.42',
                        'why' => 'Aucun passage des spécifications ne décrit la fréquence de sauvegarde ou le RPO. La section la plus proche ne traite que des classes de stockage.',
                    ],
                    [
                        'id' => 'P4',
                        'st' => 'amb',
                        'label' => 'Ambigu',
                        'sev' => 'Moyenne',
                        'conf' => '74%',
                        'sim' => '0.82',
                        'promise' => 'Chiffrement de bout en bout',
                        'pb' => 'Les données clients bénéficient d’un ',
                        'pm' => 'chiffrement de bout en bout',
                        'pa' => '.',
                        'psrc' => 'Proposition §3.2 · p.6',
                        'hasEv' => true,
                        'eb' => 'Les données sont chiffrées via ',
                        'em' => 'TLS 1.3 en transit et AES-256 au repos',
                        'ea' => '.',
                        'esrc' => 'Specs Tech §5.3 Sécurité · p.37',
                        'why' => 'Le chiffrement en transit et au repos est documenté, mais rien n’indique que les clés restent sous le contrôle exclusif du client (E2EE).',
                    ],
                    [
                        'id' => 'P5',
                        'st' => 'uns',
                        'label' => self::STATUS_UNSUPPORTED_FR,
                        'sev' => self::SEVERITY_HIGH_FR,
                        'conf' => '85%',
                        'sim' => '0.38',
                        'promise' => 'Support 24/7, réponse en 15 minutes',
                        'pb' => 'Notre équipe assure un ',
                        'pm' => 'support 24/7 avec temps de réponse de 15 minutes',
                        'pa' => '.',
                        'psrc' => 'Proposition §4.2 · p.9',
                        'hasEv' => false,
                        'esrc' => 'Section la plus proche : Specs Tech §9 Exploitation · p.71',
                        'why' => 'La section exploitation décrit les outils de supervision mais ne définit ni horaires de support ni temps de réponse garanti.',
                    ],
                    [
                        'id' => 'P6',
                        'st' => 'sup',
                        'label' => 'Supporté',
                        'sev' => 'Faible',
                        'conf' => '94%',
                        'sim' => '0.95',
                        'promise' => 'Hébergement certifié ISO 27001',
                        'pb' => 'L’hébergement est opéré dans des ',
                        'pm' => 'datacenters certifiés ISO 27001',
                        'pa' => '.',
                        'psrc' => 'Proposition §3.1 · p.6',
                        'hasEv' => true,
                        'eb' => 'La production est hébergée dans des ',
                        'em' => 'datacenters certifiés ISO 27001',
                        'ea' => ' à Paris et Francfort.',
                        'esrc' => 'Specs Tech §3.1 Hébergement · p.18',
                        'why' => 'La spécification confirme explicitement la certification ISO 27001 et précise les localisations d’hébergement.',
                    ],
                    [
                        'id' => 'P7',
                        'st' => 'sup',
                        'label' => 'Supporté',
                        'sev' => 'Faible',
                        'conf' => '92%',
                        'sim' => '0.91',
                        'promise' => 'Résidence des données en UE (RGPD)',
                        'pb' => 'Toutes les données clients restent ',
                        'pm' => 'au sein de l’Union européenne',
                        'pa' => ', conformément au RGPD.',
                        'psrc' => 'Proposition §3.3 · p.7',
                        'hasEv' => true,
                        'eb' => 'Toutes les données de production sont stockées ',
                        'em' => 'dans la région eu-west-3',
                        'ea' => ' sans réplication hors UE.',
                        'esrc' => 'Specs Tech §3.4 Résidence des données · p.21',
                        'why' => 'La région de stockage et les règles de réplication dans les specs confirment la promesse de résidence UE.',
                    ],
                    [
                        'id' => 'P8',
                        'st' => 'uns',
                        'label' => self::STATUS_UNSUPPORTED_FR,
                        'sev' => 'Moyenne',
                        'conf' => '79%',
                        'sim' => '0.47',
                        'promise' => 'Scalabilité illimitée',
                        'pb' => 'L’architecture offre une ',
                        'pm' => 'scalabilité illimitée',
                        'pa' => ' selon la croissance de votre usage.',
                        'psrc' => 'Proposition §2.3 · p.5',
                        'hasEv' => false,
                        'esrc' => 'Section la plus proche : Specs Tech §4.5 Capacité · p.35',
                        'why' => 'Les specs décrivent l’autoscaling mais ne documentent aucune capacité illimitée.',
                    ],
                ],
            ];
        }

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

        $lock = $this->lockFactory->createLock('demo-analysis-'.$deal['id']);
        $lock->acquire(true);

        try {
            $repository = $this->entityManager->getRepository(Analysis::class);
            $existing = $repository->findOneBy(['title' => $deal['title'], 'ownerIdentifier' => null]);
            if ($existing !== null) {
                return $existing;
            }

            $analysis = new Analysis();
            $analysis->setTitle($deal['title']);
            $analysis->setStatus(AnalysisStatus::COMPLETED);

            $this->entityManager->persist($analysis);
            $this->entityManager->flush();
        } finally {
            $lock->release();
        }

        return $analysis;
    }
}
