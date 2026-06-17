<?php

declare(strict_types=1);

namespace Ksfraser\Tests\Unit\Mail;

use PHPUnit\Framework\TestCase;
use Ksfraser\FA\Mail\OutboundAccountService;

/**
 * @covers \Ksfraser\FA\Mail\OutboundAccountService
 */
class OutboundAccountServiceTest extends TestCase
{
    public function testGetAvailableAccountsIncludesSystem(): void
    {
        $accounts = OutboundAccountService::getAvailableAccounts('1');
        $values = array_column($accounts, 'value');
        $this->assertContains('system', $values);
    }

    public function testGetAvailableAccountsIncludesPersonal(): void
    {
        $accounts = OutboundAccountService::getAvailableAccounts('1');
        $values = array_column($accounts, 'value');
        $this->assertContains('personal', $values);
    }

    public function testResolveSystemConfigReturnsEmpty(): void
    {
        $config = OutboundAccountService::resolveConfig('1', 'system');
        $this->assertIsArray($config);
        // System config returns [] which means "use company prefs"
        $this->assertEmpty($config);
    }

    public function testResolvePersonalConfigWhenNotConfigured(): void
    {
        $config = OutboundAccountService::resolveConfig('1', 'personal');
        // No personal prefs set up in test environment
        $this->assertIsArray($config);
    }

    public function testRenderSelectorReturnsHtml(): void
    {
        $html = OutboundAccountService::renderSelector('1');
        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('</select>', $html);
    }

    public function testRenderSelectorIncludesSystemSelected(): void
    {
        $html = OutboundAccountService::renderSelector('1', 'system');
        $this->assertStringContainsString('value="system" selected', $html);
    }

    public function testSaveAndGetPersonalConfigRoundTrip(): void
    {
        $data = [
            'mail_type'     => 'SMTP',
            'smtp_host'     => 'smtp.test.com',
            'smtp_port'     => 587,
            'smtp_secure'   => 'tls',
            'smtp_username' => 'user@test.com',
            'smtp_password' => 'secret',
            'bcc_email'     => 'bcc@test.com',
        ];
        OutboundAccountService::savePersonalConfig('test99', $data);
        $config = OutboundAccountService::getPersonalConfig('test99');
        $this->assertSame('SMTP', $config['mail_type']);
        $this->assertSame('smtp.test.com', $config['smtp_host']);
        $this->assertSame(587, $config['smtp_port']);
        $this->assertSame('tls', $config['smtp_secure']);
        $this->assertSame('user@test.com', $config['smtp_username']);
        $this->assertSame('secret', $config['smtp_password']);
        $this->assertSame('bcc@test.com', $config['bcc_email']);
    }

    public function testPersonalPrefsExist(): void
    {
        OutboundAccountService::savePersonalConfig('test98', [
            'smtp_host' => 'smtp.test.com',
        ]);
        $this->assertTrue(OutboundAccountService::personalPrefsExist('test98'));
        OutboundAccountService::savePersonalConfig('test97', [
            'smtp_host' => '',
        ]);
        $this->assertFalse(OutboundAccountService::personalPrefsExist('test97'));
    }
}
