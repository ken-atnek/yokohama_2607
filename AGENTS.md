<!-- BEGIN:nextjs-agent-rules -->

# This is NOT the Next.js you know

This version has breaking changes — APIs, conventions, and file structure may all differ from your training data. Read the relevant guide in `node_modules/next/dist/docs/` before writing any code. Heed deprecation notices.

<!-- END:nextjs-agent-rules -->

---

## 参照順（必須）

1. `docs/ROOTS_SPEC.md`
2. `docs/PAGE_STRUCTURE.md`
3. `docs/ROOTS_IMAGE_LIST_SPEC.md`
4. `docs/rules/front-rules/tsx-comment-rules.md`（`tsx` 編集時）
5. `docs/rules/front-rules/project-setup.md`
6. `docs/rules/front-rules/coding-style.md`
7. `docs/rules/front-rules/nextjs-export.md`
8. `docs/rules/front-rules/fetch-pattern.md`
9. `docs/rules/front-rules/ui-interactions.md`
10. `docs/rules/front-rules/checklist.md`
11. `docs/seo/SEO_AUDIT_REQUEST_TEMPLATE.md`
12. `docs/seo/SEO_FIX_TRACKER_TEMPLATE.md`

---

## 運用ルール

- Tailwind CSS は使用しない
- スタイルは SCSS で実装する
- 生の `a` タグは使わず、内部遷移は `Link`、外部遷移は `ExternalLink` を使う
- `src/components/roots` 配下の親ラッパークラスは `root` を避け、`rootsHero` のようにコンポーネント名ベースで命名する
- `next.config.ts` の `output: 'export'` を維持する
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
