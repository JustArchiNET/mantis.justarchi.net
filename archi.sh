#!/bin/bash
set -eu
cd "$(dirname "$0")"

composer install -n -o --no-dev --no-progress --no-suggest
