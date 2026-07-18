/* =======================================
 * 横浜ダンディー 出勤情報ページ
 * URL: /src/app/dandy/schedule/page.tsx
 * Referenced in: /src/components/dandy/LayoutWrapperSub.tsx
 * Created: 2025-09-18
 * Last updated: 2026-07-17
 * ======================================= */
import LayoutWrapperSub from '@/components/dandy/LayoutWrapperSub';
import type { Metadata } from 'next';
import { isRealProduction } from '@/lib/env';
import ShopPageTitle from '@/components/common/ShopPageTitle';
import CastScheduleByDay from '@/components/common/schedule/CastScheduleByDay';

export const metadata: Metadata = {
  title: '横浜ダンディー 出勤情報',
  description: isRealProduction
    ? '横浜ダンディーの出勤情報ページです。店舗在籍キャストの当日出勤情報を掲載しています。'
    : undefined,
};

export default function DandySchedulePage() {
  return (
    <LayoutWrapperSub>
      <ShopPageTitle titleJp="出勤情報" titleEn="schedule" shop="dandy" />
      <CastScheduleByDay />
    </LayoutWrapperSub>
  );
}
