/* =======================================
 * クラブダンディー 週間出勤表ページ
 * URL: /src/app/club_dandy/weekly-schedule/page.tsx
 * Referenced in: /src/components/club_dandy/LayoutWrapperSub.tsx
 * Created: 2026-07-31
 * Last updated: 2026-07-31
 * ======================================= */
import LayoutWrapperSub from '@/components/club_dandy/LayoutWrapperSub';
import type { Metadata } from 'next';
import { isRealProduction } from '@/lib/env';
import ShopPageTitle from '@/components/common/ShopPageTitle';
import CastScheduleByPeriod from '@/components/common/schedule/CastScheduleByPeriod';
import { withCanonical } from '@/lib/seo';

export const metadata: Metadata = withCanonical('/club_dandy/weekly-schedule/', {
  title: 'クラブダンディー 週間出勤表',
  description: isRealProduction
    ? 'クラブダンディーの週間出勤表ページです。在籍キャストの出勤予定を一覧で掲載しています。'
    : undefined,
});

export default function ClubDandyWeeklySchedulePage() {
  return (
    <LayoutWrapperSub>
      <ShopPageTitle
        titleJp="週間出勤表"
        titleEn="weekly schedule"
        shop="club_dandy"
      />
      <CastScheduleByPeriod />
    </LayoutWrapperSub>
  );
}
