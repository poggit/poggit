# Poggit
The PocketMine Plugin Platform

## Installation
### Setup
1. `mkdir secrets`
2. Add a file `secrets/secrets.js` that should export an object with types as described in `shared/SecretsType.ts`
3. `cp default-docker-compose.yml docker-compose.yml`
4. Edit `docker-compose.yml`. In particular, pay attention to:
	- MySQL passwords
	- the port numbers to forward
	- If you want to run the server in debug mode, set the environment variable `PGD_DEBUG=true` for `frontend` and `wh` services
5. `docker-compose build`
6. Create these empty files:
	- `wh.log`: This will be where the webhook handler logs are written into (this is subject to change) (not used if $PGD_DEBUG is true)
	- `frontend.log`: This is where the frontend server logs are written into (this is subject to change) (not used if $PGD_DEBUG is true)
	- `.server_started`: This file is `touch`ed when the frontend server has started (because its startup might be significantly slow). See .travis.yml for how to utilize this file to wait for server start.

If you use ngrok to redirect traffic, append this to the `services` section of `docker-compose.yml`:

```yml
  ngrok:
    image: wernight/ngrok
    restart: always
    environment:
      NGROK_AUTH: YOUR_NGROK_AUTH_TOKEN_HERE
      NGROK_PORT: frontend:3000
    ports:
      - 5050:4040
    links:
      - frontend:frontend
```

This configuration opens the ngrok web control panel on port 5050, which can be accessed externally.

Poggit itself does not start an HTTPS server (only HTTP), but some components may assume that clients connected with HTTPS. As a result, production servers should be run behind CloudFlare or similar services with forced HTTPS, and development servers should be accessed through an ngrok gateway.

### Starting
```bash
docker-compose up -d
```

If you run the server the first time, you might want to populate some initial data (so as to get rid of bugs that would not be seen in production, such as division by zero, blank pages, etc.). However, the required tables are only created after the first server run. So, after making sure the frontend server is started the first time (using browser, using logs or using `.server_started`), run `travis/populate.sh` to populate the database.

### Unit testing
Run unit tests by executing `travis/testUnit.sh`

## Project structure
The structure of this project is very complicated, involving multiple node modules, some custom toolchains, etc.

The whole software should only be executed using docker. For the sake of convenience, some install paths are hardcoded and are only suitable when run with docker. The `docker-compose` utility can be used to configure docker container settings.

The first container is `mysql`, hosting a MySQL database, which is run from the `library/mysql:8.0` image directly without any special configuration other than authentication.

The second container is `wh`, mainly managed in the `webhook-handler` directory. It is an internal HTTP server that handles GitHub webhook requests, but it is not directly exposed to the Internet. GitHub webhook traffic is redirected from the third container. The server startup is managed by `webhook-handler/run.sh`.

The third container is `frontend`, mainly managed in the `frontend` directory. It consists of two node modules: `poggit-delta-client` and `poggit-delta-server`. The startup is managed by `frontend/run.sh`.

`poggit-delta-client` is the client-side script, to be bundled with browserify + TypeScript + Closure Compiler before the server starts.

`poggit-delta-server` is the frontend HTTP server. It also compiles SASS stylesheets from `frontend/sass`, serves the static directory `frontend/public` and renders HTML templates from `frontend/view`.

All three node modules also depend on the scripts in the `shared` directory.

The `travis` contains scripts to be executed from Travis-CI.

The `Makefile` file in the root directory contains a few useful commands:
- `make logs`: See output from the `frontend` container if $PGD_DEBUG is true
- `make whLogs`: See output from the `wh` container if $PGD_DEBUG is true
- `make mysql`: Starts a MySQL interactive shell client connection.
- `make restart`: Restarts the `frontend` and `wh` servers. It also uses a `.npm_installed` file, which is used to check whether the containers need to restart because of changes in `package.json` or `run.sh`. (Restarting the containers is much slower than just restarting the node processes)
