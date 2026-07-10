# SCSSコメントヘッダー運用ルール

## 目的

`scss` / `module.scss` ファイルの先頭に、用途・参照元・更新日が分かるコメントを統一して記載する。

---

## 対象

- `src/**/*.scss`
- `src/**/*.module.scss`

---

## 記載ルール

- `module.scss` を新規作成する時は、先頭に `@use` を記載して `src/styles/foundation/` を読み込む
- `@use` の相対パスは、その `module.scss` の配置階層に応じて調整する
- ファイル先頭に記載する
- `URL` はプロジェクトルートからの絶対パス風で書く
- `Referenced in` は対応する `tsx` や読み込み元を書く
- 修正したら `Last updated` を更新する
- タイトルは「プロジェクト名 + コンポーネント名 + スタイル」で簡潔に書く

---

## テンプレート

```scss
@use "../../styles/foundation/" as *;
/* =======================================
 * ふもと旅館 Header スタイル
 * URL: /src/components/common/Header.module.scss
 * Referenced in: /src/components/common/Header.tsx
 * Created: 2026-06-09
 * Last updated: 2026-06-09
 * ======================================= */
```

---

## 記載例

### module.scss

```scss
@use "../../styles/foundation/" as *;
/* =======================================
 * ふもと旅館 Footer スタイル
 * URL: /src/components/common/Footer.module.scss
 * Referenced in: /src/components/common/Footer.tsx
 * Created: 2026-06-09
 * Last updated: 2026-06-09
 * ======================================= */
```

### グローバルSCSS

```scss
/* =======================================
 * ふもと旅館 グローバルスタイル
 * URL: /src/styles/globals.scss
 * Referenced in: /src/app/layout.tsx
 * Created: 2026-06-09
 * Last updated: 2026-06-09
 * ======================================= */
```

---

## 補足

- `module.scss` は原則 `foundation` を `@use` してからコメントヘッダーを書く
- `@use` の相対パスは固定しない
- 例: `src/components/common/` や `src/components/top/` なら `../../styles/foundation/`
- さらに深い階層では `../../../styles/foundation/` のように調整する
- コメント文言は `tsx` 側と対応が取れる名前にする
- 日付は `YYYY-MM-DD` で統一する
- 空ファイルで先に作る場合でも、コメントは先に入れてよい
