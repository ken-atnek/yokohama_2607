# TSXコメントヘッダー運用ルール

## 目的

`tsx` ファイルの先頭に、用途・参照元・更新日が分かるコメントを統一して記載する。

---

## 対象

- `src/**/*.tsx`

---

## 記載ルール

- ファイル先頭（`import` の前）に記載する
- `URL` はプロジェクトルートからの絶対パス風で書く（例: `/src/app/roots/page.tsx`）
- `Referenced in` は主な呼び出し元や利用ページを書く
- 修正したら `Last updated` を更新する
- タイトルは「プロジェクト名 + セクション名 or ページ名」で簡潔に書く

---

## テンプレート

```tsx
/* =======================================
 * 熊日すぱいす ROOTS ページ
 * URL: /src/app/roots/page.tsx
 * Referenced in: /src/app/roots/page.tsx
 * Created: 2026-05-01
 * Last updated: 2026-05-01
 * ======================================= */
```

---

## 記載例

### ページ

```tsx
/* =======================================
 * 熊日すぱいす 商品ページ
 * URL: /src/app/roots/[id]/page.tsx
 * Referenced in: /src/app/roots/[id]/page.tsx
 * Created: 2026-05-01
 * Last updated: 2026-05-01
 * ======================================= */
```

### コンポーネント

```tsx
/* =======================================
 * 熊日すぱいす CONTACT セクション
 * URL: /src/components/Top/ContainerContact.tsx
 * Referenced in: /src/app/page.tsx
 * Created: 2026-05-01
 * Last updated: 2026-05-01
 * ======================================= */
```

---

## 補足

- 既存コメントに `URL` や `Referenced in` の誤記（例: `: :`）がある場合は修正する
- 日付は `YYYY-MM-DD` で統一する
