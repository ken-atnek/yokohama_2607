## Next.js 15 App Router の制約

### ⚠️ 重要: params は Promise型を使わない

**Next.js 15では params が Promise になったが、静的エクスポート時は同期型で扱う**

#### ❌ 公式ドキュメント通り（動的レンダリング用）

```typescript
export default async function Page({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params; // 静的エクスポートでエラー
}
```

#### ✅ 静的エクスポート用（このプロジェクトの正解）

```typescript
export default function Page({ params }: { params: { id: string } }) {
  const { id } = params; // 同期的にアクセス
}
```

### generateMetadata / generateStaticParams

**静的エクスポート時は async 禁止**

#### ❌ NG

```typescript
export async function generateMetadata(): Promise<Metadata> {
  return { title: "Page" };
}

export async function generateStaticParams() {
  return [{ id: "1" }];
}
```

#### ✅ OK

```typescript
export function generateMetadata(): Metadata {
  return { title: "Page" };
}

export function generateStaticParams() {
  return [{ id: "1" }];
}
```

**理由**: `output: 'export'` 時は全て事前生成されるため、非同期処理は不要
