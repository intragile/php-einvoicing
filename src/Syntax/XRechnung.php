<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax;

use IntrAgile\EInvoicing\Model\ProcessControl;

/**
 * Identifiers of the German CIUS XRechnung 3.0.
 *
 * An invoice that carries XRechnung::processControl() is written by UblWriter and CiiWriter with
 * the XRechnung specification identifier (BT-24) and the Peppol billing profile (BT-23), and is
 * validated with XRechnungValidator.
 */
final class XRechnung
{
    /** Specification identifier (BT-24) of the XRechnung 3.0 CIUS. */
    public const CIUS = 'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0';

    /** Specification identifier (BT-24) of the XRechnung 3.0 Extension. */
    public const EXTENSION = self::CIUS.'#conformant#urn:xeinkauf.de:kosit:extension:xrechnung_3.0';

    /** Specification identifier (BT-24) of the XRechnung Core Invoice Data Model (CVD) 0.9. */
    public const CVD = self::CIUS.'#compliant#urn:xeinkauf.de:kosit:xrechnung:cvd_0.9';

    /** Business process type (BT-23) that XRechnung recommends: Peppol billing. */
    public const PEPPOL_PROFILE = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';

    /**
     * Process control for an XRechnung invoice.
     *
     * @param  string  $specification  one of CIUS, EXTENSION or CVD
     */
    public static function processControl(string $specification = self::CIUS): ProcessControl
    {
        return new ProcessControl($specification, self::PEPPOL_PROFILE);
    }
}
