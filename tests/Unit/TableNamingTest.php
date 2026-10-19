<?php

declare(strict_types=1);

namespace Ksfraser\Tests\Unit\Mail;

use PHPUnit\Framework\TestCase;

/**
 * Every table this module addresses must exist.
 *
 * FrontAccounting's db_query() returns false — and raises no error — when a
 * statement fails, so a module that addresses a table it never created fails
 * *silently*: reads come back empty and writes go nowhere.  That is not
 * hypothetical.  This module shipped a preference store named
 * `TB_PREF . 'fa_preference_values'` while the schema file created a table
 * called `fa_preference_values`, and the schema file was never wired into
 * the activation path either, so no release of the module ever had the table
 * its code was reading and writing.
 *
 * These tests compare the tables the code addresses with the tables the
 * module's schema creates, so that class of failure fails the build instead
 * of the mail server.
 */
class TableNamingTest extends TestCase
{
    /**
     * Tables owned by another package, not by this module's schema.
     *
     * The shared preference store is created by whichever module owns the
     * shared schema (see AGENTS_ARCH.md section 12).
     *
     * @var string[]
     */
    private const EXTERNAL_TABLES = [
        'ksf_module_prefs',
    ];

    /**
     * The legacy name this module must never use again.
     *
     * @var string
     */
    private const LEGACY_TABLE = 'fa_preference_values';

    public function testEveryAddressedTableIsCreatedByTheModuleSchema(): void
    {
        $schema = $this->schemaTables();
        $referenced = $this->referencedTables();

        $this->assertNotEmpty($referenced, 'No table references found — the scan is broken.');

        foreach ($referenced as $table) {
            $this->assertTrue(
                in_array('0_' . $table, $schema, true)
                    || in_array($table, $schema, true)
                    || in_array($table, self::EXTERNAL_TABLES, true),
                sprintf(
                    'Table "%s" is addressed by the module but no schema file creates it. '
                    . 'Schema creates: %s. A statement against a table that does not exist '
                    . 'fails silently in FrontAccounting.',
                    $table,
                    $schema === [] ? '(none)' : implode(', ', $schema)
                )
            );
        }
    }

    public function testLegacyPreferenceTableIsGone(): void
    {
        foreach ($this->moduleFiles() as $file) {
            $contents = $this->code((string) file_get_contents($file));
            $this->assertStringNotContainsString(
                self::LEGACY_TABLE,
                $contents,
                sprintf('%s still references the legacy preference table.', $file)
            );
        }
    }

    public function testSchemaFilesUseTheCompanyPrefix(): void
    {
        foreach ($this->schemaFiles() as $file) {
            $contents = (string) file_get_contents($file);
            preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([A-Za-z0-9_]+)`?/i', $contents, $matches);
            foreach ($matches[1] as $table) {
                $this->assertStringStartsWith(
                    '0_',
                    $table,
                    sprintf(
                        '%s creates "%s" without the company prefix; db_import() only replaces '
                        . 'a literal 0_, so the code (which reads TB_PREF . "<name>") would '
                        . 'never find it.',
                        $file,
                        $table
                    )
                );
            }
        }
    }

    public function testAccountTableIsTheModulesOwnStore(): void
    {
        $schema = $this->schemaTables();

        $this->assertContains('0_ksf_mail_accounts', $schema);
    }

    /**
     * Tables created by this module's schema files.
     *
     * @return string[]
     */
    private function schemaTables(): array
    {
        $tables = [];
        foreach ($this->schemaFiles() as $file) {
            $contents = (string) file_get_contents($file);
            preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([A-Za-z0-9_]+)`?/i', $contents, $matches);
            foreach ($matches[1] as $table) {
                $tables[] = strtolower($table);
            }
        }

        return array_values(array_unique($tables));
    }

    /**
     * Tables the module's code addresses.
     *
     * @return string[]
     */
    private function referencedTables(): array
    {
        $tables = [];
        foreach ($this->moduleFiles() as $file) {
            $contents = $this->code((string) file_get_contents($file));

            // TB_PREF . 'some_table'
            if (preg_match_all("/TB_PREF\s*\.\s*'([a-z0-9_]+)'/i", $contents, $matches)) {
                foreach ($matches[1] as $table) {
                    $tables[] = strtolower($table);
                }
            }

            // const SOME_TABLE = 'some_table'
            if (preg_match_all("/const\s+[A-Z0-9_]*TABLE\s*=\s*'([a-z0-9_]+)'/i", $contents, $matches)) {
                foreach ($matches[1] as $table) {
                    $tables[] = strtolower($table);
                }
            }

            // Table names inside SQL string literals.
            if (preg_match_all('/["\']((?:SELECT|INSERT\s+INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO)[^"\']*)["\']/is', $contents, $matches)) {
                foreach ($matches[1] as $statement) {
                    if (preg_match_all('/\b(?:FROM|INTO|UPDATE)\s+`?([a-z0-9_]+)`?/i', $statement, $found)) {
                        foreach ($found[1] as $table) {
                            $tables[] = strtolower($table);
                        }
                    }
                }
            }
        }

        return array_values(array_unique($tables));
    }

    /**
     * Source with comments removed.
     *
     * Comments are prose about the history of a table name; only executable
     * code may address one.
     *
     * @param string $source PHP source
     * @return string
     */
    private function code(string $source): string
    {
        $out = '';
        $length = strlen($source);
        $inString = null;

        for ($i = 0; $i < $length; $i++) {
            $char = $source[$i];
            $next = $i + 1 < $length ? $source[$i + 1] : '';

            if ($inString !== null) {
                $out .= $char;
                if ($char === '\\') {
                    if ($next !== '') {
                        $out .= $next;
                        $i++;
                    }
                    continue;
                }
                if ($char === $inString) {
                    $inString = null;
                }
                continue;
            }

            if ($char === "'" || $char === '"') {
                $inString = $char;
                $out .= $char;
                continue;
            }

            if ($char === '/' && $next === '/') {
                while ($i < $length && $source[$i] !== "\n") {
                    $i++;
                }
                $out .= "\n";
                continue;
            }

            if ($char === '#') {
                while ($i < $length && $source[$i] !== "\n") {
                    $i++;
                }
                $out .= "\n";
                continue;
            }

            if ($char === '/' && $next === '*') {
                $end = strpos($source, '*/', $i + 2);
                $i = $end === false ? $length : $end + 1;
                $out .= ' ';
                continue;
            }

            $out .= $char;
        }

        return $out;
    }

    /**
     * The module's source files.
     *
     * @return string[]
     */
    private function moduleFiles(): array
    {
        $root = dirname(__DIR__, 2);
        $files = [$root . '/hooks.php'];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root . '/src', \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * The module's schema files.
     *
     * @return string[]
     */
    private function schemaFiles(): array
    {
        $root = dirname(__DIR__, 2);
        $files = glob($root . '/sql/*.sql');
        if ($files === false) {
            return [];
        }

        return $files;
    }
}
