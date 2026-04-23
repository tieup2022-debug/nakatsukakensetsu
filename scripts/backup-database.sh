#!/usr/bin/env bash
# Laravel の .env を読むのは artisan 側なので、認証情報はリポジトリに置かずにバックアップできます。
# 例（本番）: 0 3 * * * /path/to/nakatsuka_new/scripts/backup-database.sh
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"
exec php artisan backup:database "$@"
