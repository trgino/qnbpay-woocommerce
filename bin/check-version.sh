#!/usr/bin/env bash
#
# Guards the single source of truth for the plugin version: the "Version:"
# header in index.php. Fails if readme.txt "Stable tag" does not match it.
#
# WordPress reads the header; WordPress.org requires the Stable tag to match.
# Everything else (the QNBPAY_VERSION constant, README badge) is derived or docs.

set -euo pipefail

header="$(grep -m1 -oiE 'Version:[[:space:]]*[0-9][0-9.]*' index.php | grep -oE '[0-9][0-9.]*')"
stable="$(grep -m1 -oiE 'Stable tag:[[:space:]]*[0-9][0-9.]*' readme.txt | grep -oE '[0-9][0-9.]*')"

echo "Plugin header Version : ${header:-<none>}"
echo "readme.txt Stable tag : ${stable:-<none>}"

if [ -z "$header" ]; then
	echo "::error::Could not read Version from index.php header" >&2
	exit 1
fi
if [ "$header" != "$stable" ]; then
	echo "::error::Version mismatch — index.php ($header) != readme.txt Stable tag ($stable)" >&2
	exit 1
fi

echo "OK: version is consistent ($header)"
