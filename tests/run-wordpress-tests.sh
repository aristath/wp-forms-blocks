#!/usr/bin/env bash

set -euo pipefail

test_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
plugin_dir=$(cd "$test_dir/.." && pwd)
wp_root=$(cd "$plugin_dir/../../.." && pwd)
database_dir=$(mktemp -d /tmp/wp-forms-blocks-test.XXXXXX)

cleanup() {
	rm -rf -- "$database_dir"
}
trap cleanup EXIT

wp_test=(
	wp
	"--path=$wp_root"
	"--exec=error_reporting(E_ERROR); define('DB_DIR', '$database_dir'); define('DB_FILE', 'test.sqlite');"
)

"${wp_test[@]}" core install \
	--url=https://wp-forms-blocks.test \
	--title="WP Forms Blocks Tests" \
	--admin_user=admin \
	--admin_password=password \
	--admin_email=admin@example.com \
	--skip-email \
	--quiet
"${wp_test[@]}" plugin activate wp-forms-blocks --quiet
"${wp_test[@]}" eval-file "$test_dir/php/wordpress-smoke.php"
"${wp_test[@]}" eval-file "$test_dir/php/wordpress-privacy-smoke.php"

ajax_success=$("${wp_test[@]}" eval-file "$test_dir/php/wordpress-ajax-smoke.php")
[[ "$ajax_success" == *'"success":true'* ]]

ajax_honeypot=$(
	WP_FORMS_BLOCKS_REJECTION_CASE=honeypot \
		"${wp_test[@]}" eval-file "$test_dir/php/wordpress-ajax-rejection-smoke.php"
)
[[ "$ajax_honeypot" == *'"success":false'* ]]
[[ "$ajax_honeypot" == *'could not be processed'* ]]

ajax_signature=$(
	WP_FORMS_BLOCKS_REJECTION_CASE=signature \
		"${wp_test[@]}" eval-file "$test_dir/php/wordpress-ajax-rejection-smoke.php"
)
[[ "$ajax_signature" == *'"success":false'* ]]
[[ "$ajax_signature" == *'security check failed'* ]]

echo "All isolated WordPress integration tests passed."
