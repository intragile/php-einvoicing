<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Directory;

/**
 * The Peppol Directory instances, by base URL.
 */
enum DirectoryEnvironment: string
{
    case Production = 'https://directory.peppol.eu';
    case Test = 'https://test-directory.peppol.eu';
}
