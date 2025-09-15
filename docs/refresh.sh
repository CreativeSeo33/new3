#!/usr/bin/env bash
set -u

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RUNTIME_DIR="$ROOT_DIR/docs/runtime"
DB_DIR="$ROOT_DIR/docs/db"

mkdir -p "$RUNTIME_DIR" "$DB_DIR"

run() {
  local name="$1"; shift
  local outfile="$1"; shift
  echo ">> $name"
  if "$@" > "$outfile" 2>&1; then
    echo "OK: $outfile"
  else
    echo "FAIL: $name -> $outfile"
  fi
}

run "DI Container"      "$RUNTIME_DIR/container.json" php "$ROOT_DIR/bin/console" debug:container --format=json
run "Routes"             "$RUNTIME_DIR/routes.json"    php "$ROOT_DIR/bin/console" debug:router   --format=json
run "Composer deps"      "$RUNTIME_DIR/composer-deps.json" composer show -D --format=json
run "DB Schema (diff)"   "$DB_DIR/schema.sql"         php "$ROOT_DIR/bin/console" doctrine:schema:update --dump-sql
run "Doctrine entities"  "$DB_DIR/entities.txt"       php "$ROOT_DIR/bin/console" doctrine:mapping:info
run "OpenAPI JSON"       "$RUNTIME_DIR/openapi.json"  php "$ROOT_DIR/bin/console" api:openapi:export --json
run "OpenAPI YAML"       "$RUNTIME_DIR/openapi.yaml"  php "$ROOT_DIR/bin/console" api:openapi:export --yaml

echo "Done."


