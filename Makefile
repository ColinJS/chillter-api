PROJECT_NAME := "chillter"
APP_CONTAINER := "apache"

.SILENT:

enter:
	docker-compose -p $(PROJECT_NAME) exec --user=1000 $(APP_CONTAINER) bash

build:
	docker-compose -p $(PROJECT_NAME) build

up:
	docker-compose -p $(PROJECT_NAME) up -d

update:
	docker-compose -p $(PROJECT_NAME) up --no-deps -d $(APP_CONTAINER)

stop:
	docker-compose -p $(PROJECT_NAME) stop

exec:
	-docker-compose -p $(PROJECT_NAME) exec --user=1000 $(APP_CONTAINER) ${CMD}

console:
	make exec CMD="bin/console ${CMD}"