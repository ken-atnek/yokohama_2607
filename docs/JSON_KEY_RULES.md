# JSONキー命名ルール

## 目的

- 既存サイト（`/Users/ken/site_data/hpg-kobe.kir.jp/hot-point/2510`）のデータ資産を活かしつつ、
  新サイトで保守しやすいキー名に揃える。

---

## 基本方針

- 移行コストを下げるため、既存キーは可能な限り維持する
- 分かりづらいキーのみ最小限リネームする
- 値の型はできるだけ厳密にする（数値は文字列ではなく数値）
- URL項目は `...Url` に統一する
- 配列項目は複数形にする

---

## 命名規則

- 形式は `camelCase`
- 単位を含む数値はキー名で明示する
  - 例: `heightCm`, `waistCm`
- 真偽値は `is` / `has` で始める
  - 例: `isPublished`, `hasNewFace`

---

## 既存キーの扱い（キャスト詳細）

### そのまま使用

- `castId`
- `castName`
- `castNameKana`
- `castNameEn`
- `castImage`
- `age`
- `bust`
- `cup`
- `hip`
- `type`
- `schedule`
- `blogUrl`
- `reviewUrl`
- `reserveUrl`
- `startTime`
- `joinedDate`

### 変更するキー（最小）

- `tall` → `heightCm`
- `west` → `waistCm`

---

## 型ルール

- `age`, `heightCm`, `bust`, `waistCm`, `hip` は `number`
- 画像パス・URLは `string`
- 日付は `YYYY-MM-DD` 形式の `string`
- 時刻は `HH:mm` 形式の `string`
- 空値は `null` を許容する（空文字多用は避ける）

---

## 将来拡張ルール

- 追加項目は既存キーを壊さずに追加する
- 廃止予定キーは即削除せず、移行期間を設ける
- カスタム項目が必要な場合のみ `customFields` を使用する

---

## 補足

- 本ルールは初期版。実装開始時にページ単位で追記・更新する。
