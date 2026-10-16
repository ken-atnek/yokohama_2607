# Public Site Integration Backend AGENTS

## 1. このファイルの目的

このファイルは、横浜ダンディーグループ Next.js 公開サイトと連携するバックエンド開発の入口です。

詳細仕様をここへ複製せず、作業ルール、AIの役割、確認工程、参照すべき正本資料を定義します。

## 2. 適用範囲

主対象は `backend_project/` 配下のドキュメント、仕様、workflow、review、rules です。

ただし、現行の公開サイト連携バックエンド実装は `../public/sys/` など `backend_project/` 外にも存在します。これらを調査・実装する場合も、本ファイルと `docs/` 配下の関連資料を必ず確認してください。

対象例:

- Next.js公開サイトから利用するPHP API
- フォーム送信バックエンド
- メール関連バックエンド
- 出勤お知らせメールシステム
- メール受信CLI
- メール認証
- 購読登録・解除
- 購読設定API
- cron / batch / CLI
- 外部サービス連携API
- 公開サイトから利用するDBアクセス処理
- 公開サイトと既存管理画面側データを橋渡しする処理

Next.js / TypeScript / SCSS / UI / SEO のフロント実装ルールは、原則としてルート `AGENTS.md` と `../docs/` 側を参照します。

## 3. backend_project の位置づけ

`backend_project` は、横浜ダンディーグループ全体の管理画面バックエンドではありません。

名称上の意味は、公開サイト連携バックエンド、または Frontend Integration Backend です。

主な責任:

- 公開サイトから呼び出すPHP APIの仕様管理
- メール、CLI、cron、batchの仕様管理
- 公開サイト連携用DB処理の仕様管理
- 既存管理画面データとの橋渡し仕様管理
- バックエンドAI開発工程、レビュー、チェックリスト、lessonsの管理

## 4. cp8_dandy との責任分界

`../../../cp8_dandy` は既存の店舗管理画面側バックエンドです。

主な役割:

- 女の子情報管理
- スケジュール管理
- リアルタイム管理
- ランキング管理
- 画像管理
- 管理画面フォーム、Ajax、DB更新、JSON生成

公開サイト連携バックエンドから `cp8_dandy` のDB、JSON、API、仕様を参照する場合があります。ただし、`backend_project` 側の都合だけで `cp8_dandy` の仕様、DB構造、JSON仕様、管理画面処理、管理画面側APIを変更してはいけません。

変更が必要と判断した場合は、実装せずに必要理由、影響範囲、対象ファイル、既存仕様との関係を報告してください。

## 5. 最優先ルール

- 実装前に、対象仕様書、既存コード、呼び出し元、呼び出し先を確認する
- 調査依頼の段階で勝手にコード編集しない
- 実装は最小変更を優先する
- 稼働中のPHP/API/CLI/cron/メール受信処理を、整理目的だけで移動しない
- 本番DB、本番サーバー、cron、`.procmailrc` は明示指示なしに変更しない
- 認証情報、APIキー、DB接続情報、秘密鍵、メール認証情報は表示・編集・出力しない
- 仕様書と現行実装が食い違う場合は、勝手にどちらかへ合わせず差異を報告する

### 判断の優先順位

判断が競合した場合は、「どのAIが言ったか」ではなく「何を根拠としているか」を優先します。

原則として以下の順序で判断します。

1. 今回の明示的なユーザー指示
2. 確定済みプロジェクト仕様書
3. 適用対象の `AGENTS.md`
4. プロジェクト内 `rules` / `workflows`
5. 実際のDB仕様・API仕様・既存コードとの整合性
6. Codex / ChatGPT / Claudeによる調査・解釈・提案

AIの提案を理由として確定仕様を勝手に変更しません。仕様書と現行実装が食い違う場合は、勝手にどちらかへ合わせず、差異を確認し、必要に応じてユーザーへ報告します。

Codex自身の推測も、確定仕様より上位には扱いません。

## 6. AIの役割分担

### Codex

Codexは、実リポジトリを直接確認する調査・実装担当です。

主な役割:

- 関連ファイル探索
- 呼び出し元・呼び出し先確認
- 既存コード調査
- DB処理、SQL、SESSION、API、JSON生成、cron、batch、メール処理の確認
- 影響範囲確認
- 最小変更での実装
- 構文確認、lint、test
- git diff確認

実装判断は、実リポジトリと確定仕様を根拠にします。

### Claude

Claudeは、独立した第三者レビュワーです。

Claudeには原則として実装を担当させません。Claudeの指摘は実装指示ではなく確認候補として扱います。

禁止:

- Claudeの指摘を無条件で採用する
- Claude回答のみを根拠にコード変更する
- Claudeの提案を確定仕様より優先する
- 大規模リファクタリング案をそのまま採用する

Claudeの指摘はCodexが実リポジトリで事実確認し、採用、保留、対応不要を判断します。

### ChatGPT

ChatGPTは、プロンプト設計、AI回答レビュー、工程整理担当です。

主な役割:

- Codex調査用プロンプト作成
- Codex実装用プロンプト作成
- Codex回答の論理・抜け漏れレビュー
- Claudeレビュー依頼プロンプト作成
- Claude回答の妥当性レビュー
- Claude指摘の整理
- Codexへ戻す確認事項整理
- REVIEW_FIX_TRACKER整理
- lessons候補整理

ChatGPTはユーザーのローカルリポジトリを直接参照できない前提です。ChatGPTの判断のみを根拠としてコード変更してはいけません。

ただし、コード、git diff、対象ファイル等がChatGPTへ明示的に共有された場合は、共有された範囲についてコードレビューを行うことがあります。

共有されていないリポジトリ全体との整合性、呼び出し関係、他ファイルへの影響等は、Codexが実リポジトリで確認します。

## 7. 標準開発フロー

標準工程は `docs/workflows/ai-development-flow.md` を参照してください。

概要:

1. プロジェクトルール・仕様確認
2. ChatGPTでCodex向け調査プロンプト作成
3. Codexで実リポジトリ調査
4. ChatGPTで調査回答レビュー
5. 必要に応じてCodex追加調査
6. ChatGPTでCodex向け初回実装プロンプト作成
7. Codexで最小変更の初回実装
8. ChatGPTでCodex実装報告・必要に応じてdiffレビュー
9. ChatGPTでClaude初回第三者レビュー用プロンプト作成
10. Claude Codeで初回第三者コードレビュー
11. ChatGPTでClaude回答の妥当性・優先度・過剰提案を整理
12. Codexが実リポジトリで指摘を事実確認
13. Codexが採用 / 保留 / 対応不要を判断。この段階では原則まだ修正しない
14. ChatGPTでCodexの採否判断をレビュー
15. ChatGPTで採用項目のみを対象としたCodex修正プロンプト作成
16. Codexで採用項目のみ修正
17. Codexで構文確認・テスト・git diff確認
18. 修正後Claude再レビューの要否を判断
19. 不要の場合はChatGPTで最終回答・diffレビュー
20. 必要な場合、ChatGPTでClaude修正後再レビュー用プロンプト作成
21. 必要な場合、Claude Codeで修正後コード再レビュー
22. 必要な場合、ChatGPTでClaude再レビュー結果をレビュー・整理
23. 再レビュー実施時は新規有効指摘なし / ありを判断。ありの場合はCodexで事実確認し、今回対応必須の重大項目のみ追加修正
24. 追加修正した場合はCodexで構文確認・必要なテスト・git diff再確認
25. ChatGPTで最終回答・diffレビュー
26. REVIEW_FIX_TRACKERへ記録
27. 再利用価値のある内容のみlessonsへ記録

## 8. 調査と実装の分離

PHP / SQL / DB / SESSION / API / JSON / cron / メール処理は副作用の可能性があります。

原則:

```text
調査
↓
原因確認
↓
影響範囲確認
↓
対応方針決定
↓
実装
```

調査段階ではコード編集、DB更新、cron変更、設定変更を行いません。

## 9. 最小変更原則

禁止:

- 不要なリファクタリング
- 不要なファイル構成変更
- DB構造変更
- 命名規則の全面変更
- コーディングスタイルの一括修正
- unrelatedな警告修正
- 依頼されていない仕様変更
- AI独自の改善を同時実装すること

改善案がある場合は、実装せず別途報告します。

## 10. バックエンド実装共通原則

- 既存PHPのinclude / require構成を維持する
- Web、CLI、cronで共通Serviceがある場合は既存構成を優先する
- APIレスポンス形式、HTTPステータス、JSONキーを既存仕様に合わせる
- DB更新と外部メール送信を同一トランザクションに含めない
- 旧DB、旧cron、旧 `.procmailrc` は切替完了まで削除しない
- feature flag、dry-run、send enabledの既存仕様を確認してから変更する
- 例外ログにはメールアドレス、token、認証情報などの機密値を出さない

## 11. セキュリティ・DB・API確認

変更内容に応じて、必要な項目だけ確認します。

- PHPバージョン互換性
- MySQL仕様
- SQL / prepared statement
- SQL Injection
- XSS
- CSRF
- 認証・認可
- SESSION / Cookie
- 入力値検証
- 出力エスケープ
- ファイル操作とパス検証
- JSONレスポンス
- HTTPステータス
- transaction / rollback
- ログ
- cron / batch / CLI
- メール送受信
- 既存データ互換性
- 他画面、他店舗への影響

詳細は `docs/rules/backend-checklist.md` を参照してください。

## 12. レビュー運用

Claudeレビューは `docs/workflows/claude-review-flow.md` と `docs/review/CLAUDE_REVIEW_REQUEST.md` を使用します。

Claude指摘をCodexへ戻す場合は `docs/workflows/claude-to-codex-review-fix-flow.md` を使用します。

レビュー指摘は直接実装せず、Codexが実リポジトリで事実確認してから採用、保留、対応不要を判断します。

Claudeレビューは、初回第三者レビューと修正後再レビューを分けて扱います。

- 初回第三者レビュー: Codexの初回実装後に、仕様矛盾、既存処理への影響、セキュリティ、DB/API/SESSION/メール/CLIなどを確認する
- 修正後再レビュー: Codexが採用項目を修正し、構文確認・テスト・git diff確認を終えた後、要否を判断してから実施する。実施する場合は、前回指摘、Codex採否、採用項目、修正内容、修正後diff、確認結果へ範囲を限定する

修正後再レビューは、新しい全面設計レビューではありません。新しい指摘が出た場合も、ChatGPTで整理し、Codexが事実確認してから採用、保留、対応不要を判断します。

標準は、初回Claudeレビューと修正後Claude再レビューの最大2段階です。重大な未修正、修正起因の高リスク不具合、確定仕様違反、セキュリティ問題が確認された場合のみ追加確認を検討します。

## 13. テスト・検証

PHPファイル変更後:

```bash
php -l {file}
```

JavaScriptファイル変更後:

```bash
node --check {file}
```

SQLやDB変更案がある場合は、対象DB、対象テーブル、目的、影響範囲、実行前確認SQL、実行後確認SQL、rollback可能性を明確にします。

`npm run build`、`composer install`、本番DB変更、cron変更など影響が大きい操作は、明示指示なしに実行しません。

## 14. REVIEW_FIX_TRACKER

バックエンド専用レビュー管理は `docs/review/REVIEW_FIX_TRACKER.md` で行います。

IDは `BE-001` のようにバックエンド用であることが分かる形式を基本とします。

TRACKERは、レビュー指摘を直接実装する表ではありません。指摘の妥当性確認と対応状況を管理する表です。

## 15. lessons

バックエンド専用lessonsは `docs/rules/lessons.md` に記録します。

記録対象:

- 再発しやすい問題
- AIが誤認しやすいバックエンド仕様
- 複数機能で共通する注意点
- PHP / SQL / API等で再利用可能な知見
- 調査方法として再利用可能な知見
- 横浜ダンディー固有の重要な落とし穴

単発の修正内容は、原則としてlessonsではなくREVIEW_FIX_TRACKERや作業記録へ残します。

## 16. 横浜ダンディー固有の必須参照資料

この一覧は、実リポジトリ上で存在確認した資料と配置に基づきます。

| 資料・配置 | 用途 | 参照する作業 |
| --- | --- | --- |
| `backend_project/docs/specification/出勤お知らせメールシステム_リニューアル仕様書_2026-08-01_確定仕様反映版.txt` | 出勤お知らせメール全体仕様の正本 | 登録、認証、購読、通知、メール、DB、cron |
| `backend_project/docs/specification/出勤お知らせメールシステム_フェーズ別実装手順書_2026-08-01_確定仕様反映版.txt` | フェーズ別の実装手順 | 実装順、検証、ロールバック |
| `backend_project/docs/specification/出勤お知らせメールシステム_フェーズ6追加確定仕様書_2026-08-10.txt` | 設定リンク、設定、解除の追加確定仕様 | settings / unsubscribe API |
| `backend_project/docs/specification/出勤お知らせメールシステム_フェーズ7公開状態同期追加確定仕様書_2026-08-11.txt` | 公開状態同期の追加確定仕様 | view同期、reconciliation、cp8 hook |
| `backend_project/docs/specification/出勤お知らせメールシステム_フェーズ8出勤変更イベント生成追加確定仕様書_2026-08-12.txt` | 出勤変更イベント生成の追加確定仕様 | schedule event、event_key、canonical state |
| `backend_project/docs/specification/DDL/fase1_DB_table_DDL_2026-08-03.sql` | 通知DBテーブルDDLの参照先 | DB確認、SQL確認 |
| `backend_project/docs/specification/DDL/fase1_DB_table_DDL_2026-08-03_up.sql` | DDL差分候補。正本関係は未確定 | DDL比較時のみ慎重に参照 |
| `backend_project/docs/specification/config/notification_config.example.php` | 通知設定例。秘密情報は含めない | 設定項目確認 |
| `../public/sys/` | 出勤お知らせメールの現行PHP API / CLI / lib実装 | API、CLI、メール、設定、購読 |
| `../public/sys/pop3.php` | 空メール受信CLI入口 | メール受信、mailto受付 |
| `../public/sys/api/` | confirmation / settings / unsubscribe系API | Next.js画面連携API |
| `../public/sys/cli/` | reconciliation等CLI | cron / batch |
| `../public/sys/lib/src/DandyNotification/` | 通知システム共通ライブラリ | Service / Repository / Mail / Security |
| `../public/backend/` | 公開側カウンター系PHP | 公開サイト用PHP処理 |
| `../public/db/` | 公開サイトが参照する生成済みJSONと画像 | CastList、details、schedule、realtime、ranking等 |
| `../../../cp8_dandy/.specification/女の子情報DB構成仕様書.md` | 女の子DB仕様 | t_girl_master2参照 |
| `../../../cp8_dandy/.specification/公開JSON構成仕様書.md` | 公開JSON仕様 | CastList、details、schedule、realtime、ranking |
| `../../../cp8_dandy/.specification/出勤カレンダー・リアルタイム待機情報仕様書.md` | 出勤・リアルタイム仕様 | t_work_list、schedule、realtime、Phase8 |
| `../../../cp8_dandy/.docs/girl/GIRL_JSON.md` | 公開JSON生成メモ | JSON生成元PHP、実装差分確認 |

## 17. 出勤お知らせメールシステム

出勤お知らせメールシステムは、公開サイト連携バックエンドの代表的な対象機能です。

主な範囲:

- Next.js側の登録導線
- mailtoによる受付
- メール受信CLI
- DB
- 仮登録
- 本登録
- 認証URL
- 認証済利用者の追加登録
- 購読管理
- 設定API
- リアルタイム通知条件
- 出勤通知メール
- cron / batch

詳細仕様は `backend_project/docs/specification/` 内の最新仕様書を正本とします。AGENTSへ仕様値を大量転記しません。

出勤お知らせメール関連を扱う場合は、少なくとも以下を確認してください。

- 空メール / mailto受付
- 仮登録
- 本登録
- 認証URL
- 認証済利用者の追加登録
- 購読状態
- 設定
- リアルタイム通知条件
- 指定時間変更
- 次回出勤日3件
- メールテンプレート
- 送信元
- 本番URL / デモURL
- DB
- メール受信CLI
- バックエンドAPI
- cron / batch

本番URLは `https://www.dandy-g.jp/` 基準であることを仕様書で確認します。デモURLやローカルURLを本番仕様として扱わないでください。
