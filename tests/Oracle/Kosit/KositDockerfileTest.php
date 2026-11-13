<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Oracle\Kosit;

use IntrAgile\EInvoicing\Validation\XRechnung\XRechnungSources;
use PHPUnit\Framework\TestCase;

/**
 * Keeps the downloads of the KoSIT oracle image (docker/kosit/Dockerfile) on the artefacts that
 * XRechnungSources pins, so the oracle and the library cannot drift apart.
 */
final class KositDockerfileTest extends TestCase
{
    public function test_dockerfile_downloads_the_pinned_validator_and_configuration(): void
    {
        $archives = XRechnungSources::archives();
        $arguments = self::arguments();

        self::assertSame($archives['validator-jar']['url'], $arguments['KOSIT_VALIDATOR_URL'] ?? null);
        self::assertSame($archives['validator-jar']['sha256'], $arguments['KOSIT_VALIDATOR_SHA256'] ?? null);
        self::assertSame($archives['configuration']['url'], $arguments['KOSIT_CONFIGURATION_URL'] ?? null);
        self::assertSame($archives['configuration']['sha256'], $arguments['KOSIT_CONFIGURATION_SHA256'] ?? null);
    }

    public function test_base_image_is_pinned_by_tag_and_digest(): void
    {
        self::assertMatchesRegularExpression(
            '#^eclipse-temurin:21\.[0-9._]+-jre@sha256:[0-9a-f]{64}$#',
            self::arguments()['BASE_IMAGE'] ?? '',
        );
    }

    /**
     * @return array<string, string> the default value of every ARG line, by name
     */
    private static function arguments(): array
    {
        $dockerfile = file_get_contents(__DIR__.'/../../../docker/kosit/Dockerfile');
        self::assertIsString($dockerfile);
        preg_match_all('/^ARG\s+([A-Z0-9_]+)=(\S+)\s*$/m', $dockerfile, $matches, PREG_SET_ORDER);

        $arguments = [];
        foreach ($matches as $match) {
            $arguments[$match[1]] = $match[2];
        }

        return $arguments;
    }
}
