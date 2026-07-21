'use client';
/* =======================================
 *店舗システム  アクセスマップ
 * URL: src/components/common/system/BlockAccess.tsx
 * Referenced in: src/components/common/system/SystemMain.tsx
 * Created: 2025-08-27
 * Last updated: 2026-07-21
 * ======================================= */
import styles from './BlockAccess.module.scss';
import { usePathname } from 'next/navigation';
import Image from 'next/image';
import clsx from 'clsx';
import { Shops } from '@/data/AreaShopData';
import ExternalLink from '@/components/common/ExternalLink';
import { getShopFromPath, getStoreClass } from '@/lib/shopUtils';

type ShopDetails = {
  variant?: 'default' | 'systemPage' | 'topPage'; // バリアント追加
};

const mapImageSrcMap: Record<string, string> = {
  dandy: '/images/dandy/map.webp',
};

const BlockAccess = ({ variant = 'default' }: ShopDetails) => {
  const pathname = usePathname();
  const shop = getShopFromPath(pathname);
  const activeStoreClass = getStoreClass(shop);
  const shopData = Shops.find((s) => s.storeId === shop);
  const mapImageSrc = mapImageSrcMap[shop];
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
      <h2 className={styles.itemH2}>access map</h2>
      {mapImageSrc && shopData ? (
        <div className={styles.boxMapInfo}>
          <Image
            src={mapImageSrc}
            alt={`${shopData.name} アクセスマップ`}
            width={640}
            height={640}
          />
        </div>
      ) : null}
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
