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

### 補足して使用

- `age`
  - 数値で扱える場合は `number`
  - テキスト表記のみの場合を考慮して `null` を許容する
- `ageText`
  - 画面表示用の年齢文言
  - 数値年齢ではなくテキスト表記を出したい場合のみ使用する
  - 例: `20代後半`, `30代前半`

---

## 型ルール

- `age` は `number | null`
- `ageText` は表示用の `string`
- `heightCm`, `bust`, `waistCm`, `hip` は `number`
- 画像パス・URLは `string`
- 日付は `YYYY-MM-DD` 形式の `string`
- 時刻は `HH:mm` 形式の `string`
- 空値は `null` を許容する（空文字多用は避ける）

### 在籍一覧の表示用キー

- 横浜の在籍一覧では、サービス表示用に真偽値キーを持たせてよい
  - `serviceHealth`: `ヘルス` 表示のON/OFF
  - `serviceMat`: `マット` 表示のON/OFF
- 上記2項目は `boolean` で管理する
- サービス表示文言を配列で持つ方式（例: `serviceLabels`）は現時点では採用しない
- キャスト画像右上のアイコン表示は、次の3ジャンルで管理してよい
  - `badgeType`: `none | new | new_kirakira | trial | osusume | event`
  - `shopIconRank`: 店舗アイコンランキング順位。未設定時は `null`
  - `areaIconRank`: エリアアイコンランキング順位。未設定時は `null`
- `shopIconRank` / `areaIconRank` は現時点では数値管理とし、画像ファイル名はゼロ埋め2桁に合わせてよい
  - 例: `1` → `01.webp`

---

## ランキング系JSONの補足

- ランキングの順位 `rank` は `string` ではなく `number` を使う
  - 例: `"rank": 1`
- 表示件数はタブ単位で `displayCount` を持たせてよい
  - 例: エリアTOPは `20`、店舗TOPは `10`
- アコーディオンを使う場合は `accordion` を持たせてよい
  - `enabled`: 設置有無
  - `initialVisibleGroupCount`: 初期表示する `section` 数
  - `insertAfterGroup`: 何個目の `layoutGroups` の後にボタンを置くか
- レイアウトの区切り位置は `layoutGroups` の配列で管理してよい
  - 例: `[3, 2, 2, 3, 5, 5]`
  - この場合は `3件 → 2件 → 2件 → 3件 → 5件 → 5件` の順で区切る
- グレード表示は `gradeId` で管理してよい
- `gradeId` と表示文言の対応は `src/data/CastGradeData.ts` を参照する
- ランキング内のグレード表示文言は、現時点では `labelEn` を使用する
- `gradeId` の対応は次で固定する
  - `0`: なし
  - `1`: シルバー
  - `8`: レジェンド
  - `2`: ゴールド
  - `3`: `VIP`
  - `4`: グランド VIP
  - `5`: プレミアム
  - `6`: ダイヤモンド
  - `7`: ロイヤルクイーン
- 在籍一覧を `gradeId` ごとにブロック表示する場合、`0` の見出しは `normal` として扱ってよい
- 在籍一覧の表示順は `ロイヤルクイーン → ダイヤモンド → プレミアム → グランド VIP → VIP → ゴールド → シルバー → レジェンド → normal` としてよい
- ランキングJSON内の店舗識別子は、現行サンプルに合わせて `shopID` を使用してよい
  - 例: `"shopID": "dandy"`

---

## 将来拡張ルール

- 追加項目は既存キーを壊さずに追加する
- 廃止予定キーは即削除せず、移行期間を設ける
- カスタム項目が必要な場合のみ `customFields` を使用する

---

## 補足

- 本ルールは初期版。実装開始時にページ単位で追記・更新する。
