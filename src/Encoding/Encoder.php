<?php

namespace Quarks\EventBus\Transport\Encoding;

use Quarks\EventBus\Envelope;
use Quarks\EventBus\Exception\MessageEncodingFailedException;
use Quarks\EventBus\Transport\AMQPMessage;

class Encoder
{
    private const structuredContentType = 'application/cloudevents+json';

    private BinaryEncoder $binaryEncoder;
    private StructuredEncoder $structuredEncoder;

    public function __construct()
    {
        $this->binaryEncoder = new BinaryEncoder();
        $this->structuredEncoder = new StructuredEncoder();
    }

    /**
     * @throws MessageEncodingFailedException
     */
    public function encode(Envelope $envelope): AMQPMessage
    {
        if ($envelope->getMetadata()->getDataContentType() === self::structuredContentType) {
            return $this->structuredEncoder->encode($envelope);
        }

        return $this->binaryEncoder->encode($envelope);
    }

    public function decode(\AMQPEnvelope $AMQPEnvelope): Envelope
    {
        if ($AMQPEnvelope->getContentType() === self::structuredContentType) {
            return $this->structuredEncoder->decode($AMQPEnvelope);
        }

        return $this->binaryEncoder->decode($AMQPEnvelope);
    }
}
