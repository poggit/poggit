#!/bin/bash

export APP_DIR="$(realpath "$(dirname "$0")")"
set -x

cd "$APP_DIR"/client
npm install
npm link poggit-eps-lib-all
npm link poggit-eps-lib-frontend

cd "$APP_DIR"/server
npm install
npm link poggit-eps-lib-all
npm link poggit-eps-lib-server
npm link poggit-eps-lib-frontend
