/* =======================================
 * ミスターダンディー 週間出勤表ページ
 * URL: /src/app/mr_dandy/weekly-schedule/page.tsx
 * Referenced in: /src/components/mr_dandy/LayoutWrapperSub.tsx
 * Created: 2026-07-31
 * Last updated: 2026-08-11
 * ======================================= */
import LayoutWrapperSub from '@/components/mr_dandy/LayoutWrapperSub';
import type { Metadata } from 'next';
import { isRealProduction } from '@/lib/env';
import ShopPageTitle from '@/components/common/ShopPageTitle';
import CastScheduleByPeriod from '@/components/common/schedule/CastScheduleByPeriod';
import { withCanonical } from '@/lib/seo';

export const metadata: Metadata = withCanonical('/mr_dandy/weekly-schedule/', {
  title: '横浜 風俗(ヘルス) 週間出勤表｜ミスターダンディー(関内・末吉町)',
  description: isRealProduction
    ? 'ミスターダンディーの週間出勤表ページです。在籍キャストの出勤予定を一覧で掲載しています。'
    : undefined,
});

export default function MrDandyWeeklySchedulePage() {
  return (
    <LayoutWrapperSub>
      <ShopPageTitle
        titleJp="週間出勤表"
        titleEn="weekly schedule"
        shop="mr_dandy"
      />
      <CastScheduleByPeriod />
    </LayoutWrapperSub>
  );
}
