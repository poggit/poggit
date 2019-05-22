#!/bin/bash

set -x

PROJ_DIR="$(realpath "$(dirname "$0")")"

cd "$PROJ_DIR"/lib/all
npm install
npm link

cd "$PROJ_DIR"/lib/frontend
npm install
npm link
npm link poggit-eps-lib-all

cd "$PROJ_DIR"/lib/server
npm install
npm link
npm link poggit-eps-lib-all

cd "$PROJ_DIR"/webhook
npm install
npm link poggit-eps-lib-server

cd "$PROJ_DIR"/app/server
npm install
npm link poggit-eps-lib-server
npm link poggit-eps-lib-frontend

cd "$PROJ_DIR"/app/client
npm install
npm link poggit-eps-lib-frontend

cd "$PROJ_DIR"/tools
npm install
