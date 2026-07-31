/* =======================================
 * クラブダンディー 出勤情報ページ
 * URL: /src/app/club_dandy/schedule/page.tsx
 * Referenced in: /src/components/club_dandy/LayoutWrapperSub.tsx
 * Created: 2026-07-31
 * Last updated: 2026-07-31
 * ======================================= */
import LayoutWrapperSub from '@/components/club_dandy/LayoutWrapperSub';
import type { Metadata } from 'next';
import { isRealProduction } from '@/lib/env';
import ShopPageTitle from '@/components/common/ShopPageTitle';
import CastScheduleByDay from '@/components/common/schedule/CastScheduleByDay';

export const metadata: Metadata = {
  title: 'クラブダンディー 出勤情報',
  description: isRealProduction
    ? 'クラブダンディーの出勤情報ページです。店舗在籍キャストの当日出勤情報を掲載しています。'
    : undefined,
};

export default function ClubDandySchedulePage() {
  return (
    <LayoutWrapperSub>
      <ShopPageTitle titleJp="出勤情報" titleEn="schedule" shop="club_dandy" />
      <CastScheduleByDay />
    </LayoutWrapperSub>
  );
}
