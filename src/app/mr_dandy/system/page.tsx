/* =======================================
 * ミスターダンディー システムページ
 * URL: /src/app/mr_dandy/system/page.tsx
 * Referenced in: /src/components/mr_dandy/LayoutWrapperMain.tsx
 * Created: 2026-07-31
 * Last updated: 2026-08-11
 * ======================================= */
import LayoutWrapperMain from '@/components/mr_dandy/LayoutWrapperMain';
import SystemMain from '@/components/common/system/SystemMain';
import type { Metadata } from 'next';
import { isRealProduction } from '@/lib/env';
import { withCanonical } from '@/lib/seo';
export const metadata: Metadata = withCanonical('/mr_dandy/system/', {
  title: '横浜 風俗(ヘルス) 料金システム｜ミスターダンディー(関内・末吉町)',
  description: isRealProduction
    ? 'ミスターダンディーの料金システムページです。コース料金・指名料・アクセス情報を掲載しています。'
    : undefined,
});
export default function MrDandySystemPage() {
  return (
    <LayoutWrapperMain hideShopLeftOnMobile>
      <SystemMain />
    </LayoutWrapperMain>
  );
}
