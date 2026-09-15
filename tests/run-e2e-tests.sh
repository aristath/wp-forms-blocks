#!/usr/bin/env bash

set -euo pipefail

e2e_port=${WP_FORMS_BLOCKS_E2E_PORT:-}

if [[ -z "$e2e_port" ]]; then
	e2e_port=$(php -r '$socket = stream_socket_server( "tcp://127.0.0.1:0", $errno, $error ); $name = stream_socket_get_name( $socket, false ); echo substr( strrchr( $name, ":" ), 1 );')
fi

if [[ ! "$e2e_port" =~ ^[0-9]+$ ]] || (( e2e_port < 1024 || e2e_port > 65535 )); then
	echo 'WP_FORMS_BLOCKS_E2E_PORT must be a valid port.' >&2
	exit 1
fi

export WP_FORMS_BLOCKS_E2E_PORT="$e2e_port"
export WP_BASE_URL="http://localhost:$e2e_port"

exec playwright test --config playwright.config.js "$@"
