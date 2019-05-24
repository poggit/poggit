#!/bin/bash

echo Waiting for MySQL server up...
until mysqladmin ping --silent; do
	sleep 1
done
