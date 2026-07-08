/* =======================================
 * 横浜ダンディーグループ 横浜店舗一覧
 * URL: /src/components/entrance/AreaShopList.tsx
 * Referenced in: /src/components/entrance/Entrance.tsx
 * Created: 2026-07-08
 * Last updated: 2026-07-08
 * ======================================= */

import Image from 'next/image';
import Link from 'next/link';
import styles from '@/components/entrance/Entrance.module.scss';
import { Shops } from '@/data/AreaShopData';

export default function EntranceAreaShopList({
  excludeStoreId,
}: {
  excludeStoreId?: string;
}) {
  return (
    <ul className={styles.listAreaShop}>
      {Shops.filter((shop) => shop.storeId !== excludeStoreId).map((shop) => (
        <li
          key={shop.storeId}
          className={styles[shop.storeId as keyof typeof styles]}
        >
          <Link href={shop.url} aria-label={shop.name} />
          <Image src={shop.logo} alt={shop.name} width={240} height={80} />
          <h4 style={{ color: shop.shopColor }}>{shop.name}</h4>
        </li>
      ))}
    </ul>
  );
}
