#!/usr/bin/env bash

set -euo pipefail

test_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
plugin_dir=$(cd "$test_dir/.." && pwd)
working_dir=$(mktemp -d /tmp/wp-forms-blocks-plugin-check.XXXXXX)
wp_root="$working_dir/wordpress"
database_dir="$working_dir/database"
plugin_check_dir="$wp_root/wp-content/plugins/plugin-check"
plugin_archive="$plugin_dir/wp-forms-blocks.zip"
wp_cli=( php -d memory_limit=512M "$(command -v wp)" )

cleanup() {
	rm -rf -- "$working_dir"
}
trap cleanup EXIT

if [[ ! -f "$plugin_archive" ]]; then
	echo "Missing plugin archive: $plugin_archive" >&2
	exit 1
fi

mkdir -p "$database_dir" "$wp_root"
"${wp_cli[@]}" core download --path="$wp_root" --force --quiet
"${wp_cli[@]}" config create \
	--path="$wp_root" \
	--dbname=wordpress \
	--dbuser=root \
	--dbpass='' \
	--dbhost=localhost \
	--skip-check \
	--quiet

curl --fail --location --silent --show-error \
	https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip \
	--output "$working_dir/sqlite-database-integration.zip"
unzip -q "$working_dir/sqlite-database-integration.zip" -d "$wp_root/wp-content/plugins"
cp \
	"$wp_root/wp-content/plugins/sqlite-database-integration/db.copy" \
	"$wp_root/wp-content/db.php"
unzip -q "$plugin_archive" -d "$wp_root/wp-content/plugins"

curl --fail --location --silent --show-error \
	https://downloads.wordpress.org/plugin/plugin-check.zip \
	--output "$working_dir/plugin-check.zip"
unzip -q "$working_dir/plugin-check.zip" -d "$wp_root/wp-content/plugins"

wp_test=(
	"${wp_cli[@]}"
	"--path=$wp_root"
	"--exec=error_reporting(E_ERROR); define('DB_DIR', '$database_dir'); define('DB_FILE', 'plugin-check.sqlite');"
)

"${wp_test[@]}" core install \
	--url=https://wp-forms-blocks-plugin-check.test \
	--title="WP Forms Blocks Plugin Check" \
	--admin_user=admin \
	--admin_password=password \
	--admin_email=admin@example.com \
	--skip-email \
	--quiet

WP_FORMS_BLOCKS_PLUGIN_CHECK_DIR="$plugin_check_dir" \
	"${wp_test[@]}" plugin check wp-forms-blocks \
		--require="$test_dir/plugin-check-bootstrap.php" \
		--require="$plugin_check_dir/cli.php" \
		--format=strict-table \
		--include-experimental \
		--ignore-codes=trademarked_term
