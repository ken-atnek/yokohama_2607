## プロジェクト概要

Next.js 15 App Router + TypeScript + SCSS による静的サイト生成プロジェクト

## 環境構築

### 初期セットアップ

```bash
npx create-next-app@latest . --typescript
# App Router: Yes を選択
```

### フォーマッタ設定（必須）

`.prettierrc` をプロジェクト作成時に必ず配置する。

```json
{
  "tabWidth": 2,
  "useTabs": false,
  "semi": true,
  "singleQuote": true,
  "trailingComma": "es5",
  "bracketSpacing": true,
  "endOfLine": "lf",
  "overrides": [
    {
      "files": ["*.scss", "**/*.scss"],
      "options": {
        "tabWidth": 4,
        "printWidth": 80,
        "proseWrap": "always",
        "singleQuote": false
      }
    },
    {
      "files": ["*.ts", "*.tsx"],
      "options": {
        "tabWidth": 2,
        "singleQuote": true
      }
    }
  ]
}
```

`.editorconfig` もプロジェクト作成時に必ず配置する。

```ini
root = true

[*]
charset = utf-8
end_of_line = lf
insert_final_newline = true
trim_trailing_whitespace = true
indent_style = space
indent_size = 2

[*.scss]
indent_size = 4

[*.md]
trim_trailing_whitespace = false
```

### 開発依存パッケージ

```bash
npm install -D prettier sass stylelint stylelint-config-standard-scss stylelint-scss rimraf cross-env
```

### ESLint 設定（必須）

`eslint.config.mjs` をプロジェクト作成時に必ず配置する。

```javascript
import { defineConfig, globalIgnores } from "eslint/config";
import nextVitals from "eslint-config-next/core-web-vitals";
import nextTs from "eslint-config-next/typescript";

const eslintConfig = defineConfig([
  ...nextVitals,
  ...nextTs,
  // Override default ignores of eslint-config-next.
  globalIgnores([
    // Default ignores of eslint-config-next:
    ".next/**",
    "out/**",
    "build/**",
    "next-env.d.ts",
  ]),
]);

export default eslintConfig;
```

### Stylelint 除外設定（必須）

`.stylelintignore` をプロジェクト作成時に必ず配置する。

```gitignore
node_modules
.next
out
build
```

### next.config.ts

```typescript
import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  output: "export", // 静的エクスポート必須
};

export default nextConfig;
```

### scripts運用（推奨）

`package.json` の scripts は次を基準にする。

```json
{
  "scripts": {
    "dev": "next dev",
    "build": "next build",
    "build:demo": "cross-env NEXT_PUBLIC_IS_REAL_PROD=false NEXT_PUBLIC_METADATA_BASE=https://demo-yokohama.dandy-g.jp/ next build && rimraf out/db",
    "build:prod": "cross-env NEXT_PUBLIC_IS_REAL_PROD=true NEXT_PUBLIC_METADATA_BASE=https://www.dandy-g.jp/ next build && rimraf out/db",
    "start": "next start",
    "lint": "eslint .",
    "lint:style": "stylelint \"src/**/*.scss\"",
    "lint:style:fix": "stylelint \"src/**/*.scss\" --fix"
  }
}
```

- `build:demo` はデモURL `https://demo-yokohama.dandy-g.jp/` を使用する
- `build:prod` は本番URL `https://www.dandy-g.jp/` を使用する
- `out/db` は build 後に削除する

### next.config.ts（開発運用の推奨設定）

ローカルネットワーク確認や他プロジェクトとの設定統一のため、以下を基準とする。

```typescript
import type { NextConfig } from 'next';
import path from 'path';
const isProd = process.env.NODE_ENV === 'production';

const nextConfig: NextConfig = {
  reactStrictMode: true,
  experimental: {
    optimizeCss: false,
  },
  sassOptions: {
    includePaths: [path.join(__dirname, 'src/styles')],
  },
  output: 'export',
  trailingSlash: true,
  images: { unoptimized: true },
  allowedDevOrigins: ['192.168.9.21', '192.168.7.21'],

  ...(isProd && {
    assetPrefix: '',
  }),
};

export default nextConfig;
```
