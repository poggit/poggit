#!/bin/bash

TARGET="$1"
if [ -z "$1" ]; then
	echo Usage: $0 "<target>"
	exit 1
fi

echo Waiting for "$TARGET"...
while [ ! -f "$TARGET" ]; do
	sleep 1
done
