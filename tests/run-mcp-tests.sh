#!/usr/bin/env bash

set -euo pipefail

test_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
plugin_dir=$(cd "$test_dir/.." && pwd)
wp_root=${WP_ROOT_DIR:-$(cd "$plugin_dir/../../.." && pwd)}
working_dir=$(mktemp -d /tmp/wp-forms-blocks-mcp.XXXXXX)

cleanup() {
	rm -rf -- "$working_dir"
}
trap cleanup EXIT

mkdir -p "$working_dir/plugins" "$working_dir/database"
curl --fail --location --silent --show-error \
	https://github.com/WordPress/mcp-adapter/releases/download/v0.7.0/mcp-adapter.zip \
	--output "$working_dir/mcp-adapter.zip"
unzip -q "$working_dir/mcp-adapter.zip" -d "$working_dir/plugins"
ln -s "$plugin_dir" "$working_dir/plugins/wp-forms-blocks"
ln -s "$wp_root/wp-content/plugins/sqlite-database-integration" "$working_dir/plugins/sqlite-database-integration"

wp_test=(
	wp
	"--path=$wp_root"
	"--exec=error_reporting(E_ERROR); define('DB_DIR', '$working_dir/database'); define('DB_FILE', 'test.sqlite'); define('WP_PLUGIN_DIR', '$working_dir/plugins');"
)

"${wp_test[@]}" core install \
	--url=https://wp-forms-blocks-mcp.test \
	--title="WP Forms Blocks MCP Tests" \
	--admin_user=admin \
	--admin_password=password \
	--admin_email=admin@example.com \
	--skip-email \
	--quiet
"${wp_test[@]}" plugin activate wp-forms-blocks mcp-adapter --quiet
"${wp_test[@]}" eval-file "$test_dir/php/wordpress-mcp-smoke.php"
