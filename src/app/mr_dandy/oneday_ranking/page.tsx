/* =======================================
 * ミスターダンディー ワンデイランキングページ
 * URL: /src/app/mr_dandy/oneday_ranking/page.tsx
 * Referenced in: /src/app/mr_dandy/oneday_ranking/page.tsx
 * Created: 2026-08-03
 * Last updated: 2026-08-03
 * ======================================= */
import type { Metadata } from 'next';
import LayoutWrapperMain from '@/components/mr_dandy/LayoutWrapperMain';
import { isRealProduction } from '@/lib/env';
import OneDayRanking from '@/components/common/oneday/OneDayRanking';
import { withCanonical } from '@/lib/seo';

export const metadata: Metadata = withCanonical('/mr_dandy/oneday_ranking/', {
  title: 'ミスターダンディー ワンデイランキング',
  description: isRealProduction
    ? 'ミスターダンディーのワンデイランキングページです。店舗キャストのデイリーランキングを掲載しています。'
    : undefined,
});

export default function MrDandyOneDayRankingPage() {
  return (
    <LayoutWrapperMain hideShopLeftOnMobile>
      <OneDayRanking
        shop="mr_dandy"
        jsonPath="/db/contents/mr_dandy/OneDayRanking.json"
      />
    </LayoutWrapperMain>
  );
}
