# バックエンド lessons

## 目的

公開サイト連携バックエンドで再利用価値のある注意点、AIが誤認しやすい仕様、再発防止知見を記録します。

単発の修正内容は、原則として `../review/REVIEW_FIX_TRACKER.md` や作業記録へ残します。

## 記録対象

- 再発しやすい問題
- AIが誤認しやすいバックエンド仕様
- 複数機能で共通する注意点
- PHP / SQL / API等で再利用可能な知見
- 調査方法として再利用可能な知見
- 横浜ダンディー固有の重要な落とし穴

## lessons

### BE-LESSON-001: backend_project外の実装にもバックエンドルールが必要

現行の出勤お知らせメール実装は `public/sys/` にあり、`backend_project/AGENTS.md` の階層下にはない。

そのため、`public/sys/`、`public/backend/`、`public/db/` などの公開サイト連携バックエンドを扱う場合は、ルート `AGENTS.md` から `backend_project/AGENTS.md` と関連資料へ誘導する必要がある。

### BE-LESSON-002: cp8_dandyは参照対象であり、backend_projectの直接管理対象ではない

公開サイト連携バックエンドは、女の子DB、schedule、realtime、CastList、details、ランキングJSONなどで `cp8_dandy` の既存仕様に依存する。

ただし、`backend_project` 側の都合だけで `cp8_dandy` のDB構造、JSON仕様、管理画面処理、APIを変更してはいけない。変更が必要な場合は、理由と影響範囲を報告する。

### BE-LESSON-003: 仕様書は正本を1か所に集約する

出勤お知らせメール関連仕様はバックエンド専用仕様であり、`backend_project/docs/specification/` を正本とする。

同じ仕様書を旧 `docs/specification/` と新 `backend_project/docs/specification/` の両方に残すと、AIが旧版やコピーを正本として誤認しやすい。

### BE-LESSON-004: DDLの正本関係は仕様書の記述を優先する

`fase1_DB_table_DDL_2026-08-03.sql` と `fase1_DB_table_DDL_2026-08-03_up.sql` のように近い名前のDDLがある場合、ファイル名だけで正本判断しない。

フェーズ仕様書で正本関係が未確定とされているものは、勝手に統合・削除・採用しない。

### BE-LESSON-005: 日本語From表示名はraw UTF-8でheaderへ入れない

PHPの `mail()` 等で日本語display-nameを含むFrom headerを組み立てる場合、display-nameをraw UTF-8のままheaderへ直接入れない。

display-nameはRFC 2047 encoded-word化し、addr-specはASCIIのまま維持する。

### BE-LESSON-006: MIME Subjectはencoded-word長と物理header line長の両方を見る

RFC 2047 MIME encodingでは、encoded-word単体の75文字制限だけでなく、`Subject: ` 等のfield-nameを含めた物理header line長も考慮する。

`mb_encode_mimeheader()` を使う場合は、必要に応じて第5引数のindentへfield-name分の長さを渡し、folding位置を実際のheader行に合わせる。

### BE-LESSON-007: MIME header testはprefix確認だけで終わらせない

MIME headerのtestで `=?UTF-8?B?` のprefixだけを確認しても、長い日本語件名やfolding不備は検出できない。

最低限、encoded-word長、物理header line長、`CRLF + whitespace` folding、decode round-tripを確認する。

### BE-LESSON-008: 実送信workerは副作用前にfail-closed preflightを行う

実送信workerで必須URLやconfigが不足している場合、DB接続、claim、attempt_count更新、token生成、transport呼出より前にpreflightで停止する。

Rendererまで進んでから失敗させるだけではなく、副作用が発生する前にfail-closedにする。
