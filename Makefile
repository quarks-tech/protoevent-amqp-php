BUILDER_IMAGE_TAG = protoevent-amqp-php_php
BUILDER_IMAGE = .
APPLICATION_PATH = /var/www/application

.PHONY: install-builder
install-builder:
	docker build -t $(BUILDER_IMAGE_TAG) $(BUILDER_IMAGE)

.PHONY: consume
consume:
	docker run --rm -v $(PWD):$(APPLICATION_PATH) $(BUILDER_IMAGE_TAG) sh -c 'cd demo && php consumer.php'

.PHONY: publish
publish:
	docker run --rm -v $(PWD):$(APPLICATION_PATH) $(BUILDER_IMAGE_TAG) sh -c 'cd demo && php producer.php'

