<?php

namespace Quarks\EventBus\Transport;

use Quarks\EventBus\Envelope;
use Quarks\EventBus\Exception\MessageEncodingFailedException;
use Quarks\EventBus\Exception\TransportException;
use Quarks\EventBus\Transport\Encoding\Encoder;

class AMQPTransport implements TransportInterface, BlockingTransportInterface
{
    public const MARKER_AMQP_DELIVERY_TAG = 'amqp_delivery_tag';
    public const MAX_RETRIES = 3;

    private AMQPConnection $connection;
    private Encoder $encoder;

    private array $receiverOptions = [
        'queue' => '',
        'setupTopology' => false,
        'requeueOnError' => false,
        'prefetchCount' => 3,
        'enableDLX' => false,
    ];

    public function __construct(AMQPConnection $connection, array $receiverOptions)
    {
        $this->connection = $connection;
        $this->receiverOptions = array_replace_recursive($this->receiverOptions, $receiverOptions);

        $this->encoder = new Encoder();
    }

    /**
     * @throws TransportException
     * @throws MessageEncodingFailedException
     */
    public function publish(Envelope $envelope, array $options = []): void
    {
        $dividerPos = strrpos($envelope->getMetadata()->getType(), ".");
        $exchange = substr($envelope->getMetadata()->getType(), 0, $dividerPos);
        $routingKey = substr($envelope->getMetadata()->getType(), $dividerPos+1);

        $amqpMessage = $this->encoder->encode($envelope);

        try {
            $this->connection->publish($amqpMessage, $exchange, $routingKey);
        } catch (\AMQPException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @throws TransportException
     */
    public function get(): iterable
    {
        try {
            $amqpEnvelope = $this->connection->get($this->receiverOptions['queue']);

            if (null === $amqpEnvelope) {
                return;
            }

            yield $this->encoder->decode($amqpEnvelope);

        } catch (\AMQPException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @throws \AMQPQueueException
     * @throws \AMQPException
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     */
    public function fetch(callable $fetcher)
    {
        $this->connection->fetch($this->receiverOptions['queue'], function (\AMQPEnvelope $amqpEnvelope, \AMQPQueue $queue) use ($fetcher) {
            $envelope = $this->encoder->decode($amqpEnvelope);
            $envelope->addMarker(self::MARKER_AMQP_DELIVERY_TAG, $amqpEnvelope->getDeliveryTag());
            $envelope->setHeaders($amqpEnvelope->getHeaders());

            $fetcher($envelope);
        });
    }

    /**
     * @throws TransportException
     */
    public function ack(Envelope $envelope): void
    {
        if (empty($deliveryTag = $envelope->getMarker(self::MARKER_AMQP_DELIVERY_TAG))) {
            throw new \LogicException('Missing marker');
        }

        try {
            $this->connection->ack($this->receiverOptions['queue'], $deliveryTag);
        } catch (\AMQPException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    private function hasExceededRetryCount(Envelope $envelope): bool
    {
        $headers = $envelope->getHeaders();
        $deaths = $headers['x-death'] ?? [];

        if (is_array($deaths)) {
            foreach ($deaths as $death) {
                if ($death['queue'] === $headers['x-first-death-queue']) {
                    return $death['count'] >= self::MAX_RETRIES;
                }
            }
        }

        return false;
    }

    /**
     * @throws TransportException
     */
    public function reject(Envelope $envelope, bool $requeue = false): void
    {
        if (empty($deliveryTag = $envelope->getMarker(self::MARKER_AMQP_DELIVERY_TAG))) {
            throw new \LogicException('Missing marker');
        }

        if (!$requeue) {
            $this->putIntoParkingLot($envelope);

            return;
        }

        if ($this->hasExceededRetryCount($envelope)) {
            $this->putIntoParkingLot($envelope);

            return;
        }

        try {
            $this->connection->nack($this->receiverOptions['queue'], $deliveryTag);
        } catch (\AMQPException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    private function putIntoParkingLot(Envelope $envelope): void
    {
        $deliveryTag = $envelope->getMarker(self::MARKER_AMQP_DELIVERY_TAG);
        $dlxExchange = $this->receiverOptions['queue'] . AMQPConnection::DLX_SUFFIX;

        $amqpMessage = $this->encoder->encode($envelope);

        try {
            $this->connection->publish($amqpMessage, $dlxExchange, AMQPConnection::PARKING_LOT_ROUTING_KEY);
            $this->connection->ack($this->receiverOptions['queue'], $deliveryTag);
        } catch (\AMQPException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @throws TransportException
     */
    public function setup(array $registeredEvents): void
    {
        if (!$this->receiverOptions['setupTopology']) {
            return;
        }

        try {
            //@TODO add enableParkingLot
            $this->connection->setup(
                $registeredEvents,
                $this->receiverOptions['enableDLX'],
                $this->receiverOptions['queue'],
            );
        } catch (\AMQPException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }
}
