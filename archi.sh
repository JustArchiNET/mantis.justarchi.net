#!/usr/bin/env sh
set -eu

cd "$(dirname "$(readlink -f "$0")")"

git pull origin master

rm -rf vendor

composer install -n -o --no-dev --no-progress
