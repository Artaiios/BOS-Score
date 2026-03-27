#!/bin/bash
# BOS-Score: PHPMailer Installation
# Dieses Script lädt PHPMailer v6.9.2 herunter und kopiert die benötigten Dateien.
#
# Ausführen im Projektverzeichnis:
#   chmod +x install_phpmailer.sh
#   ./install_phpmailer.sh

set -e

PHPMAILER_VERSION="6.9.2"
TARGET_DIR="lib/PHPMailer"

echo "🔧 BOS-Score: PHPMailer $PHPMAILER_VERSION wird heruntergeladen..."

mkdir -p "$TARGET_DIR"

# Download und entpacken
curl -sL "https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v${PHPMAILER_VERSION}.tar.gz" -o /tmp/phpmailer.tar.gz
tar xzf /tmp/phpmailer.tar.gz -C /tmp

# Nur die benötigten Dateien kopieren
cp "/tmp/PHPMailer-${PHPMAILER_VERSION}/src/PHPMailer.php" "$TARGET_DIR/"
cp "/tmp/PHPMailer-${PHPMAILER_VERSION}/src/SMTP.php" "$TARGET_DIR/"
cp "/tmp/PHPMailer-${PHPMAILER_VERSION}/src/Exception.php" "$TARGET_DIR/"
cp "/tmp/PHPMailer-${PHPMAILER_VERSION}/LICENSE" "lib/LICENSE-PHPMailer"

# Aufräumen
rm -rf /tmp/phpmailer.tar.gz "/tmp/PHPMailer-${PHPMAILER_VERSION}"

echo "✅ PHPMailer $PHPMAILER_VERSION installiert in $TARGET_DIR/"
echo "   Dateien: PHPMailer.php, SMTP.php, Exception.php"
echo "   Lizenz:  lib/LICENSE-PHPMailer (LGPL 2.1)"
