# バックエンド REVIEW_FIX_TRACKER

## 目的

公開サイト連携バックエンドのレビュー指摘について、妥当性確認と対応状況を管理します。

このファイルは、レビュー指摘を直接実装するための表ではありません。

## IDルール

バックエンド指摘は `BE-001` のように採番します。

## ステータス定義

| ステータス | 意味 |
| --- | --- |
| 未確認 | 指摘を受けたが、Codexで事実確認していない |
| 確認中 | Codexが実リポジトリで確認中 |
| 採用 | 事実確認済みで対応対象 |
| 保留 | 仕様判断、DB確認、本番環境確認などが必要 |
| 対応不要 | 誤指摘、仕様上問題なし、依頼範囲外、過剰提案 |
| 対応済み | 採用項目の修正と確認が完了 |
| 再レビュー中 | 修正後Claude再レビューの確認中 |
| 再レビュー済み | 修正後Claude再レビューとChatGPT整理が完了 |
| 完了 | 最終回答・diffレビューまで完了 |

## 管理テーブル

| ID | 優先度 | 指摘元 | 指摘内容 | 対象機能 | 対象ファイル | Codexによる事実確認 | 対応方針 | 採否 | ステータス | 修正結果 | 修正後Claude再レビュー結果 | 最終状態 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| BE-001 | High | Claude third-party review | H-1: From display-name raw UTF-8 | Phase10 メールテンプレート・送信ワーカー | `public/sys/lib/src/DandyNotification/Mail/NativeMailTransport.php`<br>`tests/notification/phase10/run_unit_tests.php` | `From: ダンディーグループ <no-reply@dandy-g.jp>` がraw UTF-8で出力されていた。Phase10正本ではFrom/headerはtransport責務。 | display-nameのみRFC 2047 encoded-word化し、addr-specはASCIIのまま維持する。 | 採用 | 完了 | `From: =?UTF-8?B?...?= <no-reply@dandy-g.jp>` へ修正。decode display-name一致、From物理行長確認を追加。 | 修正後Claude再レビューは未実施。初回Claude third-party reviewはPASS WITH ITEMS。採用項目修正後はChatGPT最終レビュー、php-l、Phase10 unit/static、DB integration、Phase9 regressionでPASS。 | CLOSED / Phase10 FROZEN |
| BE-002 | Medium | Claude third-party review | M-1: Subject encoded-word 75文字超過 / folding不足 | Phase10 メールテンプレート・送信ワーカー | `public/sys/lib/src/DandyNotification/Mail/NativeMailTransport.php`<br>`tests/notification/phase10/run_unit_tests.php` | 正式件名 `DANDY-出勤お知らせメール【更新情報】` はsingle encoded-wordで80 charsとなり、RFC 2047上のencoded-word 75 chars制限を超過していた。 | `mb_encode_mimeheader()` を使用し、`Subject: ` 分のindentを考慮してfoldingする。 | 採用 | 完了 | encoded-word長、physical header line長、CRLF + SPACE folding、mb_decode_mimeheader round-tripのtestを追加。 | 修正後Claude再レビューは未実施。初回Claude third-party reviewはPASS WITH ITEMS。採用項目修正後はChatGPT最終レビュー、php-l、Phase10 unit/static、DB integration、Phase9 regressionでPASS。 | CLOSED / Phase10 FROZEN |
| BE-003 | Low | Claude third-party review | L-1: header injection check | Phase10 メールテンプレート・送信ワーカー | `public/sys/lib/src/DandyNotification/Mail/NativeMailTransport.php` | `buildHeaders()` は静的headerのみで、user-controlled valueは入っていない。subject / recipient側の改行拒否も既存実装で確認済み。 | 追加修正しない。 | 対応不要 | 完了 | 修正なし。 | 修正後Claude再レビューは未実施。初回Claude third-party reviewで対応不要項目として整理。 | 対応不要 / Phase10 FROZEN |
| BE-004 | Low | Claude third-party review | L-2: MariaDB 10.4 FOR UPDATE fallback時のlock wait timeout専用counterなし | Phase10 メールテンプレート・送信ワーカー | `public/sys/lib/src/DandyNotification/Database/DatabaseCapabilities.php`<br>`public/sys/lib/src/DandyNotification/Repository/DeliveryRepository.php`<br>`public/sys/lib/src/DandyNotification/Service/DeliverySendService.php` | MariaDB 10.4では `FOR UPDATE` fallbackを使用する。lock wait timeout等はThrowable catch、rollback、`transaction_error_count`、logへ入る。専用counterは未実装。 | 安全性bugではなくobservability改善として保留する。Phase10 freeze blockerではない。 | 保留 | 保留 | 修正なし。 | 修正後Claude再レビューは未実施。初回Claude third-party reviewでnon-blocking保留項目として整理。Phase10 freeze blockerではない。 | 保留 / non-blocking / Phase10 FROZEN |
| BE-005 | Low | Claude third-party review | P11-01: Current DB regression未分離 | Phase11 privacy/footer差分 + current regression | `tests/notification/phase11/`<br>`tests/notification/phase10/run_db_integration_tests.php` | Phase11配下にcurrent unit regressionは存在するが、current DB regression runnerは未分離。Phase10 historical DB integration coverageは存在する。今回のPhase11差分でclaim / retry / token lifecycle / mail-unit / stale / transactionのDBロジックは変更していない。production deploy前必須というPhase11正本上の明示要件はない。 | Current DB regression分離は有用だが、Go-Live representative smokeとfinal Production health check PASS後もnon-blocking follow-up candidateとして保留する。 | 保留 | 保留 | 修正なし。追加実装なし。 | Claude third-party reviewはPASS WITH FINDINGS。Codex実リポジトリ確認COMPLETE。ChatGPT final reviewでP11-01は保留 / non-blocking、Phase11 code review blockerではないと整理。 | 保留 / non-blocking / follow-up candidate |
| BE-006 | Info | Claude third-party review | P11-02: common footer FIXED文面一致確認 | Phase11 privacy/footer差分 | `public/sys/lib/src/DandyNotification/Mail/NotificationMailRenderer.php`<br>`tests/notification/phase11/run_unit_tests.php` | `commonFooterLines()` のFIXED文面がPhase11正本と一致し、7 mail type footer regressionもPASSしている。 | 問題指摘ではなく仕様一致の確認事項。追加修正しない。 | 対応不要 | 完了 | 修正なし。 | Claude third-party reviewはPASS WITH FINDINGS。Codex実リポジトリ確認COMPLETE。ChatGPT final reviewで対応不要として整理。 | 対応不要 / CLOSED |
| BE-007 | Low | Claude third-party review | OPT-C-01: explicit empty path sentinelの意図コメント不足 | Phase11 Option C Production Config Path Resolution | `public/sys/lib/bootstrap.php` | bootstrapでexplicit `''` をinvalid sentinel `' '` へ変換しており、ConfigLoaderのenv fallbackを防ぎ、explicit empty pathをFAIL / no env fallback / no fixed fallbackにする意図があった。runtime bugなし、security issueなし、maintenance concernあり。 | 処理意図を短いコメントで明記する。ConfigLoaderは変更しない。 | 採用 | 完了 | `public/sys/lib/bootstrap.php` へ `// Keep '' and '0' explicit paths invalid instead of allowing ConfigLoader to fall back to the environment.` を追加。後続OPT-C-03対応によりコメントは`''`と`'0'`の両方を含む内容へ更新。 | 修正後Claude再レビューは未実施。Claude third-party reviewはPASS WITH FINDINGS、Codex実リポジトリ確認COMPLETE、採用項目修正後はChatGPT final review PASS / FINAL。 | CLOSED / Option C PASS / FINAL |
| BE-008 | Info | Claude third-party review | OPT-C-02: explicit empty path時のConfigLoader診断文言がenv未設定風になる | Phase11 Option C Production Config Path Resolution | `public/sys/lib/src/DandyNotification/Config/ConfigLoader.php` | explicit `''` がinvalid sentinel経由でConfigLoaderへ渡る場合、例外文言は `DANDY_NOTIFICATION_CONFIG is not set...` 系になる。fail-closedは成立し、DB/mailへ進まず、通常production entrypointはexplicit pathを渡していない。 | 診断性改善だけのためにConfigLoader共通処理を変更しない。Phase10/11共通基盤への影響を避ける。 | 対応不要 | 完了 | 修正なし。ConfigLoader変更なし。 | Claude third-party reviewはPASS WITH FINDINGS。Codex実リポジトリ確認COMPLETE。ChatGPT整理で対応不要 / CLOSED。 | 対応不要 / CLOSED / Option C PASS / FINAL |
| BE-009 | Medium | ChatGPT final review | OPT-C-03: explicit `'0'` がConfigLoaderのfalsey fallbackでenvへfallbackする | Phase11 Option C Production Config Path Resolution | `public/sys/lib/bootstrap.php`<br>`tests/notification/phase11/run_unit_tests.php` | 修正前、explicit `'0'` + valid env temp configでenv configへfallbackする挙動を実行ベースで再現。DB接続0、mail0、production0。Phase11 FIXED仕様の「不正explicitはFAIL、env/fixed fallbackなし」と不整合。 | bootstrapでexplicit `''` と `'0'` をinvalid sentinel `' '` へ変換し、Phase11 Option C regressionへexplicit `'0'` caseを追加する。ConfigLoaderは変更しない。 | 採用 | 完了 | `return ($explicitConfigPath === '' || $explicitConfigPath === '0') ? ' ' : $explicitConfigPath;` へ最小修正。Phase11 unitにexplicit `'0'` + valid envでConfigException / no fallbackのregressionを追加。 | Claude findingではない。ChatGPT final reviewで追加発見し、Codexで再現確認と最小修正を実施。全regression PASS、ChatGPT final review PASS / FINAL。 | CLOSED / blocker resolved / Option C PASS / FINAL |
| BE-010 | Medium | Go-Live gap analysis | P11-EMPTY-MAIL-CONFIG-01: Production empty-mail正式初期値 / confirmation.consent_version未固定 | Phase11 Go-Live Production Config | `backend_project/docs/specification/出勤お知らせメールシステム_フェーズ11本番メール送信経路・認証・運用開始追加確定仕様書_2026-08-21.txt`<br>`backend_project/docs/specification/config/notification_config.example.php`<br>`tests/notification/phase11/run_unit_tests.php` | Production actual configはsafe baselineとして `notification_empty_mail_receive_enabled=false` / `notification_delivery_create_enabled=false` / `notification_send_enabled=false` / `notification_dry_run=true`、empty_mail max/rateとconfirmation.consent_versionは未反映。Production初期運用値はmax_bytes=262144、email=3600/12、email_cast=3600/5、global=300/200、consent_version=2026-08-31に確定。ConfigLoaderはpositive integer validationとconsent_version format validationを維持し、完全一致validationは追加しない。 | 正本仕様、config example、Phase11 unit、Production actual configへ正式初期値を反映する。feature ONは別工程で行う。 | 採用 | 完了 | 正本仕様へProduction initial operating valuesを追加。config exampleを正式初期値へ更新。Phase11 unitにProduction正式profile load確認、safe baseline null許容、feature ON時null拒否確認を追加。Production actual configへmax_bytes=262144、email=3600/12、email_cast=3600/5、global=300/200、consent_version=2026-08-31を反映。safe feature flags維持、runtime変更なし。 | Production actual config反映後、php syntax PASS、Production ConfigLoader load PASS、正式値一致PASS、safe feature flags PASS、side effect 0。 | CLOSED / FINAL |
| BE-011 | Medium | ChatGPT review / Claude third-party review confirmed | Phase11 canonical specification stale production status | Phase11 Go-Live Production Config | `backend_project/docs/specification/出勤お知らせメールシステム_フェーズ11本番メール送信経路・認証・運用開始追加確定仕様書_2026-08-21.txt`<br>`backend_project/docs/review/REVIEW_FIX_TRACKER.md` | Claude third-party reviewでCONFIRMED。Phase11正本に、現在のProduction実測後の状態と合わない古い「現時点」記述が残っていた。Production actual config反映前blocker YES、Severity Medium。 | 過去の2026-08-24調査記録は維持し、当時のProduction deploy、P11-MAIL-AUTH-01A結果、Return-Path/SPF/DKIM/DMARC、残項目を正本へ最小反映する。 | 採用 | 完了 | Phase11正本の§1、§2、§9-14、§17、§19、§23、§29-34を当時状態へ更新。当時のP11-MAIL-AUTH-01A補助verify_email test PASSとcanonical subscription_added Controlled Test未完了状態を分離。2026-09-07代表smoke後はcanonical subscription_added PASSとしてBE-014へ引継ぎ。§23と§31-32のPhase11 DONE/FROZEN/production activation境界、§2 REAL-MAIL-CONFIRM区分、§14/§29 Option C実装済み状態、§33 tracker更新事実、§34 Phase11 `-f` overrideを整合。runtime/test/config/Production変更なし。 | 修正後Claude再レビューは未実施。Codex docs-only反映とfinal diff review対象。 | CLOSED / FINAL |
| BE-012 | High | Go-Live preflight / ChatGPT code review | Settings Go-Live blocker: formal Go-Live flags下でsettings mutationがmaintenanceになる | Phase11 settings / settings-link / unsubscribe | `public/sys/lib/src/DandyNotification/Service/SettingsAuthenticationService.php`<br>`public/sys/lib/src/DandyNotification/Service/SettingsLinkRequestService.php`<br>`tests/notification/phase11/run_current_regression_tests.php` | 旧availabilityは `settings=true / delivery_create=true / send=false / dry_run=true` を前提にしており、正式Go-Live flags `settings=true / delivery_create=true / send=true / dry_run=false` でmaintenanceになった。 | settings mutation availabilityを `notification_settings_enabled && notification_delivery_create_enabled` へ分離し、send/dry_runはworker transmission controlとして扱う。 | 採用 | 完了 | Source fix PASS。Phase11 current regressionでSettingsLinkRequestService::request、SettingsAuthenticationService::preview/authenticate、NotificationSettingsService::updateQuietHours、UnsubscribeService::confirmSubscription/confirmAllをformal Go-Live flags下でexerciseしPASS。HTTP mutation中のdirect mail send 0を確認。Production deploy PASS。 | ChatGPT code review PASS。Claude review PASS。Codex repository fact-check PASS。Production controlled settings auth/update PASS。 | CLOSED / Production Go-Live PASS |
| BE-013 | High | Production Go-Live preparation / Claude review | RFC822 normalization wrapper / permanent `.procmailrc` receive route | Phase11 empty-mail receive route | `public/sys/cli/receive-empty-mail.php`<br>`public/sys/pop3.php`<br>`~/.procmailrc`<br>`~/.forward` | Production `procmail -f-` はpipe input先頭にMBOX `From ` separatorを付与し、従来のdirect pipeでは `malformed_header_name` になった。wrapperは先頭1行だけがliteral `From ` で始まる場合のみ除去し、`From:` headerやbody中の `From ...` は変更しない。 | RFC822 wrapperをProduction receive routeへ組み込み、`.procmailrc` からwrapper経由で `sys/pop3.php` を起動する。 | 採用 | 完了 | wrapper/procmail routeはProduction E2E PASS。final hash: wrapper `ac1c8eb2f9e9e20c0d0a9b39245665ed96c14840e877206e965b8d13432ff5f4`、`.procmailrc` `838d7083d60fdbb4a03654be2039e05306a81350f2bf19c8364d3189e34d0e61`、`.forward` unchanged。 | Claude review PASS、ChatGPT final review PASS、Production deploy PASS、final health check `PERMANENT_RECEIVE_ROUTE_HEALTH=PASS`。 | CLOSED / Production E2E PASS |
| BE-014 | High | Production Go-Live / final health check | Phase11 Production Go-Live activation、representative smoke、final Production health | Phase11 production operation | Production actual config<br>notification cron<br>Production DB read-only evidence<br>Production logs | 2026-09-07にformal Go-Live flagsを反映し、notification cronを1件登録。fresh controlled identityでverify_email、registration_complete、settings_link、subscription_addedの代表smokeを実施。 | Go-Live actualを正本仕様書、controlled real mail plan、REVIEW_FIX_TRACKERへ反映し、Phase11 Freeze review可能な状態へ整合する。 | 採用 | 完了 | Go-Live activation PASS。notification cron count 1 / continuing YES。4 deliveries sent: verify_email #18、registration_complete #19、settings_link #20、subscription_added #21、すべてattempt_count=1 / actual receipt YES / duplicate NONE / manual worker 0。final queue eligible=0 / stale=0。post-Go-Live failed=0、transport_failure=0、send_outcome_unknown=0、fatal_error=0、receive failure=0、rate-limit rejection=0。token_not_found 1件はInformation / blocker NO。HTTP healthとbounce healthはNOT_OBSERVABLE / non-blocking。 | final Production health check PASS。新規Critical/High blocker NONE。Operational Follow-Upはfirst-day monitoring、DKIM/DMARC導入検討、cron log rotation、bounce mailbox観測方法整理。 | READY_FOR_FINAL_FREEZE_REVIEW / Phase11 runtime PASS |
| BE-015 | Medium | ChatGPT final freeze review | Phase11 canonical docs final current-state inconsistencies | Phase11 freeze docs alignment | `backend_project/docs/specification/出勤お知らせメールシステム_フェーズ11本番メール送信経路・認証・運用開始追加確定仕様書_2026-08-21.txt`<br>`backend_project/docs/review/REVIEW_FIX_TRACKER.md` | Runtime / ProductionはPASS。正本に、M-1 current feature flags、M-2 cron canonical / Production operational override、M-3 freeze / activation sequenceとcron overlap decisionのcurrent-state表現が古く読める箇所が残っていた。 | Production実体は変更せず、docs-onlyで現在状態へ整合する。BE-014のGo-Live履歴は維持する。 | 採用 | 完了 | §14へ2026-09-07 current Go-Live flagsを反映。§20で既存canonical scheduleとProduction operational overrideを分離。§21でcron overlap acceptable / external flock不要を決定済みに更新。§32でactual process順序を現在化。 | ChatGPT final freeze reviewでHOLDとなったdocs-only指摘。Codexで正本とtrackerを最小修正し、runtime / Production変更なし。 | CLOSED / Phase11 freeze docs aligned / READY_FOR_CHATGPT_FREEZE_RECHECK |

初回レビュー結果は `指摘元`、`優先度`、`指摘内容` に残し、Codex採否、修正結果、修正後Claude再レビュー結果、最終状態まで同じ行で追跡します。

## 実装ログ

### 2026-08-20

- 対応ID: BE-001, BE-002, BE-003, BE-004
- 実施内容: Phase10「メールテンプレート・送信ワーカー」のChatGPT critical review PASS、Claude third-party review PASS WITH ITEMS、採用項目H-1/M-1の修正完了、L-1対応不要、L-2 non-blocking保留を記録。
- 変更ファイル: `public/sys/lib/src/DandyNotification/Mail/NativeMailTransport.php`, `tests/notification/phase10/run_unit_tests.php`
- 確認方法: php-l、Phase10 unit/static、Phase10 DB integration、Phase9 regression、cleanup residual確認。
- 結果: php-l PASS、Phase10 unit/static PASS、Phase10 DB integration PASS、Phase9 regression PASS、cleanup residual 0、real mail() 0、SMTP未実装、cron未登録。
- 修正後Claude再レビュー: 未実施。初回Claude third-party reviewはPASS WITH ITEMS。採用項目修正後はChatGPT最終レビューおよびphp-l、Phase10 unit/static、Phase10 DB integration、Phase9 regressionで確認しPASS。
- 最終状態: Phase10 FROZEN（FROZEN日: 2026-08-20）

### 2026-08-25

- 対応ID: BE-005, BE-006
- 実施内容: Phase11 privacy/footer差分（privacy_policy_url完全廃止、privacyPolicyUrl API/frontend削除、confirmation/settings-request privacy link削除、全7 mail type共通footer化、real-send preflightからprivacy URL要件削除、Phase11 current regression分離）のレビュー結果を記録。
- 変更ファイル: `backend_project/docs/review/REVIEW_FIX_TRACKER.md`
- 確認方法: Claude third-party review PASS WITH FINDINGS、Codex実リポジトリ事実確認COMPLETE、ChatGPT final review PASS / FINAL。
- 結果: Phase11 privacy/footer implementation PASS / FINAL、Phase11 override regression PASS、Current unit regression PASS、Phase10 FROZEN boundary PASS、Critical 0、High 0、Medium 0、MUST FIX 0、SHOULD FIX 0、追加コード修正なし。
- Claude findings P11-01: Low / OPTIONALとして保留 / non-blocking / follow-up candidate。
- Claude findings P11-02: Info、仕様一致確認として対応不要 / CLOSED。
- 最終状態: Phase11 privacy/footer差分コードレビュー完了。Phase11 DONEではない。Phase11 FROZENではない。production deploy HOLD、real mail HOLD。

### 2026-08-26

- 対応ID: BE-007, BE-008, BE-009
- 実施内容: Phase11 Option C Production Config Path Resolutionのレビュー結果を記録。Codex local initial implementation、ChatGPT initial review、test seam最小補正、Claude third-party review PASS WITH FINDINGS、Codex実リポジトリ事実確認、ChatGPT採否整理、OPT-C-01採用修正、ChatGPT final review中のOPT-C-03追加発見、Codex再現確認、OPT-C-03最小修正、全regression PASSまで完了。
- 変更ファイル: `backend_project/docs/review/REVIEW_FIX_TRACKER.md`
- 確認方法: Claude third-party review PASS WITH FINDINGS、Codex実リポジトリ確認COMPLETE、ChatGPT final review PASS / FINAL、php-l、Phase11 unit、Phase11 current regression、Phase6 config validation、Phase9 unit/static。
- 結果: Option C specification PASS / FIXED、Option C local implementation PASS / FINAL、resolution precedence PASS、fail-closed PASS、security PASS、tests PASS、Claude review COMPLETE、Codex verification COMPLETE、ChatGPT final review PASS / FINAL、MUST FIX 0、SHOULD FIX 0、Open Option C findings 0、Production blocker from Option C 0。
- OPT-C-01: Claude third-party review由来。最終Severity Low、採用、CLOSED、Production blocker NO。bootstrapのinvalid sentinel意図コメントを追加済み。
- OPT-C-02: Claude third-party review由来。最終Severity Info、対応不要、CLOSED、Production blocker NO。ConfigLoader診断文言改善は行わない。
- OPT-C-03: ChatGPT final review由来。Severity Medium、採用、CLOSED。修正前はOption C fail-closed不整合としてdeploy前対応対象、修正後はblocker resolved。explicit `'0'` のenv fallbackを再現確認し、bootstrap最小修正とPhase11 Option C regression追加済み。
- Claude factual correction: production actual config未配置という趣旨の記載は既存正本と不一致のためblockerへ反映しない。production actual configは `/home/hpg-kobe/private/dandy_notification/dandy_notification_config.production.php` に存在、syntax PASS、structural validation PASS、safe feature flags PASS、owner `hpg-kobe`、mode `600` と記録済み。
- 修正後Claude再レビュー: 未実施。ChatGPT final review PASS / FINAL。
- 最終状態: Option C review PASS / FINAL。Phase10 FROZEN維持。Phase11 DONEではない。Phase11 FROZENではない。Backend production deploy HOLD、Frontend deploy HOLD、Production DB HOLD、Controlled real mail HOLD。

### 2026-08-31

- 対応ID: BE-010
- 実施内容: P11-EMPTY-MAIL-CONFIG-01のProduction正式初期値とconfirmation.consent_versionを正本仕様、config example、Phase11 unit testへ反映。
- 確定値: empty_mail.max_bytes=262144、rate_limit.email=3600/12、rate_limit.email_cast=3600/5、rate_limit.global=300/200、confirmation.consent_version=2026-08-31。
- 変更方針: ConfigLoaderへProduction完全一致validationは追加しない。Production actual configとfeature flagsは変更しない。
- Production actual config反映: PASS。php syntax PASS、Production ConfigLoader load PASS、正式値一致PASS、safe feature flags PASS、side effect 0。
- 最終状態: CLOSED / FINAL。

- 対応ID: BE-011
- 実施内容: Phase11正本のstale production statusを現在状態へ最小補正。Production deploy、P11-MAIL-AUTH-01A、Return-Path、send IP、SPF、DKIM none、DMARC none、残OPEN項目を再分類。§23と§31-32のPhase11 DONE/FROZEN/production activation境界、§2 REAL-MAIL-CONFIRM区分、§14/§29 Option C実装済み状態、§33 tracker更新事実、§34 Phase11 `-f` overrideを整合。
- 変更方針: 過去の2026-08-24調査記録はhistorical recordとして維持。当時のP11-MAIL-AUTH-01A補助verify_email test PASSとcanonical subscription_added Controlled Test未完了状態を分離。2026-09-07代表smoke後はcanonical subscription_added PASSとしてBE-014へ引継ぎ。runtime/test/config/Productionは変更しない。
- 最終状態: CLOSED / FINAL。

### 2026-09-07

- 対応ID: BE-012, BE-013, BE-014, BE-015
- 実施内容: Phase11 Production Go-Live activation、permanent receive route、representative smoke、final Production health checkのactual evidenceを最終記録。
- Production runtime: `/home/hpg-kobe/public_html/dandy/yokohama_2607`、PHP `/usr/local/bin/php`、PHP 8.4.25。
- Formal Go-Live flags: `notification_empty_mail_receive_enabled=true`、`notification_delivery_create_enabled=true`、`notification_confirmation_enabled=true`、`notification_settings_enabled=true`、`notification_send_enabled=true`、`notification_dry_run=false`。
- Permanent receive route: `MTA -> /usr/bin/procmail -f- -> ~/.procmailrc -> /usr/local/bin/php -q sys/cli/receive-empty-mail.php -> sys/pop3.php`。RFC822 wrapperによりMBOX先頭 `From ` separatorを先頭1行のみ除去し、Production E2E PASS。
- Notification cron: `send-deliveries.php` cron registered count 1、every 1 minute、continuing YES。frequencyはGo-Live時のOPERATIONAL_RECOMMENDATIONであり、canonical fixed valueではない。
- Settings Go-Live blocker: formal Go-Live flags下でsettings mutation availabilityを確認し、source fix / regression / Production deploy / settings auth-update PASS。CLOSED_IN_PRODUCTION。
- Representative smoke: fresh controlled identityでverify_email #18、registration_complete #19、settings_link #20、subscription_added #21を確認。すべてsent、attempt_count=1、actual receipt YES、duplicate NONE、manual worker 0。
- Final controlled graph: subscriber active、subscriptions 2 active、verify token used、manage_settings token used、quiet hours enabled 720-1200。
- Final health: eligible=0、stale=0、post-Go-Live failed=0、transport_failure=0、send_outcome_unknown=0、fatal_error=0、receive failure=0、rate-limit rejection=0、cron stderr health PASS、source integrity PASS。
- HTTP health: NOT_OBSERVABLE。accessible error log候補に `/wgmailsys/` error evidence=0。ただしaccess logベースのHTTP 5xx総数は観測不可。Go-Live blocker NO。
- Bounce health: NOT_OBSERVABLE。bounce mailbox read手段は未確立。controlled email全4通actual receiptは確認済みだが、bounce mailboxそのものを確認したとは記録しない。Go-Live blocker NO。
- token_not_found: `2026-09-07T09:00:18.926427Z` のinfo 1件。使用済みsettings tokenへの後続再アクセスと整合し、Information / No action / blocker NO。
- Mail auth: Envelope From `bounce@dandy-g.jp`、Header From `ダンディーグループ <no-reply@dandy-g.jp>`、sending IP `133.18.103.245`、SPF PASS、SPF alignment PASS、DKIM none、DMARC none。
- Operational Follow-Up: first-day monitoring継続、DKIM導入検討、DMARC導入検討、cron log rotation長期運用検討、bounce mailbox観測方法の整理。いずれもPhase11 Freeze blockerではない。
- Final freeze docs correction: ChatGPT final freeze reviewのM-1 / M-2 / M-3指摘をdocs-onlyで反映。current Go-Live flags、Production operational cron override、cron overlap decision、actual freeze / activation sequenceを正本へ整合。runtime / Production変更なし。
- 最終状態: Phase11 GO-LIVE ACTIVE / RUNTIME VALIDATION PASS / READY_FOR_FINAL_FREEZE_REVIEW。Phase11 FROZENはChatGPT final freeze review後に別途判定。

## 保留事項

- 仕様判断が必要な項目を記録する
- DBまたは本番環境の確認が必要な項目を記録する
- 判断者と判断期限が分かる場合は追記する

### BE-004: MariaDB 10.4 FOR UPDATE fallback時のlock wait timeout専用counter

- 状態: 保留 / non-blocking
- 対象: Phase10 メールテンプレート・送信ワーカー
- 内容: MariaDB 10.4では `FOR UPDATE SKIP LOCKED` ではなく `FOR UPDATE` fallbackを使用する。
- 現状: lock wait timeout等はThrowable catch、rollback、`transaction_error_count`、logへ入る。
- 未実装: lock wait timeout専用counter。
- 判断: 安全性bugではなくobservability改善。Phase10 freeze blockerではない。

### BE-005: Phase11 Current DB regression未分離

- 状態: 保留 / non-blocking / follow-up candidate
- 対象: Phase11 privacy/footer差分 + current regression
- 内容: Phase11配下にcurrent unit regressionは存在するが、current DB regression runnerは未分離。
- 現状: Phase10 historical DB integration coverageは存在する。Phase10 historical testはPhase10 FROZEN証跡として変更しない。
- 未実装: Phase11 current DB regression runner。
- 判断: 分離自体は有用だが、今回のPhase11差分でclaim / retry / token lifecycle / mail-unit / stale / transactionのDBロジックは変更していない。production deploy前必須というPhase11正本上の明示要件はない。
- 現在位置: Go-Live representative smokeとfinal Production health checkはPASS済み。BE-005は引き続き正式blockerではなく、non-blocking follow-up candidateとして残す。

