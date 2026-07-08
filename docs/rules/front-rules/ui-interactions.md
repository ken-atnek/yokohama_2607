## ハンバーガーメニュー実装ルール（共通）

このプロジェクト以降の実装では、以下の構成を標準とする。

### 基本構成

- `button` は1つだけ配置する
- `button` は `position: fixed` で常時表示する
- メニュー本体は `overlay` + `panel` 構成にする
- 開閉状態は `isOpen` で管理する

### クラス制御

- `button` に `is-open` クラスを付与して見た目を切り替える
- `overlay` に `is-open` クラスを付与して表示状態を切り替える
- SCSS 側で `.hamburgerButton.is-open` / `.overlay.is-open` を使って制御する
- `className` の条件分岐は `clsx` を使用する（テンプレート文字列連結は避ける）

```tsx
className={clsx(styles.overlay, isOpen && styles["is-open"])}
```

### 表示アニメーション

- メニュー本体の出し入れは `transform` で制御する
- 例: `transform: translateY(-100%)` → `translateY(0)`
- `opacity` / `pointer-events` を併用して操作可能状態を管理する

### 操作仕様

- `aria-expanded` を付与する
- `aria-label` を付与する
- 外側クリックで閉じる処理を入れる

### 補足

- JSX 分岐で表示要素を増減させるより、クラス付与で状態を切り替える
- 見た目の調整は原則SCSS側で行う

---

## Sass生成ルール（追加）

- Sass は既存ファイルに合わせてネスト構造を優先する
- 既存の並び・責務を崩さず、最小差分で追記する
- `margin` / `line-height` / `letter-spacing` は必要な場合のみ追加する
- 不要な `className={styles.xxx}` は追加しない
- `className={styles.xxx}` は必要箇所のみに限定する（後編集しやすさ優先）
