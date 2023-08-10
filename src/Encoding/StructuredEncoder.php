<?php

namespace Quarks\EventBus\Transport\Encoding;

use Quarks\EventBus\ContentTypeHelper;
use Quarks\EventBus\Envelope;
use Quarks\EventBus\Exception\MessageDecodingFailedException;
use Quarks\EventBus\Exception\MessageEncodingFailedException;
use Quarks\EventBus\Metadata;
use Quarks\EventBus\Transport\AMQPMessage;
use Quarks\EventBus\Transport\AMQPTransport;

class StructuredEncoder
{
    private array $knownKeys = ['source', 'data', 'datacontenttype', 'time', 'specversion', 'id', 'type', 'subject', 'dataschema', 'datacontenttype'];

    public function encode(Envelope $envelope): AMQPMessage
    {
        $body = $envelope->getBody();

        if ($envelope->getMetadata()->getDataContentType() === ContentTypeHelper::CLOUDEVENTS_CONTENT_TYPE_JSON) {
            // dirty hack
            $body = json_decode($envelope->getBody(), true);
        }

        $plain = [
            'source' => $envelope->getMetadata()->getSource(),
            'data' => $body,
            'datacontenttype' => $envelope->getMetadata()->getDataContentType(),
            'time' => $envelope->getMetadata()->getTime(),
            'specversion' => $envelope->getMetadata()->getSpecVersion(),
            'id' => $envelope->getMetadata()->getId(),
            'type' => $envelope->getMetadata()->getType(),
        ];

        try {
            return new AMQPMessage(
                json_encode($plain, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                AMQP_NOPARAM,
                ['content_type' => $envelope->getMetadata()->getDataContentType()]
            );
        } catch (\Exception $exception) {
            throw new MessageEncodingFailedException('', 0, $exception);
        }
    }

    public function decode(\AMQPEnvelope $AMQPEnvelope): Envelope
    {
        try {
            $bodyDecoded = json_decode($AMQPEnvelope->getBody(), true, flags: JSON_THROW_ON_ERROR);

            if (!is_array($bodyDecoded)) {
                throw new MessageDecodingFailedException();
            }

            if (\DateTime::createFromFormat(\DateTimeInterface::RFC3339, $bodyDecoded['time']) === false) {
                throw new MessageDecodingFailedException();
            }

            $metadata = new Metadata(
                $bodyDecoded['specversion'],
                $bodyDecoded['type'],
                $bodyDecoded['source'],
                $bodyDecoded['id'],
                $bodyDecoded['time'],
            );

            $metadata
                ->setSubject($bodyDecoded['subject'] ?? '')
                ->setDataScheme($bodyDecoded['dataschema'] ?? '')
                ->setDataContentType($bodyDecoded['datacontenttype'] ?? '');

            $extensions = array_diff(array_keys($bodyDecoded), $this->knownKeys);

            foreach ($extensions as $name) {
                $metadata->addExtension($name, $bodyDecoded[$name]);
            }

            if ($metadata->getDataContentType() == ContentTypeHelper::CLOUDEVENTS_CONTENT_TYPE_JSON) {
                // dirty hack
                $body = json_encode($bodyDecoded['data'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT);
            }

            return new Envelope(
                $metadata,
                $body ?? $bodyDecoded['data'],
                [AMQPTransport::MARKER_AMQP_DELIVERY_TAG => $AMQPEnvelope->getDeliveryTag()]
            );
        } catch (\Exception $exception) {
            throw new MessageDecodingFailedException('', 0, $exception);
        }
    }
}
