## チェックリスト

作業開始前に確認:

- [ ] `next.config.ts` に `output: 'export'` がある
- [ ] page.tsx の params に `Promise` 型を使っていない
- [ ] `generateMetadata` / `generateStaticParams` が同期関数
- [ ] `force-dynamic` を使っていない
- [ ] マスターJSONの型は `src/types/master.ts` からインポートしている
- [ ] コンテンツJSON取得は生 `fetch` + `cache: 'no-store'` + `isError` パターン
