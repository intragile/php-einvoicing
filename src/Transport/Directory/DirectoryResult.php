<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Directory;

/**
 * One page of Peppol Directory search results.
 */
final readonly class DirectoryResult
{
    /**
     * @param  list<DirectoryEntry>  $entries  the entries of this page; matches outside the
     *                                         iso6523-actorid-upis scheme are left out, so there
     *                                         may be fewer than the page size
     */
    public function __construct(
        /** Number of matches over all pages, as reported by the Directory. */
        public int $total,
        public array $entries,
    ) {}
}
