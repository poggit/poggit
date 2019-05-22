#!/bin/bash

cd /main
cp -r base/* .
bash /main/lib/link.sh
npm link poggit-eps-lib-server

npm install
ts-node src
