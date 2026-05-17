#!/bin/bash
set -e

composer install --no-interaction --prefer-dist

if [ $# -eq 0 ]; then
    exec php -S 0.0.0.0:8080 -t vendor/codeigniter4/framework/public/
else
    exec "$@"
fi
