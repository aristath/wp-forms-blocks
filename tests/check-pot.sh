#!/usr/bin/env bash

set -euo pipefail

plugin_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
expected_pot="$plugin_dir/languages/wp-forms-blocks.pot"
generated_pot=$(mktemp /tmp/wp-forms-blocks-pot.XXXXXX)
normalized_expected=$(mktemp /tmp/wp-forms-blocks-pot-expected.XXXXXX)
normalized_generated=$(mktemp /tmp/wp-forms-blocks-pot-generated.XXXXXX)

cleanup() {
	rm -f -- "$generated_pot" "$normalized_expected" "$normalized_generated"
}
trap cleanup EXIT

wp i18n make-pot \
	"$plugin_dir" \
	"$generated_pot" \
	--exclude=artifacts,build,coverage,node_modules,tests,vendor \
	--quiet

sed -e '/^"POT-Creation-Date:/d' -e '/^"X-Generator:/d' "$expected_pot" > "$normalized_expected"
sed -e '/^"POT-Creation-Date:/d' -e '/^"X-Generator:/d' "$generated_pot" > "$normalized_generated"

if ! cmp --silent "$normalized_expected" "$normalized_generated"; then
	echo 'languages/wp-forms-blocks.pot is out of date. Run npm run i18n:make-pot.' >&2
	exit 1
fi
