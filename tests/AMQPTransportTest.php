<?php

namespace Quarks\Tests;
use PHPUnit\Framework\TestCase;
use Quarks\EventBus\Envelope;
use Quarks\EventBus\Metadata;
use Quarks\EventBus\Transport\AMQPConnection;
use Quarks\EventBus\Transport\AMQPTransport;

class AMQPTransportTest extends TestCase
{
    public function testGet()
    {
        $AMQPenvelope = $this->getMockBuilder(\AMQPEnvelope::class)->disableOriginalConstructor()->getMock();
        $AMQPenvelope
            ->method('getContentType')
            ->willReturn('application/cloudevents+json');
        $AMQPenvelope
            ->method('getBody')
            ->willReturn('{"source":"protoevent-php","data":{"id":123},"datacontenttype":"application/cloudevents+json","time":"2023-03-22T12:44:07+00:00","specversion":"1.0","id":"859a8ad5-ad3f-475e-b2c2-38e568830631","type":"example.books.v1.BookCreated"}');

        $connection = $this->getMockBuilder(AMQPConnection::class)->disableOriginalConstructor()->getMock();
        $connection
            ->method('get')
            ->willReturn($AMQPenvelope);

        $transport = new AMQPTransport($connection, []);
        $envelopes = $transport->get();

        foreach ($envelopes as $envelope) {
            $this->assertEquals(
                (new Metadata('1.0', 'example.books.v1.BookCreated', 'protoevent-php', '859a8ad5-ad3f-475e-b2c2-38e568830631', '2023-03-22T12:44:07+00:00'))
                    ->setDataContentType('application/cloudevents+json'),
                $envelope->getMetadata()
            );
        }
    }

    public function testAck()
    {
        $connection = $this->getMockBuilder(AMQPConnection::class)->disableOriginalConstructor()->getMock();
        $connection
            ->expects($this->once())
            ->method('ack')
            ->with('my_queue_name', 'delivery_tag123')
            ->willReturn(true);

        $transport = new AMQPTransport($connection, ['queue' => 'my_queue_name']);
        $transport->ack(
            new Envelope(
                new Metadata('', '', '', '', ''),
                '',
                [AMQPTransport::MARKER_AMQP_DELIVERY_TAG => 'delivery_tag123']
            )
        );
    }

    public function testReject()
    {
        $connection = $this->getMockBuilder(AMQPConnection::class)->disableOriginalConstructor()->getMock();
        $connection
            ->expects($this->once())
            ->method('nack')
            ->with('my_queue_name', 'delivery_tag123')
            ->willReturn(true);

        $transport = new AMQPTransport($connection, ['queue' => 'my_queue_name']);
        $transport->reject(
            new Envelope(
                new Metadata('', '', '', '', ''),
                '',
                [AMQPTransport::MARKER_AMQP_DELIVERY_TAG => 'delivery_tag123']
            )
        );
    }
}
