#!/bin/sh
# Runs once, when PostgreSQL initializes an empty data directory (fresh clone / wiped Data/).
# Existing installs: `make test-db` creates the database instead (needs the role, see Makefile).
set -eu

: "${POSTGRES_TEST_DB:?}" "${POSTGRES_TEST_USER:?}" "${POSTGRES_TEST_PASSWORD:?}"

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
     -v user="$POSTGRES_TEST_USER" -v pass="$POSTGRES_TEST_PASSWORD" -v db="$POSTGRES_TEST_DB" <<'SQL'
-- CREATEDB lets `doctrine:database:create --env=test` recreate it if it's dropped
CREATE ROLE :"user" LOGIN CREATEDB PASSWORD :'pass';
CREATE DATABASE :"db" OWNER :"user";
SQL
