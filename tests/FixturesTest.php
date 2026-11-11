<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests;

use DOMDocument;
use PHPUnit\Framework\TestCase;

final class FixturesTest extends TestCase
{
    public function test_official_examples_present(): void
    {
        foreach (['ubl', 'cii'] as $syntax) {
            $files = Fixtures::official($syntax);

            self::assertNotEmpty($files, "No official {$syntax} examples found.");

            foreach ($files as $file) {
                self::assertFileIsReadable($file);
                $document = new DOMDocument;
                self::assertTrue($document->load($file, LIBXML_NONET), "{$file} is not well-formed XML.");
                self::assertNotNull($document->documentElement);
            }
        }
    }

    public function test_official_validation_stylesheets_present(): void
    {
        self::assertFileIsReadable(Fixtures::officialXslt('ubl'));
        self::assertFileIsReadable(Fixtures::officialXslt('cii'));
    }
}
