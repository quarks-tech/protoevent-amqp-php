<?php

namespace Quarks\Tests\Encoding;

use PHPUnit\Framework\TestCase;
use Quarks\EventBus\Envelope;
use Quarks\EventBus\Metadata;
use Quarks\EventBus\Transport\Encoding\StructuredEncoder;

class StructuredEncoderTest extends TestCase
{
    public function testEncode()
    {
        $encoder = new StructuredEncoder();

        $result = $encoder->encode(
            new Envelope(
                (new Metadata('1.0', 'example.books.v1.BookCreated', 'protoevent-php', '859a8ad5-ad3f-475e-b2c2-38e568830631', '2023-03-22T12:44:07+00:00'))
                    ->setDataContentType('application/cloudevents+json'),
                '{"id":123}'
            )
        );

        $this->assertEquals(
            '{"source":"protoevent-php","data":{"id":123},"datacontenttype":"application/cloudevents+json","time":"2023-03-22T12:44:07+00:00","specversion":"1.0","id":"859a8ad5-ad3f-475e-b2c2-38e568830631","type":"example.books.v1.BookCreated"}',
            $result->getBody()
        );
    }

    public function testDecode()
    {
        $encoder = new StructuredEncoder();

        $AMQPenvelope = $this->getMockBuilder(\AMQPEnvelope::class)->disableOriginalConstructor()->getMock();
        $AMQPenvelope
            ->method('getContentType')
            ->willReturn('application/cloudevents+json');
        $AMQPenvelope
            ->method('getBody')
            ->willReturn('{"source":"protoevent-php","data":{"id":123},"datacontenttype":"application/cloudevents+json","time":"2023-03-22T12:44:07+00:00","specversion":"1.0","id":"859a8ad5-ad3f-475e-b2c2-38e568830631","type":"example.books.v1.BookCreated","someextension":"extension_value"}');

        $result = $encoder->decode($AMQPenvelope);

        $this->assertEquals(
            new Envelope(
                (new Metadata('1.0', 'example.books.v1.BookCreated', 'protoevent-php', '859a8ad5-ad3f-475e-b2c2-38e568830631', '2023-03-22T12:44:07+00:00'))
                    ->setDataContentType('application/cloudevents+json')
                    ->addExtension('someextension', 'extension_value'),
                '{"id":123}'
            ),
            $result
        );
    }
}
