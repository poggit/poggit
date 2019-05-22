#!/bin/bash
cd "`dirname "$0"`"
./lib/link.sh

cd webhook
npm link poggit-eps-lib-server

cd ../app/server
npm link poggit-eps-lib-server
npm link poggit-eps-lib-frontend

cd ../app/client
npm link poggit-eps-lib-frontend
