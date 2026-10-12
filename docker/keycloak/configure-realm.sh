#!/bin/sh
# Ajustes del realm que el import (eter-realm.json) sólo aplica la primera vez
# que arranca Keycloak con la base vacía. Idempotente: se puede lanzar siempre.
# Uso: make keycloak-setup
#
# Correo: con SMTP_PASSWORD en .env.local se manda de verdad por ese SMTP
# (Gmail con contraseña de aplicación); sin ella, todo se queda en Mailpit.
set -e

KCADM=/opt/keycloak/bin/kcadm.sh
CONFIG=/tmp/kcadm-eter.config

$KCADM config credentials --config $CONFIG --server http://localhost:8080 \
    --realm master --user "${KC_BOOTSTRAP_ADMIN_USERNAME:-admin}" --password "${KC_BOOTSTRAP_ADMIN_PASSWORD:-admin123}"

$KCADM update realms/eter --config $CONFIG -s displayName=Aetheria -s registrationAllowed=false

if [ -n "$SMTP_PASSWORD" ]; then
    $KCADM update realms/eter --config $CONFIG \
        -s "smtpServer.host=${SMTP_HOST:-smtp.gmail.com}" \
        -s "smtpServer.port=${SMTP_PORT:-587}" \
        -s 'smtpServer.starttls=true' \
        -s 'smtpServer.ssl=false' \
        -s 'smtpServer.auth=true' \
        -s "smtpServer.user=$SMTP_USER" \
        -s "smtpServer.password=$SMTP_PASSWORD" \
        -s "smtpServer.from=${SMTP_FROM:-$SMTP_USER}" \
        -s 'smtpServer.fromDisplayName=Aetheria'
    echo "Realm eter configurado: correo real por ${SMTP_HOST:-smtp.gmail.com} como ${SMTP_FROM:-$SMTP_USER}."
else
    $KCADM update realms/eter --config $CONFIG \
        -s 'smtpServer.host=mailpit' -s 'smtpServer.port=1025' \
        -s 'smtpServer.starttls=false' -s 'smtpServer.auth=false' \
        -s 'smtpServer.from=no-reply@aetheria.app' -s 'smtpServer.fromDisplayName=Aetheria'
    echo "Realm eter configurado: sin SMTP_PASSWORD, los correos se quedan en Mailpit (http://localhost:8225)."
fi
