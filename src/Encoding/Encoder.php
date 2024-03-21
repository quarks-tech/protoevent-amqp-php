<?php

namespace Quarks\EventBus\Transport\Encoding;

use Quarks\EventBus\ContentTypeHelper;
use Quarks\EventBus\Envelope;
use Quarks\EventBus\Exception\MessageEncodingFailedException;
use Quarks\EventBus\Transport\AMQPMessage;

class Encoder implements EncoderInterface
{
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
        if ($envelope->getMetadata()->getDataContentType() === ContentTypeHelper::CLOUDEVENTS_CONTENT_TYPE_JSON) {
            return $this->structuredEncoder->encode($envelope);
        }

        return $this->binaryEncoder->encode($envelope);
    }

    public function decode(\AMQPEnvelope $AMQPEnvelope): Envelope
    {
        if ($AMQPEnvelope->getContentType() === ContentTypeHelper::CLOUDEVENTS_CONTENT_TYPE_JSON) {
            return $this->structuredEncoder->decode($AMQPEnvelope);
        }

        return $this->binaryEncoder->decode($AMQPEnvelope);
    }
}
