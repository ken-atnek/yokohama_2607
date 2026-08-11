/* =======================================
 * クラブダンディー システムページ
 * URL: /src/app/club_dandy/system/page.tsx
 * Referenced in: /src/components/club_dandy/LayoutWrapperMain.tsx
 * Created: 2026-07-31
 * Last updated: 2026-08-11
 * ======================================= */
import LayoutWrapperMain from '@/components/club_dandy/LayoutWrapperMain';
import SystemMain from '@/components/common/system/SystemMain';
import type { Metadata } from 'next';
import { isRealProduction } from '@/lib/env';
import { withCanonical } from '@/lib/seo';
export const metadata: Metadata = withCanonical('/club_dandy/system/', {
  title: '横浜 風俗(ヘルス) 料金システム｜クラブダンディー(関内・曙町)',
  description: isRealProduction
    ? 'クラブダンディーの料金システムページです。コース料金・指名料・アクセス情報を掲載しています。'
    : undefined,
});
export default function ClubDandySystemPage() {
  return (
    <LayoutWrapperMain hideShopLeftOnMobile>
      <SystemMain />
    </LayoutWrapperMain>
  );
}
