<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\FacturX;

use IntrAgile\EInvoicing\Syntax\XRechnung;

/**
 * Factur-X / ZUGFeRD profile. The value is the XMP fx:ConformanceLevel.
 *
 * XRechnung is the ZUGFeRD profile that embeds a full XRechnung CII document.
 */
enum FacturXProfile: string
{
    case Minimum = 'MINIMUM';
    case BasicWl = 'BASIC WL';
    case Basic = 'BASIC';
    case En16931 = 'EN 16931';
    case Extended = 'EXTENDED';
    case XRechnung = 'XRECHNUNG';

    /**
     * Guideline identifier (BT-24) a document of this profile carries.
     */
    public function guidelineId(): string
    {
        return match ($this) {
            self::Minimum => 'urn:factur-x.eu:1p0:minimum',
            self::BasicWl => 'urn:factur-x.eu:1p0:basicwl',
            self::Basic => 'urn:cen.eu:en16931:2017#compliant#urn:factur-x.eu:1p0:basic',
            self::En16931 => 'urn:cen.eu:en16931:2017',
            self::Extended => 'urn:cen.eu:en16931:2017#conformant#urn:factur-x.eu:1p0:extended',
            self::XRechnung => XRechnung::CIUS,
        };
    }

    /**
     * The profile whose guideline identifier is exactly $id after trimming surrounding
     * whitespace. The XRechnung 3.0 CIUS and its extension both give XRechnung; any other
     * identifier, including earlier XRechnung releases, gives null.
     */
    public static function fromGuidelineId(string $id): ?self
    {
        $id = trim($id);
        if ($id === XRechnung::EXTENSION) {
            return self::XRechnung;
        }
        foreach (self::cases() as $profile) {
            if ($profile->guidelineId() === $id) {
                return $profile;
            }
        }

        return null;
    }

    /**
     * File name of the embedded XML in the PDF.
     */
    public function attachmentName(): string
    {
        return $this === self::XRechnung ? 'xrechnung.xml' : 'factur-x.xml';
    }

    /**
     * The /AFRelationship of the embedded XML when the caller does not choose one: Data for the
     * Factur-X profiles and Alternative for XRechnung, as the official FeRD ZUGFeRD 2.5.2 example
     * PDFs do (Data in all 61 non-XRechnung example invoices, Alternative in the XRechnung ones).
     * The caller may pass Data, Alternative or Source instead.
     */
    public function defaultRelationship(): AfRelationship
    {
        return $this === self::XRechnung ? AfRelationship::Alternative : AfRelationship::Data;
    }

    /**
     * XMP fx:Version of the document.
     */
    public function xmpVersion(): string
    {
        return $this === self::XRechnung ? '3.0' : '1.0';
    }

    /**
     * Whether documents of this profile carry invoice lines.
     */
    public function hasLines(): bool
    {
        return ! in_array($this, [self::Minimum, self::BasicWl], true);
    }

    /**
     * Profile key of the Factur-X rule sets and schemas, or null for XRechnung, which is
     * validated with the XRechnung rules.
     */
    public function ruleProfile(): ?string
    {
        return match ($this) {
            self::Minimum => 'MINIMUM',
            self::BasicWl => 'BASIC-WL',
            self::Basic => 'BASIC',
            self::En16931 => 'EN16931',
            self::Extended => 'EXTENDED',
            self::XRechnung => null,
        };
    }
}
