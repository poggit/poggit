#!/bin/bash

export WH_DIR="$(realpath "$(dirname "$0")")"
set -x

cd "$WH_DIR"

npm install
npm link poggit-eps-lib-all
npm link poggit-eps-lib-server
