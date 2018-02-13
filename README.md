# Chillter API Docker 

## First installation

1. Install [Docker](https://docs.docker.com/engine/installation/) and [Docker Compose](https://docs.docker.com/compose/install/)
2. Build images:
```bash
$ docker-compose build
```
3. Start containers:
```bash
$ docker-compose -p chillter up -d
```
4. Import SQL database to the MariaDB container:
```bash
mysql --host=127.0.0.1 --port=33306 --user=chillter --password=chillter chillter < filename.sql
```
5. Run installation script:
```bash
$ sh docker/bin/bash/install.sh
```

## HTTP access
 - API endpoint - [http://127.0.0.1:30080](http://127.0.0.1:30080)
 - phpMyAdmin - [http://127.0.0.1:30088](http://127.0.0.1:30088)

## Useful commands
 - Start containers:
 ```bash
 $ docker-compose -p chillter up -d
 ```
 - Stop containers:
 ```bash
 $ docker-compose -p chillter stop
 ```
 - Login to the container as `root`:
 ```bash
 $ docker-compose -p chillter exec apache bash
 ```
 - Login to the container as `www-data`:
 ```bash
 $ docker-compose -p chillter exec --user=1000 apache bash
 ```

## WebSocket server for event chat
The server is implemented using command `chillter:web_socket_server`.

The example `upstart` script is in `src/C/Resources/upstart.conf`.

The example JS implementation is in `src/C/Resources/websocket-client.html`.


## PhpStorm users
If you use PhpStorm, you can install [Silex/Pimple Plugin
](https://plugins.jetbrains.com/plugin/7809-silex-pimple-plugin) to have code completion for the dependency injection container.

On PHP side we use [sorien/silex-pimple-dumper](https://github.com/Sorien/silex-pimple-dumper) to write container dump file automatically.