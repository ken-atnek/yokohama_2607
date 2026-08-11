/* =======================================
 * ミスターダンディー 店舗TOPページ
 * URL: /src/app/mr_dandy/top/page.tsx
 * Referenced in: /src/app/mr_dandy/page.tsx
 * Created: 2026-07-31
 * Last updated: 2026-08-11
 * ======================================= */

import type { Metadata } from 'next';
import ShopTopMain from '@/components/common/top/ShopTopMain';
import LayoutWrapperMain from '@/components/mr_dandy/LayoutWrapperMain';
import { withCanonical } from '@/lib/seo';

export const metadata: Metadata = withCanonical('/mr_dandy/top/', {
  title: '横浜 風俗(ヘルス)｜ミスターダンディー(関内・末吉町)',
  description:
    'ミスターダンディーの店舗TOPページです。店舗コンセプト、お知らせ、アクセス情報を掲載しています。',
});

export default function MrDandyTopPage() {
  return (
    <LayoutWrapperMain>
      <ShopTopMain />
    </LayoutWrapperMain>
  );
}
