<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Tests\Syntax\FacturX;

use IntrAgile\EInvoicing\Rules\FacturX\RuleFiles;
use IntrAgile\EInvoicing\Syntax\FacturX\AfRelationship;
use IntrAgile\EInvoicing\Syntax\FacturX\FacturXProfile;
use IntrAgile\EInvoicing\Syntax\XRechnung;
use PHPUnit\Framework\TestCase;

final class FacturXProfileTest extends TestCase
{
    public function test_guideline_ids(): void
    {
        self::assertSame('urn:factur-x.eu:1p0:minimum', FacturXProfile::Minimum->guidelineId());
        self::assertSame('urn:factur-x.eu:1p0:basicwl', FacturXProfile::BasicWl->guidelineId());
        self::assertSame('urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:basic', FacturXProfile::Basic->guidelineId());
        self::assertSame('urn:cen.eu:en16931:2017', FacturXProfile::En16931->guidelineId());
        self::assertSame('urn:cen.eu:en16931:2017#conformant#urn:factur-x.eu:1p0:extended', FacturXProfile::Extended->guidelineId());
        self::assertSame(XRechnung::CIUS, FacturXProfile::XRechnung->guidelineId());
    }

    public function test_conformance_levels(): void
    {
        self::assertSame(
            ['MINIMUM', 'BASIC WL', 'BASIC', 'EN 16931', 'EXTENDED', 'XRECHNUNG'],
            array_map(static fn (FacturXProfile $profile): string => $profile->value, FacturXProfile::cases()),
        );
    }

    public function test_from_guideline_id_round_trip(): void
    {
        foreach (FacturXProfile::cases() as $profile) {
            self::assertSame($profile, FacturXProfile::fromGuidelineId($profile->guidelineId()));
            self::assertSame($profile, FacturXProfile::fromGuidelineId("  {$profile->guidelineId()}\n"));
        }
        self::assertSame(FacturXProfile::XRechnung, FacturXProfile::fromGuidelineId(XRechnung::EXTENSION));
    }

    public function test_old_xrechnung_id_is_unknown(): void
    {
        self::assertNull(FacturXProfile::fromGuidelineId('urn:cen.eu:en16931:2017#compliant#urn:xoev-de:kosit:standard:xrechnung_2.1'));
        self::assertNull(FacturXProfile::fromGuidelineId(XRechnung::CVD));
        self::assertNull(FacturXProfile::fromGuidelineId('URN:FACTUR-X.EU:1P0:MINIMUM'));
        self::assertNull(FacturXProfile::fromGuidelineId(''));
    }

    public function test_attachment_names_and_xmp_versions(): void
    {
        foreach (FacturXProfile::cases() as $profile) {
            $xrechnung = $profile === FacturXProfile::XRechnung;
            self::assertSame($xrechnung ? 'xrechnung.xml' : 'factur-x.xml', $profile->attachmentName());
            self::assertSame($xrechnung ? '3.0' : '1.0', $profile->xmpVersion());
        }
    }

    public function test_default_relationships(): void
    {
        self::assertSame(AfRelationship::Data, FacturXProfile::Minimum->defaultRelationship());
        self::assertSame(AfRelationship::Data, FacturXProfile::BasicWl->defaultRelationship());
        self::assertSame(AfRelationship::Alternative, FacturXProfile::Basic->defaultRelationship());
        self::assertSame(AfRelationship::Alternative, FacturXProfile::En16931->defaultRelationship());
        self::assertSame(AfRelationship::Alternative, FacturXProfile::Extended->defaultRelationship());
        self::assertSame(AfRelationship::Alternative, FacturXProfile::XRechnung->defaultRelationship());
        self::assertSame(['Data', 'Alternative', 'Source'], array_map(static fn (AfRelationship $r): string => $r->value, AfRelationship::cases()));
    }

    public function test_lines_and_rule_profiles(): void
    {
        self::assertFalse(FacturXProfile::Minimum->hasLines());
        self::assertFalse(FacturXProfile::BasicWl->hasLines());
        self::assertTrue(FacturXProfile::Basic->hasLines());
        self::assertTrue(FacturXProfile::En16931->hasLines());
        self::assertTrue(FacturXProfile::Extended->hasLines());
        self::assertTrue(FacturXProfile::XRechnung->hasLines());

        $ruleProfiles = array_map(static fn (FacturXProfile $profile): ?string => $profile->ruleProfile(), FacturXProfile::cases());
        self::assertSame([...RuleFiles::profiles(), null], $ruleProfiles);
    }
}
