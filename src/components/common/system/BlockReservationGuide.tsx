'use client';
/* =======================================
 *店舗システム  ご利用方法
 * URL: src/components/Shop/system/BlockReservationGuide.tsx
 * Referenced in: src/components/Shop/Hot/SystemMain.tsx
 * Created: 2025-08-27
 * Last updated: 2025-09-11
 * ======================================= */
import styles from '@/styles/ShopSystem.module.scss';
import { usePathname } from 'next/navigation';
import clsx from 'clsx';
import { Shops } from '@/data/AreaShopData';
import ExternalLink from '@/components/common/ExternalLink';
import ImageWebReserve from '@/assets/images/objects/web-reserve.webp';
import { getShopFromPath, getStoreClass } from '@/lib/shopUtils';

import Image from 'next/image';
type ShopDetails = {
  logoUrl?: string;
  reserveUrl?: string;
};

const BlockReservationGuide = ({ logoUrl, reserveUrl }: ShopDetails) => {
  const pathname = usePathname();
  const shop = getShopFromPath(pathname);
  const activeStoreClass = getStoreClass(shop);

  const shopData = Shops.find((s) => s.storeId === activeStoreClass);
  return (
    <div
      className={clsx(styles.blockReservationGuide, styles[activeStoreClass])}
    >
      <h2 className="pageH2">
        <span>Reservation Guide</span>
        ご予約方法
      </h2>
      {shopData && (
        <ul>
          <li>
            <div className={styles.wrapTitle}>
              <h3 className={styles.tel}>お電話でのご予約</h3>
            </div>
            <div className={styles.wrapContentsHead}>
              <p>
                下記お電話番号まで
                <br />
                ご連絡ください。
              </p>
              <div className={styles.itemLogo}>
                <svg
                  className={styles.logoSvg}
                  width="200"
                  height="50"
                  aria-hidden="true"
                >
                  <use href={logoUrl} />
                </svg>
              </div>
              <ExternalLink
                href={`tel:${shopData.phone}`}
                className={styles.itemTel}
              >
                {shopData.phone}
              </ExternalLink>
            </div>
            <div className={styles.wrapContentsBottom}>
              <h4>ご予約方法</h4>
              <p className={styles.innerTel}>
                お名前・ご利用コース等、必要事項をスタッフにお伝えください。
              </p>
              <p className={styles.innerTel}>
                ご予約は<i>前日（16：00から）と当日</i>から可能です。
              </p>
            </div>
          </li>
          <li>
            <div className={styles.wrapTitle}>
              <h3 className={styles.web}>ネットでのご予約</h3>
            </div>
            <div className={styles.wrapContentsHead}>
              <h5>
                シティヘブンでの
                <br />
                ご予約が便利です！
              </h5>
              <Image src={ImageWebReserve} alt="WEB予約" />
            </div>
            <div className={styles.wrapContentsBottom}>
              <h4>ご予約方法</h4>
              <p className={styles.innerWeb}>
                <ExternalLink href={reserveUrl}>
                  <span>ヘブンネット {shopData.name}</span>
                </ExternalLink>
                のサイト上よりご予約が可能です。
              </p>
              <small>
                会員様の<i>電話予約が優先</i>されますのでご了承ください。
              </small>
              <p className={styles.innerWeb}>
                お店からの注意事項をよくお読みの上、ご予約ください。操作方法については
                <i>
                  <ExternalLink href="https://www.cityheaven.net/reserve-countdown/">
                    ヘブンネットをご参考
                  </ExternalLink>
                </i>
                ください。
              </p>
              <p className={styles.innerWeb}>
                ※<i>インターネットでのご予約は仮予約</i>
                になります。ご予約の確定、キャンセルのご連絡は、お客様の電話番号にショートメールにて通知いたしますので、ご確認ください。
              </p>
            </div>
          </li>
        </ul>
      )}
    </div>
  );
};
export default BlockReservationGuide;
