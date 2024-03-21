<?php

declare(strict_types=1);

namespace Quarks\EventBus\Transport\Encoding;

use Quarks\EventBus\Envelope;
use Quarks\EventBus\Transport\AMQPMessage;

interface EncoderInterface
{
    public function encode(Envelope $envelope): AMQPMessage;

    public function decode(\AMQPEnvelope $AMQPEnvelope): Envelope;
}