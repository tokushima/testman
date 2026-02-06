#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PHAR_NAME="testman.phar"

cd "$SCRIPT_DIR"

echo "Building ${PHAR_NAME}..."
php -d phar.readonly=0 cmdman.phar cmdman.Util::archive --dir src/

echo "Copying to test directory..."
cp "${PHAR_NAME}" "test/${PHAR_NAME}"

echo "Done."
