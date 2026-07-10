# キャスト紹介サイト サイト構成仕様

## 概要

- このサイトはキャスト紹介サイト
- レスポンシブ対応（PC/SP共通）
- 構成は「グループTOP + 各店舗下層ページ」の2階層

---

## サイト構成（確定）

- グループTOP: 横浜ダンディーグループ
- 下層店舗（3店舗）:
  - 横浜ダンディ
  - ミスターダンディ
  - クラブダンディ

想定URL（仮）:

```txt
/                    （グループTOP）
/dandy/     （横浜ダンディ）
/mr_dandy/           （ミスターダンディ）
/club_dandy/         （クラブダンディ）
```

---

## 店舗配下ページ（確定）

- `/{shop}/casts/`
  - キャスト一覧
- `/{shop}/casts/{cast-id}/`
  - キャスト詳細
- `/{shop}/attendance/`
  - 出勤情報（日ごと・キャストごと）
- `/{shop}/realtime/`
  - リアルタイム情報
- `/{shop}/system/`
  - システム

※ `{shop}` は `dandy` / `mr_dandy` / `club_dandy`

---

## ページ仕様（現行踏襲ベース）

### 1) キャスト一覧

- 初期表示項目は現行サイト踏襲
- 絞り込み/並び替えは初期実装に含める
- 一覧の読み込み方式は無限スクロール
- 表示項目や条件は将来変更を前提に拡張可能な設計にする

### 2) キャスト詳細

- 現行で表示している項目は基本必須
- 追加項目が出ることを前提に拡張可能な設計にする
- 任意項目（初期）:
  - タグ
  - 店舗コメント
  - 指名料
  - 動画
  - ブログリンク
- 写真表示はカルーセル形式
- 写真枚数は上限なし

### 3) 出勤情報

- 2パターンを用意する
  - 日別表示（`schedule.php` 相当）
  - 週間/一覧表示（`weeklist.php` 相当）
- ステータス文言は現行踏襲（例: `TEL確認` / `公休日` / 時間表示）

### 4) リアルタイム情報

- `realtime.html` 相当の仕様で実装する

### 5) システム

- `system.html` 相当の仕様で実装する

---

## グループ共通ページ（確定）

- `/information/`
  - お知らせ系ページ
  - 旧 `/topics/` と `/news/` は統合前提で運用
  - 将来的な再分離は可能な構成で管理

---


## `public/db` 構成方針（確定）

- 公開データ置き場は `public/db/` とする（`src/data/` の TS モジュール群と名前が紛らわしいため `data` は使わない）
- `db` 直下は用途別（種別ファースト）に分ける
  - `cast/`: キャストデータ用（現状は空、今後追加）
  - `contents/`: キャスト以外の全データ。神戸案件の `public/data` と同じく「グループ用」と「店舗用」でフォルダを分ける
- `contents/` 配下のフォルダ名は、URLスラッグと対応する英字命名にする
- グループTOP用の共通データは `contents/group` にまとめる

想定構成:

```txt
public/
└─ db/
   ├─ cast/
   └─ contents/
      ├─ group/
      ├─ dandy/
      ├─ mr_dandy/
      └─ club_dandy/
```

命名ルール:

- グループTOP用: `contents/group`
- 店舗用: URLスラッグと同じ
  - `contents/dandy`
  - `contents/mr_dandy`
  - `contents/club_dandy`
- JSONファイル名は、神戸案件に近い命名を基本維持してよい

パス分岐例:

```ts
if (pathname.startsWith('/dandy')) {
  basePath = '/db/contents/dandy/ReciprocalLink.json';
} else if (pathname.startsWith('/mr_dandy')) {
  basePath = '/db/contents/mr_dandy/ReciprocalLink.json';
} else if (pathname.startsWith('/club_dandy')) {
  basePath = '/db/contents/club_dandy/ReciprocalLink.json';
} else {
  basePath = '/db/contents/group/ReciprocalLink.json';
}
```

---

## 既存踏襲方針

- キャスト詳細の表示項目は既存サイトを基本踏襲
- ニュース/トピックスの内容仕様は変更可能性あり（存在は維持）
- 店舗ページの主要機能は既存サイトの導線・情報構造を基本踏襲

---

## 運用メモ

- 仕様確定後にJSON設計へ着手する
