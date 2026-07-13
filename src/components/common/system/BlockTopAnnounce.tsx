'use client';
/* =======================================
 *店舗システム  平日・土日祝 料金変わらず営業中
 * URL: src/components/Shop/system/BlockTopAnnounce.tsx
 * Referenced in: src/components/Shop/Hot/SystemMain.tsx
 * Created: 2025-08-27
 * Last updated: 2025-09-11
 * ======================================= */
import styles from '@/styles/ShopSystem.module.scss';
import { usePathname } from 'next/navigation';
import clsx from 'clsx';
import { getShopFromPath, getStoreClass } from '@/lib/shopUtils';

const BlockTopAnnounce = () => {
  const pathname = usePathname();
  const shop = getShopFromPath(pathname);
  const activeStoreClass = getStoreClass(shop);

  return (
    <div className={clsx(styles.blockTopAnnounce, styles[activeStoreClass])}>
      <h3>平日・土日祝 料金変わらず営業中</h3>
      <p>
        ※最終受付時間は<time dateTime="23:20">23:20</time>
        となっております。
      </p>
    </div>
  );
};
export default BlockTopAnnounce;
