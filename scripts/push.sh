#!/usr/bin/env bash
# 使い方: ./scripts/push.sh "コミットメッセージ"
# メッセージ省略時は "Update" でコミットします。

set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

MSG="${1:-Update}"

git add -A
if git diff --cached --quiet; then
  echo "コミットする変更がありません。git push のみ実行します。"
  git push origin main
else
  git commit -m "$MSG"
  git push origin main
fi

echo "完了: $(git log -1 --oneline)"
