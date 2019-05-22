#!/bin/bash

export WH_DIR="$(readlink -f "$(dirname "$0")")"
set -x

cd "$WH_DIR"

npm install
npm link poggit-eps-lib-all
npm link poggit-eps-lib-server
