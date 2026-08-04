/* =======================================
 * 横浜ダンディー 週間出勤表ページ
 * URL: /src/app/dandy/weekly-schedule/page.tsx
 * Referenced in: /src/components/dandy/LayoutWrapperSub.tsx
 * Created: 2025-09-18
 * Last updated: 2026-07-18
 * ======================================= */
import LayoutWrapperSub from '@/components/dandy/LayoutWrapperSub';
import type { Metadata } from 'next';
import { isRealProduction } from '@/lib/env';
import ShopPageTitle from '@/components/common/ShopPageTitle';
import CastScheduleByPeriod from '@/components/common/schedule/CastScheduleByPeriod';
import { withCanonical } from '@/lib/seo';

export const metadata: Metadata = withCanonical('/dandy/weekly-schedule/', {
  title: '横浜ダンディー 週間出勤表',
  description: isRealProduction
    ? '横浜ダンディーの週間出勤表ページです。在籍キャストの出勤予定を一覧で掲載しています。'
    : undefined,
});

export default function DandyWeeklySchedulePage() {
  return (
    <LayoutWrapperSub>
      <ShopPageTitle
        titleJp="週間出勤表"
        titleEn="weekly schedule"
        shop="dandy"
      />
      <CastScheduleByPeriod />
    </LayoutWrapperSub>
  );
}
