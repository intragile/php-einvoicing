<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Directory;

use IntrAgile\EInvoicing\Transport\Peppol\DocumentTypeId;
use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;
use InvalidArgumentException;

/**
 * Peppol Directory search criteria, combined with AND, and the result page to fetch.
 *
 * The Directory ignores a name of two characters or fewer and answers 400 when every criterion
 * was ignored, so such a query is refused here. It returns at most 1000 results in total.
 */
final readonly class DirectoryQuery
{
    /** Largest number of results the Directory pages through. */
    public const MAX_RESULTS = 1000;

    public const MAX_PAGE_SIZE = 100;

    /** Names this short are ignored by the Directory. */
    private const IGNORED_NAME_LENGTH = 2;

    /** Generic search term. */
    public ?string $q;

    /** Partial match on the entity name. */
    public ?string $name;

    /** ISO 3166-1 alpha-2 country code, upper case. */
    public ?string $country;

    /**
     * @throws InvalidArgumentException when no criterion is given, a name of two characters or
     *                                  fewer is the only criterion, the country is not
     *                                  alpha-2, or the page lies outside the first 1000 results
     */
    public function __construct(
        ?string $q = null,
        public ?ParticipantId $participant = null,
        ?string $name = null,
        ?string $country = null,
        public ?DocumentTypeId $documentType = null,
        /** Zero-based result page index. */
        public int $page = 0,
        /** Results per page, 1 to 100. */
        public int $pageSize = 20,
    ) {
        $this->q = self::blankToNull($q);
        $this->name = self::blankToNull($name);
        $country = self::blankToNull($country);
        if ($country !== null && preg_match('/\A[A-Za-z]{2}\z/', $country) !== 1) {
            throw new InvalidArgumentException(sprintf('The country "%s" is not an ISO 3166-1 alpha-2 code.', $country));
        }
        $this->country = $country === null ? null : strtoupper($country);

        $others = $this->q !== null || $participant !== null || $this->country !== null || $documentType !== null;
        if (! $others && $this->name === null) {
            throw new InvalidArgumentException('A Directory query needs at least one search criterion.');
        }
        if (! $others && mb_strlen($this->name ?? '') <= self::IGNORED_NAME_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'The Directory ignores a name of %d characters or fewer; add another criterion or a longer name.',
                self::IGNORED_NAME_LENGTH,
            ));
        }

        if ($pageSize < 1 || $pageSize > self::MAX_PAGE_SIZE) {
            throw new InvalidArgumentException(sprintf('The page size must be between 1 and %d, %d given.', self::MAX_PAGE_SIZE, $pageSize));
        }
        if ($page < 0) {
            throw new InvalidArgumentException(sprintf('The page index must not be negative, %d given.', $page));
        }
        if ($page * $pageSize >= self::MAX_RESULTS) {
            throw new InvalidArgumentException(sprintf('The Directory returns at most %d results; page %d of size %d starts beyond them.', self::MAX_RESULTS, $page, $pageSize));
        }
    }

    /**
     * The query parameters, in the Directory's names.
     *
     * @return array<string, string|int>
     */
    public function parameters(): array
    {
        return array_filter([
            'q' => $this->q,
            'participant' => $this->participant?->toUri(),
            'name' => $this->name,
            'country' => $this->country,
            'doctype' => $this->documentType?->toUri(),
            'rpi' => $this->page,
            'rpc' => $this->pageSize,
        ], static fn (string|int|null $value): bool => $value !== null);
    }

    private static function blankToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
