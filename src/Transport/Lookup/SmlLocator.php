<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Lookup;

use IntrAgile\EInvoicing\Transport\Peppol\ParticipantId;

/**
 * Finds a participant's SMP through the Peppol SML with the U-NAPTR lookup.
 *
 * The host name is the RFC 4648 base32 (upper case, unpadded) SHA-256 hash of the lower-cased
 * "<icd>:<value>", followed by the identifier scheme and the SML zone. Its NAPTR records with flag
 * "U" and service "Meta:SMP" are ordered by order and preference, and the regexp of the first one
 * turns the host name into the SMP base URL, which must be an https URL without user info, query
 * string or fragment.
 */
final class SmlLocator
{
    private const SERVICE = 'meta:smp';

    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private ?string $lastError = null;

    public function __construct(
        private readonly NaptrResolver $resolver,
        private readonly SmlZone $zone = SmlZone::Production,
    ) {}

    /**
     * The DNS name under which the SML publishes the participant's NAPTR records.
     */
    public static function hostname(ParticipantId $id, SmlZone $zone): string
    {
        return self::base32(hash('sha256', mb_strtolower($id->toString(), 'UTF-8'), true))
            .'.'.ParticipantId::SCHEME.'.'.$zone->value;
    }

    /**
     * The participant's SMP base URL; false when the SML has no SMP record for the participant,
     * null when the lookup failed or produced an unusable URL (see lastError()).
     */
    public function smpUrl(ParticipantId $id): string|false|null
    {
        $this->lastError = null;
        $host = self::hostname($id, $this->zone);

        $records = $this->resolver->naptr($host);
        if ($records === null) {
            return $this->fail(sprintf('The DNS lookup of the NAPTR records of %s failed.', $host));
        }

        $records = array_values(array_filter(
            $records,
            static fn (NaptrRecord $record): bool => strcasecmp($record->flags, 'U') === 0
                && strcasecmp($record->services, self::SERVICE) === 0,
        ));
        if ($records === []) {
            return false;
        }
        usort($records, static fn (NaptrRecord $a, NaptrRecord $b): int => [$a->order, $a->preference] <=> [$b->order, $b->preference]);

        $url = self::substitute($records[0]->regexp, $host);
        if ($url === null) {
            return $this->fail(sprintf('The NAPTR regexp "%s" of %s is malformed or does not match.', addcslashes($records[0]->regexp, "\0..\37\177"), $host));
        }

        $reason = SmpUrl::rejectionReason($url);
        if ($reason !== null) {
            return $this->fail($reason);
        }

        return $url;
    }

    /**
     * Why the last smpUrl() call returned null, or null when it did not.
     */
    public function lastError(): ?string
    {
        return $this->lastError;
    }

    private function fail(string $reason): null
    {
        $this->lastError = $reason;

        return null;
    }

    /**
     * Applies a NAPTR substitution expression ("<delim>pattern<delim>replacement<delim>flags",
     * RFC 3402 section 3.2) to the subject, replacing the first match only. Back-references are
     * written \1 to \9. An escaped delimiter inside the expression is not supported. Returns null
     * when the expression is malformed or does not match.
     */
    private static function substitute(string $expression, string $subject): ?string
    {
        if (strlen($expression) < 3) {
            return null;
        }
        $delimiter = $expression[0];
        if (ctype_alnum($delimiter) || $delimiter === '\\' || ctype_space($delimiter)) {
            return null;
        }
        $parts = explode($delimiter, substr($expression, 1));
        if (count($parts) !== 3 || ! in_array($parts[2], ['', 'i'], true)) {
            return null;
        }
        [$pattern, $replacement, $flags] = $parts;

        // The pattern comes from DNS: escape the PCRE delimiter so that it cannot end the pattern.
        $regex = '/'.str_replace('/', '\\/', $pattern).'/'.$flags;
        set_error_handler(static fn (): bool => true);
        try {
            $matched = preg_match($regex, $subject, $match, PREG_OFFSET_CAPTURE);
        } finally {
            restore_error_handler();
        }
        if ($matched !== 1) {
            return null;
        }

        $replaced = (string) preg_replace_callback(
            '/\\\\([0-9])/',
            static fn (array $reference): string => $match[(int) $reference[1]][0] ?? '',
            $replacement,
        );

        return substr_replace($subject, $replaced, $match[0][1], strlen($match[0][0]));
    }

    private static function base32(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::BASE32[(int) bindec(str_pad($chunk, 5, '0'))];
        }

        return $encoded;
    }
}
