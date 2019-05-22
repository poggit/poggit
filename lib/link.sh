#!/bin/bash

export LIB_DIR="$(readlink -f "$(dirname "$0")")"
set -x

cd "$LIB_DIR"/all
npm link

cd "$LIB_DIR"/frontend
npm link
npm link poggit-eps-lib-all

cd "$LIB_DIR"/server
npm link
npm link poggit-eps-lib-all
