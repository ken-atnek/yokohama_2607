'use client';
/* =======================================
 *店舗 料金表
 * URL: src/components/Shop/system/BlockPriceList.tsx
 * Referenced in: src/components/Shop/Hot/SystemMain.tsx
 * Created: 2025-08-27
 * Last updated: 2025-09-11
 * ======================================= */
import styles from '@/styles/ShopSystem.module.scss';
import { usePathname } from 'next/navigation';
import clsx from 'clsx';
import { useEffect, useState } from 'react';
import { getShopFromPath, getStoreClass } from '@/lib/shopUtils';

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

const BlockPriceList = ({ jsonPath }: ContentsProps) => {
  const pathname = usePathname();
  const shop = getShopFromPath(pathname);
  const activeStoreClass = getStoreClass(shop);

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
      <p className={styles.notice}>
        ※お盆・年末年始・繁忙期の料金につきましては、変更になる場合がございますので、お店までお問い合わせ下さい。
      </p>
    </div>
  );
};

export default BlockPriceList;
