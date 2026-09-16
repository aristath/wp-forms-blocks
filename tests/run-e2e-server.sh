#!/usr/bin/env bash

set -euo pipefail

test_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
plugin_dir=$(cd "$test_dir/.." && pwd)
wp_root=${WP_ROOT_DIR:-$(cd "$plugin_dir/../../.." && pwd)}
database_dir=$(mktemp -d /tmp/wp-forms-blocks-e2e.XXXXXX)
server_pid=''
e2e_port=${WP_FORMS_BLOCKS_E2E_PORT:-8889}

if [[ ! "$e2e_port" =~ ^[0-9]+$ ]] || (( e2e_port < 1024 || e2e_port > 65535 )); then
	echo 'WP_FORMS_BLOCKS_E2E_PORT must be a valid port.' >&2
	exit 1
fi

cleanup() {
	if [[ -n "$server_pid" ]] && kill -0 "$server_pid" 2>/dev/null; then
		kill "$server_pid" 2>/dev/null || true
		wait "$server_pid" 2>/dev/null || true
	fi
	rm -rf -- "$database_dir"
}
trap cleanup EXIT INT TERM

wp_test=(
	wp
	"--path=$wp_root"
	"--exec=error_reporting(E_ERROR); define('DB_DIR', '$database_dir'); define('DB_FILE', 'e2e.sqlite');"
)

"${wp_test[@]}" core install \
	--url="http://localhost:$e2e_port" \
	--title="WP Forms Blocks E2E" \
	--admin_user=admin \
	--admin_password=password \
	--admin_email=admin@example.com \
	--skip-email \
	--quiet
"${wp_test[@]}" option update active_plugins \
	'["wp-forms-blocks/wp-forms-blocks.php","wp-forms-blocks/tests/e2e/test-plugin/wp-forms-blocks-e2e.php"]' \
	--format=json \
	--quiet

WP_FORMS_BLOCKS_E2E_DB_DIR="$database_dir" \
	php -d "auto_prepend_file=$test_dir/e2e/prepend.php" \
	-S "127.0.0.1:$e2e_port" \
	-t "$wp_root" &
server_pid=$!
wait "$server_pid"
