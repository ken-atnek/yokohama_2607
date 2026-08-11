/* =======================================
 * 横浜ダンディー 出勤情報ページ
 * URL: /src/app/dandy/schedule/page.tsx
 * Referenced in: /src/components/dandy/LayoutWrapperSub.tsx
 * Created: 2025-09-18
 * Last updated: 2026-08-11
 * ======================================= */
import LayoutWrapperSub from '@/components/dandy/LayoutWrapperSub';
import type { Metadata } from 'next';
import { isRealProduction } from '@/lib/env';
import ShopPageTitle from '@/components/common/ShopPageTitle';
import CastScheduleByDay from '@/components/common/schedule/CastScheduleByDay';
import { withCanonical } from '@/lib/seo';

export const metadata: Metadata = withCanonical('/dandy/schedule/', {
  title: '横浜 風俗(ヘルス) 出勤情報｜横浜ダンディー(関内・曙町)',
  description: isRealProduction
    ? '横浜ダンディーの出勤情報ページです。店舗在籍キャストの当日出勤情報を掲載しています。'
    : undefined,
});

export default function DandySchedulePage() {
  return (
    <LayoutWrapperSub>
      <ShopPageTitle titleJp="出勤情報" titleEn="schedule" shop="dandy" />
      <CastScheduleByDay />
    </LayoutWrapperSub>
  );
}
