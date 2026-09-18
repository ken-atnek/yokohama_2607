<!-- BEGIN:nextjs-agent-rules -->

# This is NOT the Next.js you know

This version has breaking changes — APIs, conventions, and file structure may all differ from your training data. Read the relevant guide in `node_modules/next/dist/docs/` before writing any code. Heed deprecation notices.

<!-- END:nextjs-agent-rules -->

---

## 参照順（必須）

1. `docs/SITE_STRUCTURE.md`
2. `docs/JSON_KEY_RULES.md`
3. `docs/rules/front-rules/tsx-comment-rules.md`（`tsx` 編集時）
4. `docs/rules/front-rules/scss-comment-rules.md`（`scss` 編集時）
5. `docs/rules/front-rules/component-request-patterns.md`
6. `docs/rules/front-rules/project-setup.md`
7. `docs/rules/front-rules/coding-style.md`
8. `docs/rules/front-rules/nextjs-export.md`
9. `docs/rules/front-rules/fetch-pattern.md`
10. `docs/rules/front-rules/ui-interactions.md`
11. `docs/rules/front-rules/checklist.md`
12. `docs/seo/SEO_AUDIT_REQUEST_TEMPLATE.md`
13. `docs/seo/SEO_FIX_TRACKER_TEMPLATE.md`

---

## 公開サイト連携バックエンド作業時（必須）

`public/sys/`、`public/backend/`、`public/db/` など、Next.js公開サイトと連携するPHP / API / メール / CLI / cron / DB連携 / JSON生成・参照に関する調査・実装を行う場合は、作業前に必ず以下を確認する。

1. `backend_project/AGENTS.md`
2. `backend_project/docs/specification/`
3. `backend_project/docs/workflows/`
4. `backend_project/docs/review/`
5. `backend_project/docs/rules/`

ルート `AGENTS.md` はプロジェクト全体とフロント側の入口、`backend_project/AGENTS.md` は公開サイト連携バックエンド開発の入口として扱う。

---

## 運用ルール

- Tailwind CSS は使用しない
- スタイルは SCSS で実装する
- 生の `a` タグは使わず、内部遷移は `Link`、外部遷移は `ExternalLink` を使う
- `src/components/roots` 配下の親ラッパークラスは `root` を避け、`rootsHero` のようにコンポーネント名ベースで命名する
- `next.config.ts` の `output: 'export'` を維持する
- 作業完了ごとの `build` 実行は行わない。必要な検証はユーザー側で実施する
- `docs` に重要な `.md` を追加したら、この参照順に追記して同期する
- `docs` に運用上重要な `.md` を追加・更新した場合は、`CLAUDE.md` の参照順にも必ず同期する

---

## AI依頼テンプレ運用（固定）

- 作業前に必ず次の4点を確認する
  - やりたいこと
  - 触っていいファイル
  - ルール（最小修正・大改修しない）
  - ゴール（完了条件）
- 実装は最小差分を優先し、一度に大量変更しない
- TSX変更時は、必要に応じてSCSSもセットで修正する
