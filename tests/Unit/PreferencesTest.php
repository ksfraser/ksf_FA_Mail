<?php

declare(strict_types=1);

namespace Ksfraser\Tests\Unit\Mail;

use PHPUnit\Framework\TestCase;
use Ksfraser\FA\Mail\Preferences;

/**
 * Preferences are stored in the shared cross-module store.
 *
 * @covers \Ksfraser\FA\Mail\Preferences
 */
class PreferencesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $GLOBALS['_test_prefs'] = [];
        $GLOBALS['_test_sql'] = [];
        $GLOBALS['_test_unknown_tables'] = [];
        unset($_SESSION['wa_current_user'], $_SESSION['user_id']);
    }

    protected function tearDown(): void
    {
        unset($_SESSION['wa_current_user'], $_SESSION['user_id']);

        parent::tearDown();
    }

    public function testSetThenGetRoundTrips(): void
    {
        $prefs = new Preferences();

        $this->assertTrue($prefs->set('signature', 'Regards, KSF'));
        $this->assertSame('Regards, KSF', $prefs->get('signature'));
    }

    public function testGetReturnsDefaultWhenUnset(): void
    {
        $prefs = new Preferences();

        $this->assertSame('fallback', $prefs->get('missing_key', 'fallback'));
    }

    public function testValueIsStoredUnderTheModuleNameSegment(): void
    {
        $prefs = new Preferences();
        $prefs->set('signature', 'Regards, KSF');

        $this->assertArrayHasKey('ksf_FA_Mail', $GLOBALS['_test_prefs']);
        $this->assertArrayHasKey('signature', $GLOBALS['_test_prefs']['ksf_FA_Mail']['']);
    }

    public function testWriteTargetsTheSharedStore(): void
    {
        $prefs = new Preferences();
        $prefs->set('signature', 'Regards, KSF');

        $inserts = array_values(array_filter(
            $GLOBALS['_test_sql'],
            static function (string $sql): bool {
                return stripos($sql, 'INSERT INTO') === 0;
            }
        ));

        $this->assertCount(1, $inserts);
        $this->assertStringContainsString('ksf_module_prefs', $inserts[0]);
        $this->assertStringNotContainsString('fa_preference_values', $inserts[0]);
    }

    public function testReadTargetsTheSharedStore(): void
    {
        $prefs = new Preferences();
        $prefs->get('signature', 'fallback');

        $selects = array_values(array_filter(
            $GLOBALS['_test_sql'],
            static function (string $sql): bool {
                return stripos($sql, 'SELECT') === 0;
            }
        ));

        $this->assertCount(1, $selects);
        $this->assertStringContainsString('ksf_module_prefs', $selects[0]);
        $this->assertStringNotContainsString('fa_preference_values', $selects[0]);
    }

    public function testNoStatementAddressesAnUnknownTable(): void
    {
        $prefs = new Preferences();
        $prefs->set('signature', 'Regards, KSF');
        $prefs->get('signature');

        $this->assertSame([], $GLOBALS['_test_unknown_tables']);
    }

    public function testUserSegmentComesFromTheSignedInUser(): void
    {
        $user = new \stdClass();
        $user->user = 'opencode';
        $_SESSION['wa_current_user'] = $user;

        $prefs = new Preferences();
        $prefs->set('signature', 'Regards, KSF');

        $this->assertArrayHasKey('opencode', $GLOBALS['_test_prefs']['ksf_FA_Mail']);
    }

    public function testPreferenceIsScopedToTheModuleName(): void
    {
        $prefs = new Preferences();

        $this->assertSame('ksf_FA_Mail', $prefs->module_name);
    }
}
