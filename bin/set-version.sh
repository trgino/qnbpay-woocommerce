#!/usr/bin/env bash
#
# Updates the plugin version across index.php and readme.txt, then verifies consistency.
# Usage: bash bin/set-version.sh <version>
# Example: bash bin/set-version.sh 2.0.3

set -euo pipefail

NEW_VERSION="${1:-}"

if [ -z "$NEW_VERSION" ]; then
    echo "Usage: bash bin/set-version.sh <version>" >&2
    echo "Example: bash bin/set-version.sh 2.0.3" >&2
    exit 1
fi

if [[ ! "$NEW_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+.*$ ]]; then
    echo "::error::Version must be in semver format (e.g. 2.0.3)" >&2
    exit 1
fi

echo "Updating version to $NEW_VERSION in index.php and readme.txt..."

php -r "
\$ver = \$argv[1];
\$targets = [
    ['index.php', '/(Version:\s*)[0-9][0-9.]*/'],
    ['readme.txt', '/(Stable tag:\s*)[0-9][0-9.]*/'],
    ['readme.md', '/(version-)[0-9][0-9.]*/']
];
foreach (\$targets as [\$file, \$pattern]) {
    \$content = file_get_contents(\$file);
    \$updated = preg_replace(\$pattern, '\${1}' . \$ver, \$content, 1);
    file_put_contents(\$file, \$updated);
}
" "$NEW_VERSION"

echo "Verifying version consistency..."
bash bin/check-version.sh
