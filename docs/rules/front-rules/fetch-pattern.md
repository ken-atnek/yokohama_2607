## データ取得パターン

`output: 'export'` のため、ランタイムのサーバーサイド処理は不可。
管理画面が `public/db/` 配下の JSON を書き換えるため、全データ取得はクライアントサイドで行う。

### `force-dynamic` は使用禁止

```typescript
// ❌ NG: output: 'export' と競合してビルドエラーになる
export const dynamic = "force-dynamic";
```

### 2種類のfetchパターン

| 用途                                             | 関数          | エラー表示               | キャッシュ制御                   |
| ------------------------------------------------ | ------------- | ------------------------ | -------------------------------- |
| マスター・設定JSON（空でも支障なし）             | `fetchJson()` | なし（fallback値で継続） | なし（デフォルト）               |
| コンテンツJSON（管理画面連動・エラー表示が必要） | 生 `fetch()`  | あり（`isError` state）  | `cache: 'no-store'` + `?t=${ts}` |

#### ✅ マスター・設定JSON: `fetchJson` を使う

```typescript
import { fetchJson } from "@/utils/fetchJson";
import { withBasePath } from "@/utils/withBasePath";

const [items, setItems] = useState<Item[]>([]);

useEffect(() => {
  fetchJson<Item[]>(withBasePath("/db/master/items.json"), []).then(setItems);
}, []);
```

#### ✅ コンテンツJSON（管理画面連動）: 生 `fetch` + `isError` を使う

```typescript
const [data, setData] = useState<DataType>({ items: [] });
const [isError, setIsError] = useState(false);

useEffect(() => {
  const path = withBasePath('/db/content/data.json');
  const ts = Date.now();

  fetch(`${path}?t=${ts}`, { cache: 'no-store' })
    .then((res) => {
      if (!res.ok) throw new Error('fetch failed');
      return res.json();
    })
    .then((json) => setData(json as DataType))
    .catch(() => setIsError(true));
}, []);

// JSX内でエラー表示
{isError ? <p>データの読み込みに失敗しました。</p> : null}
```

**理由**: `cache: 'no-store'` でブラウザキャッシュを確実に無効化し、管理画面の更新が即時反映される。

### 共有マスター型

マスターJSONの型定義は `src/types/master.ts` に一元管理。各ファイルでローカル定義しない。

```typescript
import type {
  SalaryUnitMaster,
  EmploymentTypeMaster,
  FacilityTypeMaster,
} from "@/types/master";
```
