<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Exception;

use RuntimeException;

/**
 * Thrown when an inbound XML document carries constructs that are refused for safety, such as a
 * DOCTYPE declaration with entities.
 */
final class UnsafeXml extends RuntimeException {}
