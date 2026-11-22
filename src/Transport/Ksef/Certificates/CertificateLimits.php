<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport\Ksef\Certificates;

/**
 * The certificate limits of the authenticated subject, from GET /certificates/limits: how many
 * enrolment requests it may still make and how many active certificates it may still hold.
 */
final readonly class CertificateLimits
{
    /**
     * @param  bool  $canRequest  whether the subject may make a new enrolment request now
     */
    public function __construct(
        public bool $canRequest,
        public int $enrollmentRemaining,
        public int $enrollmentLimit,
        public int $certificateRemaining,
        public int $certificateLimit,
    ) {}
}
