<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\B2Brouter\Spain;

use Closure;
use InvalidArgumentException;

/**
 * The bound of waiting for a VERI*FACTU tax report: at most $attempts reads, $waitMs milliseconds
 * apart. The default of 60 reads 3 seconds apart waits about three minutes (177 seconds of
 * waiting); the B2Brouter sandbox has been seen to keep a report "signed" for more than 80
 * seconds before it was registered.
 */
final readonly class VerifactuPolling
{
    /** @var Closure(int): void */
    private Closure $sleeper;

    /**
     * @param  int  $attempts  the number of reads, at least 1
     * @param  int  $waitMs  the wait between two reads in milliseconds, at least 1
     * @param  (Closure(int): void)|null  $sleep  waits the given number of milliseconds; usleep() when null
     *
     * @throws InvalidArgumentException when the attempts or the wait are not positive
     */
    public function __construct(
        public int $attempts = 60,
        public int $waitMs = 3000,
        ?Closure $sleep = null,
    ) {
        if ($attempts < 1) {
            throw new InvalidArgumentException('The number of polling attempts must be at least 1.');
        }
        if ($waitMs < 1) {
            throw new InvalidArgumentException('The wait between polling attempts must be at least 1 millisecond.');
        }
        $this->sleeper = $sleep ?? static function (int $ms): void {
            usleep($ms * 1000);
        };
    }

    /**
     * Waits the given number of milliseconds.
     */
    public function sleep(int $ms): void
    {
        ($this->sleeper)($ms);
    }
}
