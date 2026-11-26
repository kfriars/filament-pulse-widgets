#!/bin/sh
# PROTOTYPE (throwaway): rebuild the prototype's stylesheets. Run from workbench/: npm run build:prototype-css
# The compiled files are committed, so `composer serve` needs no Node.
set -e
cd "$(dirname "$0")"
for name in package/pulse-widgets-native activity-card; do
    npx tailwindcss --input "$name.css" --output "dist/$(basename "$name").css" --minify
done
