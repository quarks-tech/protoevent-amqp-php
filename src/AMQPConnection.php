<?php

namespace Quarks\EventBus\Transport;

class AMQPConnection
{
    private const DLX_SUFFIX = ".dlx";

    private \AMQPConnection $connection;
    private ?\AMQPChannel $channel = null;

    /** @var array<\AMQPExchange> */
    private array $exchanges = [];

    /** @var array<\AMQPQueue> */
    private array $queues = [];

    public function __construct(array $connectionOptions)
    {
        if (!\extension_loaded('amqp')) {
            throw new \LogicException(sprintf('You cannot use the "%s" as the "amqp" extension is not installed.', __CLASS__));
        }

        $this->connection = new \AMQPConnection($connectionOptions);
    }

    /**
     * @throws \AMQPExchangeException
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPException
     */
    public function publish(string $body, string $exchange, string $routingKey, array $headers = [], int $delayInMs = 0): void
    {
        $this->exchange($exchange)->publish(
            $body,
            $routingKey,
            \AMQP_NOPARAM,
            []
        );
    }

    /**
     * @throws \AMQPQueueException
     * @throws \AMQPExchangeException
     * @throws \AMQPConnectionException
     * @throws \AMQPChannelException
     * @throws \AMQPException
     */
    public function setup(array $registeredEvents, bool $withDLX, string $queueName): void
    {
        $queueArguments = [];

        if ($withDLX) {
            $dlxExchange = $queueName . self::DLX_SUFFIX;
            $dlxQueue = $queueName . self::DLX_SUFFIX;

            $queueArguments['x-dead-letter-exchange'] = $dlxExchange;

            $this->exchange($dlxExchange)->declareExchange();
            $this->queue($dlxQueue)->declareQueue();

            $this->queue($dlxQueue)->bind($dlxExchange);
        }

        $this->queue($queueName, $queueArguments)->declareQueue();

        foreach ($registeredEvents as $eventFullName => $eventClassName) {
            $exchangeName = substr($eventFullName, 0, strrpos($eventFullName, "."));
            $eventName = substr(strrchr($eventFullName, "."), 1);

            $this->queue($queueName)->bind($exchangeName, $eventName);
        }
    }

    /**
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPQueueException
     * @throws \AMQPException
     */
    public function fetch(string $queueName, callable $callback)
    {
        $this->queue($queueName)->consume($callback);
    }

    /**
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     * @throws \AMQPQueueException
     * @throws \AMQPException
     */
    public function get(string $queueName): \AMQPEnvelope
    {
        return $this->queue($queueName)->get();
    }

    /**
     * @throws \AMQPException
     * @throws \AMQPConnectionException
     */
    private function channel(): \AMQPChannel
    {
        if (null !== $this->channel) {
            return $this->channel;
        }

        try {
            $this->connection->pconnect();
        } catch (\AMQPConnectionException $e) {
            throw new \AMQPException('Could not connect to the AMQP server.', 0, $e);
        }

        $this->channel = new \AMQPChannel($this->connection);

        return $this->channel;
    }

    /**
     * @throws \AMQPExchangeException
     * @throws \AMQPException
     * @throws \AMQPConnectionException
     */
    private function exchange(string $name): \AMQPExchange
    {
        if (!isset($this->exchanges[$name])) {
            $exchange = new \AMQPExchange($this->channel());
            $exchange->setName($name);
            $exchange->setType(\AMQP_EX_TYPE_FANOUT);
            $exchange->setFlags(\AMQP_DURABLE);

            $this->exchanges[$name] = $exchange;
        }

        return $this->exchanges[$name];
    }

    /**
     * @throws \AMQPException
     * @throws \AMQPQueueException
     * @throws \AMQPConnectionException
     */
    private function queue(string $queueName, array $arguments = []): \AMQPQueue
    {
        if (!isset($this->queues[$queueName])) {
            $amqpQueue = new \AMQPQueue($this->channel());
            $amqpQueue->setName($queueName);
            $amqpQueue->setFlags(\AMQP_DURABLE);
            $amqpQueue->setArguments($arguments);

            $this->queues[$queueName] = $amqpQueue;
        }

        return $this->queues[$queueName];
    }

    /**
     * @throws \AMQPQueueException
     * @throws \AMQPChannelException
     * @throws \AMQPException
     * @throws \AMQPConnectionException
     */
    public function ack(string $queue,string $deliveryTag): bool
    {
        return $this->queue($queue)->ack($deliveryTag);
    }

    /**
     * @throws \AMQPQueueException
     * @throws \AMQPException
     * @throws \AMQPChannelException
     * @throws \AMQPConnectionException
     */
    public function nack(string $queue, string $deliveryTag, int $flags = \AMQP_NOPARAM): bool
    {
        return $this->queue($queue)->nack($deliveryTag, $flags);
    }
}
