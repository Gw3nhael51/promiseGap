<?php

namespace App\Tests\Controller;

use App\Entity\Analysis;
use App\Enum\AnalysisStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Validates rendering and response status across all mockup UI routes
 */
class MockupRoutesTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    /**
     * Initializes the test client and entity manager
     */
    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * Verifies that the public landing page renders successfully
     */
    public function testLandingPage(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Your proposal says 99.99%. Your specs say 99.5%.');
    }

    /**
     * Verifies that the cockpit requires authentication and redirects anonymous visitors
     */
    public function testCockpitRequiresAuthentication(): void
    {
        // Unauthenticated visitor is redirected to login
        $this->client->request('GET', '/cockpit');
        $this->assertResponseRedirects('http://localhost/login');

        // Authenticated user can access the cockpit
        $this->client->request('GET', '/login');
        $this->client->submitForm('Sign in', [
            '_username' => 'auditor@promisegap.ai',
            '_password' => 'password123',
        ]);
        $this->assertResponseRedirects('/cockpit');
        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'What was sold versus what is written.');
        $this->assertSelectorExists('#cockpit-form');
    }

    /**
     * Verifies that anonymous visitors get 1 free demo trial and second trial prompts registration
     */
    public function testAnonymousDemoQuota(): void
    {
        // 1st demo trial succeeds
        $demoPage = $this->client->request('GET', '/demo');
        $this->client->submit($demoPage->filter('form[action$="/demo/open/cloud-hosting"]')->form());
        $this->assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Auditing the contract');

        // 2nd demo trial redirects to register
        $this->client->submit($demoPage->filter('form[action$="/demo/open/fintech-api"]')->form());
        $this->assertResponseRedirects('/register');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'You have used your 1 free demo trial');
    }

    /**
     * Verifies that audit progress and results views render with claims and evidence
     */
    public function testAuditProgressAndResults(): void
    {
        $analysis = new Analysis();
        $analysis->setTitle('Test SaaS Audit Workspace');
        $analysis->setStatus(AnalysisStatus::COMPLETED);

        $this->entityManager->persist($analysis);
        $this->entityManager->flush();

        // Test progress view
        $this->client->request('GET', '/audit/' . $analysis->getId() . '/progress');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Auditing the contract');

        // Test results view with drawer and risk gauge
        $this->client->request('GET', '/audit/' . $analysis->getId() . '/results');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Test SaaS Audit Workspace');
        $this->assertSelectorTextContains('body', 'GLOBAL RISK');
        $this->assertSelectorExists('#evidence-drawer');
    }

    /**
     * Verifies that the login page renders and authenticates the default test user
     */
    public function testLoginPage(): void
    {
        $this->client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Welcome back');

        $this->client->submitForm('Sign in', [
            '_username' => 'auditor@promisegap.ai',
            '_password' => 'password123',
        ]);

        $this->assertResponseRedirects('/cockpit');
    }

    /**
     * Verifies that authenticated users see their profile badge and Sign out instead of Sign in across public and audit pages
     */
    public function testAuthenticatedNavigationAcrossPages(): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Sign in', [
            '_username' => 'auditor@promisegap.ai',
            '_password' => 'password123',
        ]);
        $this->assertResponseRedirects('/cockpit');

        $analysis = new Analysis();
        $analysis->setTitle('Auth Header Audit');
        $analysis->setStatus(AnalysisStatus::COMPLETED);
        $analysis->setOwnerIdentifier('auditor@promisegap.ai');
        $this->entityManager->persist($analysis);
        $this->entityManager->flush();

        foreach (['/', '/demo', '/audit/' . $analysis->getId() . '/progress', '/audit/' . $analysis->getId() . '/results'] as $url) {
            $this->client->request('GET', $url);
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('header', 'auditor@promisegap.ai', $url);
            $this->assertSelectorTextContains('header', 'Sign out', $url);
            $this->assertSelectorTextNotContains('header', 'Sign in', $url);
        }
    }

    /**
     * Verifies that the EN / FR locale toggle switches interface and results labels to French
     */
    public function testLocaleSwitcherTranslatesAuditResultsToFrench(): void
    {
        $analysis = new Analysis();
        $analysis->setTitle('Audit Bilingue');
        $analysis->setStatus(AnalysisStatus::COMPLETED);
        $this->entityManager->persist($analysis);
        $this->entityManager->flush();

        $this->client->request('GET', '/locale/fr', [], [], [
            'HTTP_REFERER' => 'http://localhost/audit/' . $analysis->getId() . '/results',
        ]);
        $this->assertResponseRedirects('/audit/' . $analysis->getId() . '/results');
        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'RISQUE GLOBAL');
        $this->assertSelectorTextContains('body', 'Promesses & Engagements');
        $this->assertSelectorTextContains('body', 'Contradictoire');
    }

    /**
     * Verifies that the registration page renders and processes signup
     */
    public function testRegisterPage(): void
    {
        $this->client->request('GET', '/register');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Create your account');

        $this->client->submitForm('Create account', [
            'name' => 'Auditor Jane',
            'email' => 'jane@deal-audit.com',
            'company' => 'Audit Corp',
            'password' => 'SecurePass12345!',
        ]);

        $this->assertResponseRedirects();
        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Welcome to PromiseGap');
    }

    /**
     * Verifies password recovery requests
     */
    public function testForgotPasswordPage(): void
    {
        $this->client->request('GET', '/forgot-password');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Forgot your password?');

        $this->client->submitForm('Send reset link', [
            'email' => 'jane@deal-audit.com',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Check your inbox');
    }

    /**
     * Verifies password reset execution
     */
    public function testResetPasswordPage(): void
    {
        $this->client->request('GET', '/reset-password');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Choose a new password');

        $this->client->submitForm('Update password', [
            'password' => 'NewSecurePassword123!',
            'confirm' => 'NewSecurePassword123!',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Password updated');
    }

    /**
     * Verifies welcome onboarding wizard
     */
    public function testWelcomePage(): void
    {
        $this->client->request('GET', '/welcome');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Welcome to PromiseGap');

        $this->client->submitForm('Continue to Cockpit →', [
            'workspace' => 'Legal Audit Lab',
            'role' => 'legal',
        ]);

        $this->assertResponseRedirects('/cockpit');
    }
}
