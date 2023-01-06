<?php

namespace Quarks\EventBus\Transport;

use Quarks\EventBus\Exception\TransportException;
use Quarks\EventBus\Message;

class AMQPTransport implements TransportInterface, BlockingTransportInterface
{
    private const MARKER_AMQP_DELIVERY_TAG = 'amqp_delivery_tag';

    private AMQPConnection $connection;

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
    }

    /**
     * @throws TransportException
     */
    public function publish(string $eventName, $body, array $options = []): void
    {
        $dividerPos = strrpos($eventName, ".");
        $exchange = substr($eventName, 0, $dividerPos);
        $routingKey = substr($eventName, $dividerPos+1);

        try {
            $this->connection->publish($body, $exchange, $routingKey);
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
        } catch (\AMQPException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }

        if (null === $amqpEnvelope) {
            return;
        }

        yield new Message($amqpEnvelope->getBody(), [self::MARKER_AMQP_DELIVERY_TAG => $amqpEnvelope->getDeliveryTag()]);
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
            $fetcher(new Message($amqpEnvelope->getBody(), [self::MARKER_AMQP_DELIVERY_TAG => $amqpEnvelope->getDeliveryTag()]));
        });
    }

    /**
     * @throws TransportException
     */
    public function ack(Message $message): void
    {
        if (empty($amqpEnvelop = $message->getMarker(self::MARKER_AMQP_DELIVERY_TAG))) {
            throw new \LogicException('Missing marker');
        }

        try {
            $this->connection->ack($this->receiverOptions['queue'], $amqpEnvelop);
        } catch (\AMQPException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @throws TransportException
     */
    public function reject(Message $message, bool $requeue = false): void
    {
        if (empty($amqpEnvelop = $message->getMarker(self::MARKER_AMQP_DELIVERY_TAG))) {
            throw new \LogicException('Missing marker');
        }

        try {
            $this->connection->nack($this->receiverOptions['queue'], $amqpEnvelop);
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
            $this->connection->setup(
                $registeredEvents,
                $this->receiverOptions['enableDLX'],
                $this->receiverOptions['queue']
            );
        } catch (\AMQPException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }
}
