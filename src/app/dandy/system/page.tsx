/* =======================================
 * 横浜ダンディー システムページ
 * URL: /src/app/dandy/system/page.tsx
 * Referenced in: /src/components/dandy/LayoutWrapperMain.tsx
 * Created: 2025-09-17
 * Last updated: 2026-08-01
 * ======================================= */
import LayoutWrapperMain from '@/components/dandy/LayoutWrapperMain';
import SystemMain from '@/components/common/system/SystemMain';
import type { Metadata } from 'next';
import { isRealProduction } from '@/lib/env';
import { withCanonical } from '@/lib/seo';
export const metadata: Metadata = withCanonical('/dandy/system/', {
  title: '横浜ダンディー 料金システム',
  description: isRealProduction
    ? '横浜ダンディーの料金システムページです。コース料金・指名料・アクセス情報を掲載しています。'
    : undefined,
});
export default function DandySystemPage() {
  return (
    <LayoutWrapperMain hideShopLeftOnMobile>
      <SystemMain />
    </LayoutWrapperMain>
  );
}
