#!/usr/bin/env bash

set -euo pipefail

plugin_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
build_root="${plugin_root}/build"
stage_root="${build_root}/lineweb-restaurant-orders"
version="$(sed -n 's/^ \* Version:[[:space:]]*//p' "${plugin_root}/snaporder.php" | head -n 1)"
archive="${build_root}/lineweb-restaurant-orders-${version}.zip"

rm -rf "${stage_root}" "${archive}"
mkdir -p "${stage_root}"

rsync -a --delete --exclude-from="${plugin_root}/.distignore" "${plugin_root}/" "${stage_root}/"
find "${stage_root}" -name '.DS_Store' -delete

for forbidden_path in output .playwright-cli build node_modules vendor tests .git .github .wp-env; do
	if [[ -e "${stage_root}/${forbidden_path}" ]]; then
		echo "Release staging contains forbidden path: ${forbidden_path}" >&2
		exit 1
	fi
done

(
	cd "${build_root}"
	zip -q -r "${archive}" lineweb-restaurant-orders
)

echo "Built ${archive}"
