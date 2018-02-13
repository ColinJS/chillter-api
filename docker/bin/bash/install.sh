#!/usr/bin/env bash

RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

DOCKER_PROJECT_EXEC="docker-compose -p chillter exec --user=1000 apache"
DOCKER_PROJECT_EXEC_ROOT="docker-compose -p chillter exec apache"
DOCKER_WWW_USER="1000"

DOCKER_DAEMON_START="docker-compose -p chillter up -d"
DOCKER_DAEMON_STOP="docker-compose -p chillter stop"

echo "Create application config..."
${DOCKER_PROJECT_EXEC} rm config.php
${DOCKER_PROJECT_EXEC} bash -c "cp docker/config.php ."
echo " -> ${GREEN}DONE${NC}\n"

echo "Create Pimple container dump..."
${DOCKER_PROJECT_EXEC} bash -c "echo '{}' > pimple.json"
${DOCKER_PROJECT_EXEC} bash -c "chmod 777 pimple.json"
echo " -> ${GREEN}DONE${NC}\n"

echo "Set www permission..."
${DOCKER_PROJECT_EXEC_ROOT} chown -R ${DOCKER_WWW_USER}:${DOCKER_WWW_USER} /var/www
echo " -> ${GREEN}DONE${NC}\n"

echo "Install node modules..."
${DOCKER_PROJECT_EXEC_ROOT} npm install --silent --no-progress
echo " -> ${GREEN}DONE${NC}\n"

echo "Install Composer vendors..."
${DOCKER_PROJECT_EXEC} composer install --no-interaction --quiet
echo " -> ${GREEN}DONE${NC}\n"

echo "Generating apiDoc..."
${DOCKER_PROJECT_EXEC} gulp apidoc --silent
echo " -> ${GREEN}DONE${NC}\n"