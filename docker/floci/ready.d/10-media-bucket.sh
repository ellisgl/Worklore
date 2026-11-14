#!/bin/sh
# Floci ready hook: create the media bucket (App/.env S3_BUCKET). Floci
# treats CreateBucket on an existing bucket as a no-op, so this is safe on
# every start.
set -e
aws --endpoint-url http://localhost:4566 s3 mb "s3://${MEDIA_BUCKET}"
