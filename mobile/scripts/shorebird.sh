#!/usr/bin/env bash
# Build a Play Store release or an over-the-air patch with Shorebird, always with the
# same --dart-define values (a patch built with different ones would misbehave).
#
#   scripts/shorebird.sh release      new version for the Play Store (bump version in pubspec.yaml first)
#   scripts/shorebird.sh patch        OTA update to users on the latest release (Dart code changes only)
#   scripts/shorebird.sh preview      install the latest release on a connected phone/emulator
set -euo pipefail
cd "$(dirname "$0")/.."

[ -f release.env ] || { echo "Missing release.env (copy release.env.example, see README.md)"; exit 1; }
defines=()
while IFS='=' read -r key value; do
  [[ -z "$key" || "$key" == \#* || -z "$value" ]] && continue
  defines+=("--dart-define=$key=$value")
done < release.env

case "${1:-}" in
  release) shorebird release android -- "${defines[@]}" ;;
  patch)   shorebird patch android -- "${defines[@]}" ;;
  preview) shorebird preview ;;
  *) echo "usage: $0 release|patch|preview"; exit 1 ;;
esac
