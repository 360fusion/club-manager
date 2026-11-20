#!/bin/sh
# Records the four journey videos into tests/journeys/out/*.mp4, rebuilding the demo database before each.
# Start the demo server first (the `demo` entry in .claude/launch.json, port 8123). Needs Chrome, ffmpeg and PostgreSQL.
# One journey: ./record.sh meeting   Screenshots instead of video: DRY=1 ./record.sh dues
set -e
HERE="$(cd "$(dirname "$0")" && pwd)"
cd "$HERE"
for journey in ${@:-meeting event dues passon}; do
    ./reset-demo.sh >/dev/null
    node "journey-$journey.mjs"
done
