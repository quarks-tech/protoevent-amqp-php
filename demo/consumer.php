<?php

declare(strict_types=1);

use Example\Books\V1\BookCreatedEvent;
use Example\Books\V1\EventBus\Receiver\BookCreatedEventHandlerInterface;
use Example\Books\V1\EventBus\Receiver\Receiver as BooksV1Receiver;
use Quarks\EventBus\BlockingReceiver;
use Quarks\EventBus\Dispatcher\Adapter\SymfonyEventDispatcherAdapter;
use Quarks\EventBus\Dispatcher\Dispatcher;
use Quarks\EventBus\Transport\ParkingLot\AMQPTransport;
use Quarks\EventBus\Transport\ParkingLot\AMQPConnection;
use Symfony\Component\EventDispatcher\EventDispatcher;


require '../vendor/autoload.php';

$config = include 'config.php';

$eventDispatcher = new EventDispatcher();

$dispatcher = new Dispatcher(new SymfonyEventDispatcherAdapter($eventDispatcher));
$connection = new AMQPConnection($config['rabbitmq']);

$amqpTransport = new AMQPTransport($connection, [
    'queue' => 'example.consumers.v1',
    'setupTopology' => true,
    'setupBindings' => true,
]);

$receiver = new BlockingReceiver($amqpTransport, $dispatcher);

$booksV1Receiver = new BooksV1Receiver($receiver, $dispatcher);
$booksV1Receiver->registerBookCreatedEventHandler(
    new class() implements BookCreatedEventHandlerInterface {
        public function handleBookCreatedEvent(BookCreatedEvent $event)
        {
            var_dump($event->getId());
        }
    });

function signalHandler($signal)
{
    echo "Received shutdown signal. Quitting..." . PHP_EOL;

    exit(0);
}

pcntl_async_signals(true);
pcntl_signal(SIGINT, 'signalHandler');

$receiver->run();
