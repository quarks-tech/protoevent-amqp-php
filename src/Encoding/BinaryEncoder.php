<?php

namespace Quarks\EventBus\Transport\Encoding;

use Quarks\EventBus\Envelope;
use Quarks\EventBus\Exception\MessageDecodingFailedException;
use Quarks\EventBus\Exception\MessageEncodingFailedException;
use Quarks\EventBus\Metadata;
use Quarks\EventBus\Transport\AMQPMessage;
use Quarks\EventBus\Transport\AMQPTransport;

class BinaryEncoder
{
    private const SPECVERSION = 'cloudEvents:specversion';
    private const ID = 'cloudEvents:id';
    private const SOURCE = 'cloudEvents:source';
    private const SUBJECT = 'cloudEvents:subject';
    private const DATASCHEME = 'cloudEvents:dataschema';
    private const TIME = 'cloudEvents:time';

    public function encode(Envelope $envelope): AMQPMessage
    {
        $headers = [
            self::SPECVERSION => $envelope->getMetadata()->getSpecVersion(),
		    self::ID => $envelope->getMetadata()->getId(),
		    self::SOURCE => $envelope->getMetadata()->getSource(),
        ];

        if (!empty($subject = $envelope->getMetadata()->getSubject())) {
            $headers[self::SUBJECT] = $subject;
	    }

        if (!empty($dataScheme = $envelope->getMetadata()->getDataScheme())) {
            $headers[self::DATASCHEME] = $dataScheme;
	    }

        if (!empty($time = $envelope->getMetadata()->getTime())) {
            $headers[self::TIME] = $time;
	    }

        // TODO: parse CloudEvent extensions

        $plain = [
            'source' => $envelope->getMetadata()->getSource(),
            'data' => $envelope->getBody(),
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
                ['headers' => $headers]
            );
        } catch (\Exception $exception) {
            throw new MessageEncodingFailedException('', 0, $exception);
        }
    }

    public function decode(\AMQPEnvelope $AMQPEnvelope): Envelope
    {
        if ($AMQPEnvelope->getType() === "") {
            throw new MessageDecodingFailedException();
        }

        $specVersion = $AMQPEnvelope->getHeader(self::SPECVERSION);
        $id = $AMQPEnvelope->getHeader(self::ID);
        $source = $AMQPEnvelope->getHeader(self::SOURCE);
        $subject = $AMQPEnvelope->getHeader(self::SUBJECT);
        $dataScheme = $AMQPEnvelope->getHeader(self::DATASCHEME);

        $time = $AMQPEnvelope->getHeader(self::TIME);
        if (\DateTime::createFromFormat(\DateTimeInterface::RFC3339, $time) === false) {
            throw new MessageDecodingFailedException();
        }

        $metadata = new Metadata(
            $specVersion !== false ? $specVersion : throw new MessageDecodingFailedException(),
            $AMQPEnvelope->getType(),
            $source !== false ? $source : throw new MessageDecodingFailedException(),
            $id !== false ? $id : throw new MessageDecodingFailedException(),
            $time,
        );
        $metadata
            ->setDataContentType($AMQPEnvelope->getContentType())
            ->setSubject($subject !== false ? $subject : throw new MessageDecodingFailedException())
            ->setDataScheme($dataScheme !== false ? $dataScheme : throw new MessageDecodingFailedException());

        // TODO: parse CloudEvent extensions

        return new Envelope(
            $metadata,
            $AMQPEnvelope->getBody(),
            [AMQPTransport::MARKER_AMQP_DELIVERY_TAG => $AMQPEnvelope->getDeliveryTag()]
        );
    }
}
