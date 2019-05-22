#!/bin/bash

export PROJ_DIR="$(readlink -f "$(dirname "$0")")"
set -x

"$PROJ_DIR"/lib/link.sh
"$PROJ_DIR"/webhook/link.sh
"$PROJ_DIR"/app/link.sh
