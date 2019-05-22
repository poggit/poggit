#!/bin/bash
cd "`dirname "$0"`"

cd all
npm link

cd ../frontend
npm link poggit-eps-lib-all
npm link

cd ../server
npm link poggit-eps-lib-all
npm link
