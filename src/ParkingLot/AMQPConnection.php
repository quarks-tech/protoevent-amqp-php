<?php

namespace Quarks\EventBus\Transport\ParkingLot;

use Quarks\EventBus\Transport\AMQPMessage;

class AMQPConnection
{
    public const DLX_SUFFIX = '.dlx';
    public const WAIT_SUFFIX = '.wait';
    public const PARKING_LOT_SUFFIX = '.pl';
    public const RETRY_ROUTING_KEY = 'retry';
    public const WAIT_ROUTING_KEY = 'wait';
    public const PARKING_LOT_ROUTING_KEY = 'parkinglot';

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
    public function publish(AMQPMessage $message, string $exchange, string $routingKey, int $delayInMs = 0): void
    {
        $this->exchange($exchange)->publish(
            $message->getBody(),
            $routingKey,
            $message->getFlags(),
            $message->getAttributes()
        );
    }

    /**
     * @throws \AMQPQueueException
     * @throws \AMQPExchangeException
     * @throws \AMQPConnectionException
     * @throws \AMQPChannelException
     * @throws \AMQPException
     */
    public function setup(array $registeredEvents, string $queueName, array $receiverOptions): void
    {
        if ($receiverOptions['setupTopology']) {
            $this->setupTopology($queueName, $receiverOptions['minRetryBackoff']);
        }

        if ($receiverOptions['setupBindings']) {
            $this->setupBindings($registeredEvents, $queueName);
        }
    }


    private function setupBindings(array $registeredEvents, string $incomingQueue): void
    {
        foreach ($registeredEvents as $eventPathReference => $eventClass) {
            $exchangeName = substr($eventPathReference, 0, strrpos($eventPathReference, "."));
            $eventName = substr(strrchr($eventPathReference, "."), 1);

            $this->queue($incomingQueue)->bind($exchangeName, $eventName);
        }
    }

    private function setupTopology(string $queueName, int $minRetryBackoff): void
    {
        $dlxExchange = $queueName . self::DLX_SUFFIX;
        $waitQueue = $queueName . self::WAIT_SUFFIX;
        $parkingLotQueue = $queueName . self::PARKING_LOT_SUFFIX;

        $this->exchange($dlxExchange, \AMQP_EX_TYPE_TOPIC)->declareExchange();

        $this->queue($waitQueue, [
            'x-dead-letter-exchange' => $dlxExchange,
            'x-dead-letter-routing-key' => self::RETRY_ROUTING_KEY,
            'x-message-ttl' => $minRetryBackoff,
        ])->declareQueue();

        $this->queue($parkingLotQueue)->declareQueue();

        $this->queue($queueName, [
            'x-dead-letter-exchange' => $dlxExchange,
            'x-dead-letter-routing-key' => self::WAIT_ROUTING_KEY,
        ])->declareQueue();

        $this->queue($waitQueue)->bind($dlxExchange, self::WAIT_ROUTING_KEY);
        $this->queue($queueName)->bind($dlxExchange, self::RETRY_ROUTING_KEY);
        $this->queue($parkingLotQueue)->bind($dlxExchange, self::PARKING_LOT_ROUTING_KEY);
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
    public function get(string $queueName): ?\AMQPEnvelope
    {
        if (false !== $message = $this->queue($queueName)->get()) {
            return $message;
        }

        return null;
    }

    /**
     * @throws \AMQPQueueException
     * @throws \AMQPChannelException
     * @throws \AMQPException
     * @throws \AMQPConnectionException
     */
    public function ack(string $queue, string $deliveryTag): bool
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

    /**
     * @throws \AMQPExchangeException
     * @throws \AMQPException
     * @throws \AMQPConnectionException
     */
    private function exchange(string $name, string $type = AMQP_EX_TYPE_FANOUT): \AMQPExchange
    {
        if (!isset($this->exchanges[$name])) {
            $exchange = new \AMQPExchange($this->channel());
            $exchange->setName($name);
            $exchange->setType($type);
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
}