#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PHAR_NAME="testman.phar"
INSTALL_PATH="/usr/local/bin/testman"

cd "$SCRIPT_DIR"

echo "Building ${PHAR_NAME}..."
php -d phar.readonly=0 cmdman.phar cmdman.Util::archive --dir src/

echo "Copying to test directory..."
cp "${PHAR_NAME}" "test/${PHAR_NAME}"

if [ "$1" = "--install" ]; then
	echo "Installing to ${INSTALL_PATH}..."
	(echo '#!/usr/bin/env php'; cat "${PHAR_NAME}") > "${INSTALL_PATH}"
	chmod +x "${INSTALL_PATH}"
	echo "Installed: $(${INSTALL_PATH} --help 2>&1 | head -1)"
fi

echo "Done."
