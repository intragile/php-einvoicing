<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Syntax\Cii;

/**
 * UN/CEFACT Cross Industry Invoice release that CiiWriter targets.
 *
 * Both releases share the namespace URIs and the element order for everything EN 16931 uses. D22B
 * differs only in the cardinality of the preceding invoice reference (BG-3): 0..1 in D16B, 0..n in
 * D22B.
 */
enum CiiVersion: string
{
    case D16B = 'D16B';
    case D22B = 'D22B';
}
