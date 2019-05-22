#!/bin/bash

set -x

cd /main
cp -r base/* .
find lib -type l -exec rm {} +
bash ./lib/link.sh
bash ./link.sh

ts-node src
