<?php

declare(strict_types=1);

namespace Ksfraser\Tests\Unit\Mail;

use PHPUnit\Framework\TestCase;
use Ksfraser\FA\Mail\MailerService;

/**
 * @covers \Ksfraser\FA\Mail\MailerService
 */
class MailerServiceTest extends TestCase
{
    public function testConstructWithoutSmtpReturnsInstance(): void
    {
        $service = new MailerService();
        $this->assertInstanceOf(MailerService::class, $service);
    }

    public function testIsAvailableWithoutSmtpReturnsFalse(): void
    {
        $service = new MailerService();
        $this->assertFalse($service->isAvailable());
    }

    public function testSendWithoutSmtpFallsBackGracefully(): void
    {
        $service = new MailerService();
        $result = $service->send(
            'test@example.com',
            'Test User',
            'Test Subject',
            'Test body',
            'from@example.com'
        );
        $this->assertIsBool($result);
    }

    public function testSendIcalWithoutSmtpFallsBackGracefully(): void
    {
        $service = new MailerService();
        $result = $service->sendIcal(
            'test@example.com',
            'Test User',
            'Invitation',
            'You are invited',
            "BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n",
            'from@example.com'
        );
        $this->assertIsBool($result);
    }

    public function testConstructWithSmtpConfig(): void
    {
        $config = [
            'mail_type'     => 'SMTP',
            'smtp_host'     => 'smtp.example.com',
            'smtp_port'     => 587,
            'smtp_secure'   => 'tls',
            'smtp_username' => 'user@example.com',
            'smtp_password' => 'pass',
            'bcc_email'     => 'bcc@example.com',
        ];
        $service = new MailerService($config);
        $this->assertInstanceOf(MailerService::class, $service);
        // PHPMailer should be initialized
        $this->assertTrue($service->isAvailable());
    }

    public function testConstructWithMailTypeMail(): void
    {
        $config = [
            'mail_type' => 'MAIL',
            'smtp_host' => 'smtp.example.com',
        ];
        $service = new MailerService($config);
        $this->assertFalse($service->isAvailable());
    }

    public function testConstructWithEmptyHost(): void
    {
        $config = [
            'mail_type' => 'SMTP',
            'smtp_host' => '',
        ];
        $service = new MailerService($config);
        $this->assertFalse($service->isAvailable());
    }

    public function testSetupControllerEnsuresDefaults(): void
    {
        $controller = new \Ksfraser\FA\Mail\SetupController();
        $count = $controller->ensureDefaults();
        $this->assertIsInt($count);
    }

    public function testSetupControllerGetPrefs(): void
    {
        $controller = new \Ksfraser\FA\Mail\SetupController();
        $controller->ensureDefaults();
        $prefs = $controller->getPrefs();
        $this->assertArrayHasKey('mail_type', $prefs);
        $this->assertArrayHasKey('smtp_host', $prefs);
        $this->assertArrayHasKey('smtp_port', $prefs);
    }

    public function testSetupControllerValidateMailType(): void
    {
        $controller = new \Ksfraser\FA\Mail\SetupController();
        $this->assertNull($controller->validate(['mail_type' => 'MAIL']));
    }

    public function testSetupControllerValidateSmtpMissingHost(): void
    {
        $controller = new \Ksfraser\FA\Mail\SetupController();
        $error = $controller->validate([
            'mail_type' => 'SMTP',
            'smtp_host' => '',
            'smtp_port' => '587',
        ]);
        $this->assertNotNull($error);
    }

    public function testSetupControllerValidateSmtpInvalidPort(): void
    {
        $controller = new \Ksfraser\FA\Mail\SetupController();
        $error = $controller->validate([
            'mail_type' => 'SMTP',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => '0',
        ]);
        $this->assertNotNull($error);
    }

    public function testSetupControllerValidateSmtpValid(): void
    {
        $controller = new \Ksfraser\FA\Mail\SetupController();
        $error = $controller->validate([
            'mail_type' => 'SMTP',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => '587',
        ]);
        $this->assertNull($error);
    }
}
