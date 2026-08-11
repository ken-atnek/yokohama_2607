/* =======================================
 * 横浜ダンディー ワンデイランキングページ
 * URL: /src/app/dandy/oneday_ranking/page.tsx
 * Referenced in: /src/app/dandy/oneday_ranking/page.tsx
 * Created: 2026-08-03
 * Last updated: 2026-08-11
 * ======================================= */
import type { Metadata } from 'next';
import LayoutWrapperMain from '@/components/dandy/LayoutWrapperMain';
import { isRealProduction } from '@/lib/env';
import OneDayRanking from '@/components/common/oneday/OneDayRanking';
import { withCanonical } from '@/lib/seo';

export const metadata: Metadata = withCanonical('/dandy/oneday_ranking/', {
  title: '横浜 風俗(ヘルス) ランキング｜横浜ダンディー(関内・曙町)',
  description: isRealProduction
    ? '横浜ダンディーのワンデイランキングページです。店舗キャストのデイリーランキングを掲載しています。'
    : undefined,
});

export default function DandyOneDayRankingPage() {
  return (
    <LayoutWrapperMain hideShopLeftOnMobile>
      <OneDayRanking
        shop="dandy"
        jsonPath="/db/contents/dandy/OneDayRanking.json"
      />
    </LayoutWrapperMain>
  );
}
