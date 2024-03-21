<?php

declare(strict_types=1);

use Example\Books\V1\BookCreatedEvent;
use Quarks\EventBus\Publisher;
use Quarks\EventBus\Transport\Encoding\Encoder;
use Quarks\EventBus\Transport\ParkingLot\AMQPTransport;
use Quarks\EventBus\Transport\ParkingLot\AMQPConnection;
use Example\Books\V1\EventBus\Publisher\Publisher as BooksV1Publisher;

require '../vendor/autoload.php';

$config = include 'config.php';
$connection = new AMQPConnection($config['rabbitmq']);
$amqpTransport = new AMQPTransport($connection, new Encoder(), [
    'queue' => 'example.consumers.v1',
    'setupTopology' => true,
    'setupBindings' => true,
]);

$publisher = new Publisher($amqpTransport);

$booksV1Publisher = new BooksV1Publisher($publisher);
$booksV1Publisher->publishBookCreatedEvent(
    (new BookCreatedEvent())
        ->setId(312)
);
