# Claude Instructions

作業開始時は必ず以下の順で **全ファイルを読んでから** 作業を開始すること。

1. `AGENTS.md`
2. `docs/SITE_STRUCTURE.md`
3. `docs/JSON_KEY_RULES.md`
4. `docs/rules/front-rules/tsx-comment-rules.md`（`tsx` 編集時）
5. `docs/rules/front-rules/scss-comment-rules.md`（`scss` 編集時）
6. `docs/rules/front-rules/component-request-patterns.md`
7. `docs/rules/front-rules/project-setup.md`
8. `docs/rules/front-rules/coding-style.md`
9. `docs/rules/front-rules/nextjs-export.md`
10. `docs/rules/front-rules/fetch-pattern.md`
11. `docs/rules/front-rules/ui-interactions.md`
12. `docs/rules/front-rules/checklist.md`
13. `docs/seo/SEO_AUDIT_REQUEST_TEMPLATE.md`
14. `docs/seo/SEO_FIX_TRACKER_TEMPLATE.md`

必須ルール（最優先）:

- `next.config.ts` の `output: 'export'` を維持する
- Tailwind CSS は使用しない（SCSSで実装）
- 仕様と異なる実装はしない（仮実装時は明示）
