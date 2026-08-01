/* =======================================
 * ミスターダンディー システムページ
 * URL: /src/app/mr_dandy/system/page.tsx
 * Referenced in: /src/components/mr_dandy/LayoutWrapperMain.tsx
 * Created: 2026-07-31
 * Last updated: 2026-08-01
 * ======================================= */
import LayoutWrapperMain from '@/components/mr_dandy/LayoutWrapperMain';
import SystemMain from '@/components/common/system/SystemMain';
import type { Metadata } from 'next';
import { isRealProduction } from '@/lib/env';
export const metadata: Metadata = {
  title: 'ミスターダンディー 料金システム',
  description: isRealProduction
    ? 'ミスターダンディーの料金システムページです。コース料金・指名料・アクセス情報を掲載しています。'
    : undefined,
};
export default function MrDandySystemPage() {
  return (
    <LayoutWrapperMain hideShopLeftOnMobile>
      <SystemMain />
    </LayoutWrapperMain>
  );
}
