<?php

namespace App\Tests\Security;

use App\Entity\Analysis;
use App\Enum\AnalysisStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Validates ownership rules, CSRF protection and authentication hardening
 */
class AnalysisAccessTest extends WebTestCase
{
    private const string AUDITOR = 'auditor@promisegap.ai';
    private const string OTHER = 'other-user@promisegap.ai';

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    /**
     * Initializes the test client and entity manager
     */
    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Use a unique client IP per test to isolate login throttling counters
        $this->client->setServerParameter('REMOTE_ADDR', sprintf('10.%d.%d.%d', random_int(1, 254), random_int(1, 254), random_int(1, 254)));
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * Creates and persists an analysis for a given owner
     *
     * @param string|null $owner Owner identifier, null for a shared demo analysis
     * @return Analysis Persisted analysis
     */
    private function createAnalysis(?string $owner): Analysis
    {
        $analysis = new Analysis();
        $analysis->setTitle('Access test ' . bin2hex(random_bytes(4)));
        $analysis->setStatus(AnalysisStatus::COMPLETED);
        $analysis->setOwnerIdentifier($owner);

        $this->entityManager->persist($analysis);
        $this->entityManager->flush();

        return $analysis;
    }

    /**
     * Authenticates the test client through the login form
     *
     * @param string $identifier User identifier
     * @param string $password User password
     */
    private function loginAs(string $identifier, string $password): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Sign in', ['_username' => $identifier, '_password' => $password]);
    }

    /**
     * Verifies that anonymous visitors cannot read a private analysis through any route
     */
    public function testAnonymousCannotViewPrivateAnalysis(): void
    {
        $analysis = $this->createAnalysis(self::AUDITOR);

        foreach (['/analysis/' . $analysis->getId(), '/audit/' . $analysis->getId() . '/progress', '/audit/' . $analysis->getId() . '/results'] as $url) {
            $this->client->request('GET', $url);
            $this->assertResponseRedirects('http://localhost/login', null, $url);
        }
    }

    /**
     * Verifies that a user cannot read or delete the analysis of another user
     */
    public function testUserCannotAccessAnotherUsersAnalysis(): void
    {
        $analysis = $this->createAnalysis(self::OTHER);
        $this->loginAs(self::AUDITOR, 'password123');

        $this->client->request('GET', '/analysis/' . $analysis->getId());
        $this->assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/audit/' . $analysis->getId() . '/results');
        $this->assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/analysis/' . $analysis->getId() . '/delete');
        $this->assertResponseStatusCodeSame(403);
    }

    /**
     * Verifies that the owner and administrators can read a private analysis
     */
    public function testOwnerAndAdminCanViewAnalysis(): void
    {
        $analysis = $this->createAnalysis(self::AUDITOR);

        $this->loginAs(self::AUDITOR, 'password123');
        $this->client->request('GET', '/analysis/' . $analysis->getId());
        $this->assertResponseIsSuccessful();

        $this->loginAs('admin@promisegap.ai', 'admin123');
        $this->client->request('GET', '/audit/' . $analysis->getId() . '/results');
        $this->assertResponseIsSuccessful();
    }

    /**
     * Verifies that shared demo analyses are readable by anyone but deletable only by administrators
     */
    public function testDemoAnalysisIsReadOnlyForUsers(): void
    {
        $analysis = $this->createAnalysis(null);

        $this->client->request('GET', '/audit/' . $analysis->getId() . '/results');
        $this->assertResponseIsSuccessful();

        $this->loginAs(self::AUDITOR, 'password123');
        $crawler = $this->client->request('GET', '/analysis/' . $analysis->getId());
        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('form[action$="/delete"]'));

        $this->client->request('POST', '/analysis/' . $analysis->getId() . '/delete');
        $this->assertResponseStatusCodeSame(403);
    }

    /**
     * Verifies that unknown analysis identifiers return 404 instead of creating demo data
     */
    public function testUnknownAnalysisReturnsNotFound(): void
    {
        $this->client->request('GET', '/audit/999999999/results');
        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * Verifies that analysis creation is reserved to authenticated users
     */
    public function testAnonymousCannotCreateAnalysis(): void
    {
        $this->client->request('GET', '/analysis/new');
        $this->assertResponseRedirects('http://localhost/login');
    }

    /**
     * Verifies that deletion with an invalid CSRF token keeps the analysis
     */
    public function testDeleteWithInvalidCsrfTokenKeepsAnalysis(): void
    {
        $analysis = $this->createAnalysis(self::AUDITOR);
        $id = $analysis->getId();
        $this->loginAs(self::AUDITOR, 'password123');

        $this->client->request('POST', '/analysis/' . $id . '/delete', ['_token' => 'forged']);

        $this->entityManager->clear();
        $this->assertNotNull($this->entityManager->find(Analysis::class, $id));
    }

    /**
     * Verifies that the cockpit lists only the audits owned by the current user
     */
    public function testCockpitListsOnlyOwnAudits(): void
    {
        $own = $this->createAnalysis(self::AUDITOR);
        $foreign = $this->createAnalysis(self::OTHER);
        $this->loginAs(self::AUDITOR, 'password123');

        $this->client->request('GET', '/cockpit');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', $own->getTitle());
        $this->assertSelectorTextNotContains('body', $foreign->getTitle());
    }

    /**
     * Verifies that the demo trial cannot be opened without a valid CSRF token
     */
    public function testDemoOpenRequiresCsrfToken(): void
    {
        $this->client->request('GET', '/demo/open/cloud-hosting');
        $this->assertResponseStatusCodeSame(405);

        $this->client->request('POST', '/demo/open/cloud-hosting', ['_token' => 'forged']);
        $this->assertResponseStatusCodeSame(403);
    }

    /**
     * Verifies that a login attempt without CSRF token is refused
     */
    public function testLoginWithoutCsrfTokenIsRefused(): void
    {
        $this->client->request('POST', '/login', [
            '_username' => self::AUDITOR,
            '_password' => 'password123',
        ]);
        $this->assertResponseRedirects('/login');

        $this->client->request('GET', '/cockpit');
        $this->assertResponseRedirects('http://localhost/login');
    }

    /**
     * Verifies that repeated failed logins are throttled
     */
    public function testLoginIsThrottledAfterRepeatedFailures(): void
    {
        $username = 'throttle-' . bin2hex(random_bytes(4)) . '@example.com';

        for ($attempt = 0; $attempt < 7; ++$attempt) {
            $this->client->request('GET', '/login');
            $this->client->submitForm('Sign in', ['_username' => $username, '_password' => 'wrong']);
        }

        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'Too many failed login attempts');
    }

    /**
     * Verifies that forms of the stub account flow reject a missing CSRF token
     */
    public function testAccountFormsRequireCsrfToken(): void
    {
        foreach (['/register', '/forgot-password', '/reset-password', '/welcome'] as $url) {
            $this->client->request('POST', $url, ['name' => 'x']);
            $this->assertResponseStatusCodeSame(403, $url);
        }
    }

    /**
     * Verifies that the admin console (/analyses) is restricted to ROLE_ADMIN and denied to ROLE_USER
     */
    public function testAdminConsoleRequiresAdminRole(): void
    {
        $this->loginAs(self::AUDITOR, 'password123');
        $this->client->request('GET', '/analyses');
        $this->assertResponseStatusCodeSame(403);

        $this->loginAs('admin@promisegap.ai', 'admin123');
        $this->client->request('GET', '/analyses');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Commercial Commitment Audits');
    }
}
