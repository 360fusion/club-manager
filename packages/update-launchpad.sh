#!/usr/bin/env bash
# Copies a Launchpad release into packages/launchpad, so servers and CI install it from this repository
# and never need access to the private 360fusion/launchpad repository. Run it here, then commit and push.
#   packages/update-launchpad.sh v0.7.1
set -euo pipefail

tag="${1:?usage: packages/update-launchpad.sh <tag, e.g. v0.7.1>}"
root="$(cd "$(dirname "$0")/.." && pwd)"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

gh api "repos/360fusion/launchpad/tarball/$tag" > "$tmp/release.tgz"
mkdir "$tmp/launchpad"
tar -xzf "$tmp/release.tgz" -C "$tmp/launchpad" --strip-components=1
rm -rf "$tmp/launchpad/tests" "$tmp/launchpad/docs" "$tmp/launchpad/.github" "$tmp/launchpad/phpunit.xml"
find "$tmp/launchpad" -name .DS_Store -delete

rm -rf "$root/packages/launchpad"
mv "$tmp/launchpad" "$root/packages/launchpad"

cd "$root"
composer config repositories.launchpad "{\"type\":\"path\",\"url\":\"packages/launchpad\",\"options\":{\"versions\":{\"360fusion/launchpad\":\"${tag#v}\"}}}"
# require, not update: on 0.x a caret constraint stops at the minor version, so ^0.7 would refuse 0.8.0
composer require "360fusion/launchpad:^${tag#v}" --no-interaction
echo "Launchpad $tag is in packages/launchpad. Commit packages/launchpad, composer.json and composer.lock."
