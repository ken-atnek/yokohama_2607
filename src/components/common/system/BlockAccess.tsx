'use client';
/* =======================================
 *店舗システム  アクセスマップ
 * URL: src/components/Shop/system/BlockAccess.tsx
 * Referenced in: src/components/Shop/Hot/SystemMain.tsx
 * Created: 2025-08-27
 * Last updated: 2025-09-17
 * ======================================= */
import styles from './BlockAccess.module.scss';
import { usePathname } from 'next/navigation';
import clsx from 'clsx';
import { Shops } from '@/data/AreaShopData';
import ExternalLink from '@/components/common/ExternalLink';
import { getShopFromPath } from '@/lib/shopUtils';

type ShopDetails = {
  variant?: 'default' | 'systemPage' | 'topPage'; // バリアント追加
};

const BlockAccess = ({ variant = 'default' }: ShopDetails) => {
  const pathname = usePathname();
  const pathShop = getShopFromPath(pathname);
  const shop = pathShop || pathname.split('/')[1] || '';
  const activeStoreClass = shop;
  const shopData = Shops.find((s) => s.storeId === shop);
  const googleMapUrl = shopData
    ? `https://maps.google.com/maps?q=${encodeURIComponent(
        `${shopData.post} ${shopData.address}`
      )}&t=&z=17&ie=UTF8&iwloc=&output=embed`
    : '';

  return (
    <div
      className={clsx(
        styles.blockAccess,
        styles[activeStoreClass],
        styles[variant] // バリアントクラスを適用
      )}
    >
      <h2 className="pageH2">
        <span>access</span>
        アクセス／地図
      </h2>
      {shopData ? (
        <div className={styles.wrapText}>
          <address>
            {shopData.post} {shopData.address}
          </address>
          <ExternalLink href={`tel:${shopData.phone}`}>
            tel.{shopData.phone}
          </ExternalLink>
          <p>
            <span>営業時間</span>
            {shopData.businessHours}
          </p>
        </div>
      ) : (
        <div className={styles.wrapText}>
          <p>店舗情報が見つかりません</p>
        </div>
      )}
      <div className={styles.wrapMap}>
        {googleMapUrl ? (
          <iframe
            src={googleMapUrl}
            width="100%"
            height="450"
            style={{ border: 0 }}
            allowFullScreen
            loading="lazy"
            referrerPolicy="no-referrer-when-downgrade"
          ></iframe>
        ) : (
          <div>
            <p>地図情報が見つかりません</p>
          </div>
        )}
      </div>
    </div>
  );
};
export default BlockAccess;
