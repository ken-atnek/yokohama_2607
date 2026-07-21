/* =======================================
 * 横浜ダンディーグループ システム MAIN
 * URL: /src/components/common/system/SystemMain.tsx
 * Referenced in: /src/app/dandy/system/page.tsx
 * Created: 2025-09-17
 * Last updated: 2026-07-21
 * ======================================= */
'use client';

import styles from '@/styles/ShopSystem.module.scss';
import { useEffect } from 'react';
import { usePathname } from 'next/navigation';
import BlockPriceList from '@/components/common/system/BlockPriceList';
import BlockAccess from '@/components/common/system/BlockAccess';
import ShopPageTitle from '@/components/common/ShopPageTitle';
import { getShopFromPath } from '@/lib/shopUtils';
import { trackPageAccess } from '@/lib/accessCounterApi';

const SystemMain = () => {
  const pathname = usePathname();
  const shop = getShopFromPath(pathname);

  useEffect(() => {
    //ページ読み込み時にバックグラウンド処理でユーザーアクセス情報をログに保存する
    const pageId = 'system';
    trackPageAccess(shop, pageId);
    //ページ読み込み時にバックグラウンド処理でユーザーアクセス情報をログに保存する
  }, [shop]);

  return (
    <section className={styles.containerSystemMain}>
      <ShopPageTitle
        titleJp="ご利用料金"
        titleEn="price"
        shop={shop}
        variant="noLogo"
      />
      <BlockPriceList jsonPath={`/db/contents/${shop}/Price.json`} />
      <BlockAccess variant="systemPage" />
    </section>
  );
};

export default SystemMain;
