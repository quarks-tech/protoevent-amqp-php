## Installation

1. **Install dependencies**:
    ```bash
    composer install --ignore-platform-reqs
    ```

2. **Builder php docker image**: PHP 8.1 with all the required extensions
    ```bash
    make install-builder
    ```

3. **Update the `demo/config.php` file**: make sure these credentials match with your local rabbitmq setup
    ```bash
    'rabbitmq' => [
        'host' => 'localhost',
        'port' => '5672',
        'vhost' => '/',
        'login' => 'guest',
        'password' => 'guest',
    ]
    ```

4. **Create rabbitmq exchange `example.books.v1`**


5. **Start the receiver**: The receiver will create the `excample.consumers.v1` queue, bind itself to the `BookCreatedEvent`, and start listening to events to process them.
    ```bash
    make consume
    ```
6. **Publish an event**: The `BookCreatedEvent` will be published on to `example.books.v1` exchange
    ```bash
    make publish
    ```