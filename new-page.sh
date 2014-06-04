#!/bin/sh

# page-template.sh
# part of phpsqlitesite
# http://phpsqlitesite.com
# runs sqlite sql query to add new page to db

TITLE="$1"
LABEL="$2"
read "CONTENT"

sqlite3 ./demo.sqlite <<EOF
UPDATE "index" set title="$TITLE", label="$LABEL", content="$CONTENT";
EOF