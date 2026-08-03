/* =======================================
 * 横浜ダンディー ワンデイランキングページ
 * URL: /src/app/dandy/oneday_ranking/page.tsx
 * Referenced in: /src/app/dandy/oneday_ranking/page.tsx
 * Created: 2026-08-03
 * Last updated: 2026-08-03
 * ======================================= */
import type { Metadata } from 'next';
import LayoutWrapperMain from '@/components/dandy/LayoutWrapperMain';
import { isRealProduction } from '@/lib/env';
import OneDayRanking from './OneDayRanking';

export const metadata: Metadata = {
  title: '横浜ダンディー ワンデイランキング',
  description: isRealProduction
    ? '横浜ダンディーのワンデイランキングページです。店舗キャストのデイリーランキングを掲載しています。'
    : undefined,
};

export default function DandyOneDayRankingPage() {
  return (
    <LayoutWrapperMain hideShopLeftOnMobile>
      <OneDayRanking />
    </LayoutWrapperMain>
  );
}
