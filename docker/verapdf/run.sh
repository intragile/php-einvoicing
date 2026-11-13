#!/usr/bin/env bash
# Runs the veraPDF PDF/A validator over every PDF in a folder and writes its machine-readable
# report (MRR, XML) to <dir>/verapdf-report.xml.
#
#   docker/verapdf/run.sh <dir>
#
# Each file is checked against the PDF/A flavour its XMP metadata declares (pdfaid:part and
# pdfaid:conformance, for example 3u or 1b); a file that declares none is checked as PDF/A-3b.
# The folder is mounted read-only and the report is written from the host side.
#
# The image is pinned by digest. veraPDF is dual-licensed under MPL-2.0 and GPL-3.0-or-later;
# the image is only run, never redistributed. It is published for linux/amd64 only and runs
# emulated on arm64 hosts, which is slow. VERAPDF_TIMEOUT (seconds, default 1800) bounds the run.
#
# Exit status: 0 when the report was written (whatever the verdicts in it), non-zero when veraPDF
# could not run.
set -euo pipefail

image="verapdf/cli:v1.30.3@sha256:d74b7026c18a0f171e73c153b617fe6520e1511feb24ca98a348b2bb897669fb"

if [ "$#" -ne 1 ] || [ ! -d "$1" ]; then
    echo "Usage: $0 <dir>" >&2
    exit 2
fi
dir="$(cd "$1" && pwd)"
report="$dir/verapdf-report.xml"

shopt -s nullglob
files=()
for file in "$dir"/*.pdf; do
    files+=("/data/$(basename "$file")")
done
if [ "${#files[@]}" -eq 0 ]; then
    echo "No PDF files in $dir." >&2
    exit 2
fi

status=0
timeout "${VERAPDF_TIMEOUT:-1800}" \
    docker run --rm --platform linux/amd64 --network none --volume "$dir:/data:ro" "$image" \
    --format xml --flavour 0 --defaultflavour 3b "${files[@]}" > "$report.tmp" || status=$?

# veraPDF exits with 1 when at least one file is not compliant; the report is complete then too.
if [ "$status" -ne 0 ] && [ "$status" -ne 1 ]; then
    echo "veraPDF failed with exit status $status." >&2
    rm -f "$report.tmp"
    exit "$status"
fi
mv "$report.tmp" "$report"
echo "Wrote $report (${#files[@]} files)."
