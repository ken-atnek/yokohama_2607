/* =======================================
 * クラブダンディー ワンデイランキングページ
 * URL: /src/app/club_dandy/oneday_ranking/page.tsx
 * Referenced in: /src/app/club_dandy/oneday_ranking/page.tsx
 * Created: 2026-08-03
 * Last updated: 2026-08-03
 * ======================================= */
import type { Metadata } from 'next';
import LayoutWrapperMain from '@/components/club_dandy/LayoutWrapperMain';
import { isRealProduction } from '@/lib/env';
import OneDayRanking from '@/components/common/oneday/OneDayRanking';

export const metadata: Metadata = {
  title: 'クラブダンディー ワンデイランキング',
  description: isRealProduction
    ? 'クラブダンディーのワンデイランキングページです。店舗キャストのデイリーランキングを掲載しています。'
    : undefined,
};

export default function ClubDandyOneDayRankingPage() {
  return (
    <LayoutWrapperMain hideShopLeftOnMobile>
      <OneDayRanking
        shop="club_dandy"
        jsonPath="/db/contents/club_dandy/OneDayRanking.json"
      />
    </LayoutWrapperMain>
  );
}
