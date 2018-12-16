#!/bin/bash
set -eu
cd "$(dirname "$0")"

git pull origin master
composer install -n -o --no-dev --no-progress --no-suggest
