/* =======================================
 * ミスターダンディー 出勤情報ページ
 * URL: /src/app/mr_dandy/schedule/page.tsx
 * Referenced in: /src/components/mr_dandy/LayoutWrapperSub.tsx
 * Created: 2026-07-31
 * Last updated: 2026-07-31
 * ======================================= */
import LayoutWrapperSub from '@/components/mr_dandy/LayoutWrapperSub';
import type { Metadata } from 'next';
import { isRealProduction } from '@/lib/env';
import ShopPageTitle from '@/components/common/ShopPageTitle';
import CastScheduleByDay from '@/components/common/schedule/CastScheduleByDay';
import { withCanonical } from '@/lib/seo';

export const metadata: Metadata = withCanonical('/mr_dandy/schedule/', {
  title: 'ミスターダンディー 出勤情報',
  description: isRealProduction
    ? 'ミスターダンディーの出勤情報ページです。店舗在籍キャストの当日出勤情報を掲載しています。'
    : undefined,
});

export default function MrDandySchedulePage() {
  return (
    <LayoutWrapperSub>
      <ShopPageTitle titleJp="出勤情報" titleEn="schedule" shop="mr_dandy" />
      <CastScheduleByDay />
    </LayoutWrapperSub>
  );
}
