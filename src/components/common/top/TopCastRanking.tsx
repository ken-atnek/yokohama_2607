/* =======================================
 * 横浜ダンディーグループ 店舗 TOP キャストランキング
 * URL: /src/components/common/top/TopCastRanking.tsx
 * Referenced in: /src/components/common/top/ShopTopMain.tsx
 * Created: 2025-08-23
 * Last updated: 2026-07-14
 * ======================================= */

'use client';

import styles from './TopCastRanking.module.scss';
import { usePathname } from 'next/navigation';
import clsx from 'clsx';
import BaseCastRanking from '@/components/common/CastRanking';
import { Shops } from '@/data/AreaShopData';
import { getShopFromPath, getStoreClass } from '@/lib/shopUtils';

type ContentsProps = {
  titleJp?: string;
  titleEn?: string;
  titleEnSub?: string;
  jsonPath: string;
};

const rankIconTypeMap: Record<string, 'dandy' | 'mr' | 'club'> = {
  dandy: 'dandy',
  mr_dandy: 'mr',
  club_dandy: 'club',
};

const TopCastRanking = ({
  titleJp,
  titleEn,
  titleEnSub,
  jsonPath,
}: ContentsProps) => {
  const pathname = usePathname();
  const shop = getShopFromPath(pathname);
  const activeStoreClass = getStoreClass(shop);
  const shopData = Shops.find((item) => item.storeId === shop);
  const storeName = shopData?.nameEn ?? '';
  const rankIconType = rankIconTypeMap[shop] ?? 'dandy';

  return (
    <article className={clsx(styles.boxCastRanking, styles[activeStoreClass])}>
      <div className={styles.wrapTitle}>
        <h2>
          <span>
            {titleEn}
            <i>{titleEnSub}</i>
          </span>
          {titleJp}
        </h2>
      </div>
      <div className={styles.titleObject}>
        <div className={styles.wrapText}>
          <div className={styles.shopName}>{storeName}</div>
          <div className={styles.title}>
            {titleEn}
            <i>{titleEnSub}</i>
          </div>
        </div>
      </div>
      <BaseCastRanking
        jsonPath={jsonPath}
        rankIconType={rankIconType}
        className={activeStoreClass}
      />
    </article>
  );
};

export default TopCastRanking;
