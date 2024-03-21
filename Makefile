BUILDER_IMAGE_TAG = protoevent-amqp-php_php
BUILDER_IMAGE = .
APPLICATION_PATH = /var/www/application

ifeq ($(findstring Darwin,$(shell uname -s)),Darwin)
   DOCKER_INTERNAL_HOST = host.docker.internal
else
   DOCKER_INTERNAL_HOST = 172.17.0.1
endif

.PHONY: install-builder
install-builder:
	docker build -t $(BUILDER_IMAGE_TAG) $(BUILDER_IMAGE)

.PHONY: consume
demo-consume:
	docker run --rm -v $(PWD):$(APPLICATION_PATH) -e 'DOCKER_INTERNAL_HOST=$(DOCKER_INTERNAL_HOST)' $(BUILDER_IMAGE_TAG) sh -c 'cd demo && php consumer.php'

.PHONY: publish
demo-publish:
	docker run --rm -v $(PWD):$(APPLICATION_PATH) -e 'DOCKER_INTERNAL_HOST=$(DOCKER_INTERNAL_HOST)' $(BUILDER_IMAGE_TAG) sh -c 'cd demo && php producer.php'

