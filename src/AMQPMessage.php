<?php

namespace Quarks\EventBus\Transport;

class AMQPMessage
{
    private mixed $body;
    private int $flags = \AMQP_NOPARAM;
    private array $attributes = [];

    public function __construct(
        mixed $body,
        int $flags = AMQP_NOPARAM,
        array $attributes = []
    ) {
        $this->body = $body;
        $this->flags = $flags;
        $this->attributes = $attributes;
    }

    public function getBody(): mixed
    {
        return $this->body;
    }

    public function getFlags(): int
    {
        return $this->flags;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
