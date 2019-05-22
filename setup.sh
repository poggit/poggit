#!/bin/bash

export PROJ_DIR="$(readlink -f "$(dirname "$0")")"

cd "$PROJ_DIR"/tools
npm install
ts-node configure

cd "$PROJ_DIR"
./link.sh
