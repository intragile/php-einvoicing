<?php

declare(strict_types=1);

namespace IntrAgile\EInvoicing\Transport;

use IntrAgile\EInvoicing\Transport\Exception\TransportException;

/**
 * Sends business documents over a network, such as Peppol through an access point, and reports
 * on their delivery.
 */
interface Transport
{
    /**
     * Hands the document to the network. A returned result means the transport accepted the
     * document; its state tells whether delivery has completed already.
     *
     * @throws TransportException when the document could not be handed over or the outcome is not known
     */
    public function send(OutboundDocument $document): SendResult;

    /**
     * The current delivery state of a document sent earlier, by the id of its send result.
     *
     * @throws TransportException when the state could not be retrieved
     */
    public function status(string $documentId): DeliveryStatus;
}
