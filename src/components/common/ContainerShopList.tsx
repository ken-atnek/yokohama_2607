/* =======================================
 * 横浜ダンディーグループ 店舗一覧リンク
 * URL: /src/components/common/ContainerShopList.tsx
 * Referenced in: /src/app/top/page.tsx
 * Created: 2025-08-19
 * Last updated: 2026-07-16
 * ======================================= */
import styles from './ContainerShopList.module.scss';
import { Shops } from '@/data/AreaShopData';
import Image from 'next/image';
import Link from 'next/link';
import ExternalLink from '@/components/common/ExternalLink';
import clsx from 'clsx';
import ScrollLink from '@/components/common/ScrollLink';

type ContainerShopListProps = {
  activeStoreClass?: string;
};

const ContainerShopList = ({ activeStoreClass }: ContainerShopListProps) => {
  return (
    <section
      className={clsx(
        styles.containerShopList,
        activeStoreClass && styles[activeStoreClass]
      )}
    >
      <h2>dandy group shop link</h2>
      <ScrollLink href="/" className={styles.pageTop}>
        <span>page top</span>
      </ScrollLink>
      <ul>
        {Shops.map(
          (
            shopItem // shop → shopItem に変数名変更（重複回避）
          ) => (
            <li key={shopItem.storeId}>
              <Link
                href={shopItem.url}
                className={clsx(styles.itemLogo, styles[shopItem.storeId])}
                style={{ borderColor: shopItem.shopColor }}
              >
                <Image
                  src={shopItem.logo}
                  alt={shopItem.name}
                  width={200}
                  height={50}
                />
              </Link>
              <p>{shopItem.name}</p>
              <ExternalLink
                href={`tel:${shopItem.phone}`}
                className={styles.itemTel}
              >
                {shopItem.phone}
              </ExternalLink>
            </li>
          )
        )}
      </ul>
    </section>
  );
};

export default ContainerShopList;
