/* =======================================
 * 横浜ダンディー 店舗TOPページ
 * URL: /src/app/dandy/top/page.tsx
 * Referenced in: /src/app/dandy/page.tsx
 * Created: 2026-07-13
 * Last updated: 2026-07-13
 * ======================================= */

import type { Metadata } from 'next';
import ShopTopMain from '@/components/common/top/ShopTopMain';
import LayoutWrapperMain from '@/components/dandy/LayoutWrapperMain';

export const metadata: Metadata = {
  title: '横浜ダンディー TOP',
  description:
    '横浜ダンディーの店舗TOPページです。店舗コンセプト、お知らせ、アクセス情報を掲載しています。',
};

export default function DandyTopPage() {
  return (
    <LayoutWrapperMain>
      <ShopTopMain />
    </LayoutWrapperMain>
  );
}
