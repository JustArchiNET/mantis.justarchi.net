#!/bin/bash
set -eu
cd "$(dirname "$0")"

git pull origin master
rm -rf vendor
composer install -n -o --no-dev --no-progress --no-suggest
