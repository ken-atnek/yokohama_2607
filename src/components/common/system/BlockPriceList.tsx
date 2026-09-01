'use client';
/* =======================================
 *店舗 料金表
 * URL: src/components/common/system/BlockPriceList.tsx
 * Referenced in: src/components/common/system/SystemMain.tsx
 * Created: 2025-08-27
 * Last updated: 2026-07-21
 * ======================================= */
import styles from '@/styles/ShopSystem.module.scss';
import { usePathname } from 'next/navigation';
import Image from 'next/image';
import clsx from 'clsx';
import { useEffect, useState } from 'react';
import { getShopFromPath, getStoreClass } from '@/lib/shopUtils';
import { Shops } from '@/data/AreaShopData';
import ExternalLink from '@/components/common/ExternalLink';

type ContentsProps = {
  jsonPath: string;
};

// jsonファイル型宣言
type PricePlan = {
  title: string;
  title_display?: boolean;
  time_table: {
    label: { [key: string]: string }[];
    time: { [key: string]: string }[];
    rates: {
      course: string;
      [key: string]: string | number;
    }[];
  };
};

const creditCards = [
  {
    src: '/images/common/credit-card/master.webp',
    alt: 'Mastercard',
    width: 313,
    height: 188,
  },
  {
    src: '/images/common/credit-card/visa.webp',
    alt: 'VISA',
    width: 338,
    height: 104,
  },
  // {
  //   src: '/images/common/credit-card/amex.webp',
  //   alt: 'American Express',
  //   width: 213,
  //   height: 74,
  // },
  // {
  //   src: '/images/common/credit-card/jcb.webp',
  //   alt: 'JCB',
  //   width: 244,
  //   height: 183,
  // },
];

const BlockPriceList = ({ jsonPath }: ContentsProps) => {
  const pathname = usePathname();
  const shop = getShopFromPath(pathname);
  const activeStoreClass = getStoreClass(shop);
  const shopData = Shops.find((s) => s.storeId === shop);

  const [pricePlans, setPricePlans] = useState<PricePlan[]>([]);

  useEffect(() => {
    const fetchPricePlans = async () => {
      try {
        // キャッシュバスティング用のタイムスタンプを追加
        const timestamp =
          process.env.NODE_ENV === 'development' ? Date.now() : '';
        const dataPath = `${jsonPath}${timestamp ? `?t=${timestamp}` : ''}`;

        const response = await fetch(dataPath);
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data: PricePlan[] = await response.json();
        setPricePlans(data);
      } catch {
        // エラー時の処理（何もしない）
      }
    };

    fetchPricePlans();
  }, [jsonPath]);

  return (
    <div className={clsx(styles.blockPriceList, styles[activeStoreClass])}>
      {pricePlans.map((plan, i) => (
        <div key={i} className={styles.wrapPricePlan}>
          {plan.title_display !== false && (
            <h3 className={styles.planTitle}>{plan.title}</h3>
          )}

          <ul
            className={
              styles[`rates${Object.keys(plan.time_table.rates[0]).length}`]
            }
          >
            <li className={styles.header}>
              {Object.entries(plan.time_table.label[0]).map(([key, label]) => (
                <div key={key}>
                  {label && <h4>{label}</h4>}
                  <p>{plan.time_table.time[0][`time${key.slice(-2)}`]}</p>
                </div>
              ))}
            </li>
            {plan.time_table.rates.map((rate, idx) => (
              <li key={idx} className={styles.row}>
                <div className={styles.min}>
                  <span>{rate.course}</span>
                </div>
                {Object.keys(plan.time_table.label[0]).map((key) => (
                  <div key={key} className={styles.price}>
                    <span>
                      {Number(rate[`price${key.slice(-2)}`]).toLocaleString()}
                    </span>
                  </div>
                ))}
              </li>
            ))}
          </ul>
        </div>
      ))}
      <div className={styles.warpCreditCard}>
        <ul>
          {creditCards.map((card) => (
            <li key={card.src}>
              <Image
                src={card.src}
                alt={card.alt}
                width={card.width}
                height={card.height}
              />
            </li>
          ))}
        </ul>
        <p>各種カードをご利用できます。</p>
      </div>
      {shopData ? (
        <div className={styles.wrapNotice}>
          <h3>お電話予約につきまして</h3>
          <p>お遊びの女の子が決まりましたらお電話予約をオススメ致します。</p>
          <p className={styles.itemReception}>3日前の6:00より受付可能</p>
          <ExternalLink
            href={`tel:${shopData.phone}`}
            className={styles.itemReserveTel}
          >
            <span>ご予約受付ダイヤル</span>
            <strong>{shopData.phone}</strong>
          </ExternalLink>
          <p>
            上記のお電話番号にて受付致します。
            <br />
            女の子の出勤は出勤表にてご確認下さい。
          </p>
          <p className={styles.itemBusinessHours}>
            <span>営業時間</span>
            {shopData.businessHours}
          </p>
        </div>
      ) : null}
    </div>
  );
};

export default BlockPriceList;
