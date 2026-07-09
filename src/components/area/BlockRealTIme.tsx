/* =======================================
 * 横浜ダンディーグループ リアルタイム
 * URL: /src/components/area/BlockRealTIme.tsx
 * Referenced in: /src/app/top/page.tsx
 * Created: 2025-08-23
 * Last updated: 2026-07-09
 * ======================================= */

'use client';
import styles from '@/styles/AreaTop.module.scss';
import { useEffect, useState } from 'react';
import Image from 'next/image';
import Link from 'next/link';
import { gradeMap } from '@/constants/castGradeMap';
import type { CastDetail } from '@/types/CastDetails';

// cast.gradeId から className を取得（例: 'grade6'）

// ショップIDから表示ラベルへのマッピング
const SHOP_LABELS: Record<string, string> = {
  hot: 'KOBE',
  villa: 'VILLA',
  // style: 'STYLE',
};

const BlockRealTIme = () => {
  const [castList, setCastList] = useState<CastDetail[]>([]);

  useEffect(() => {
    const fetchRealTimeData = async () => {
      try {
        // リアルタイムデータは常にキャッシュバスティング
        const timestamp = Date.now();
        const dataPath = `/data/area-top/areaTopRealTime.json?t=${timestamp}`;

        const response = await fetch(dataPath);
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data: CastDetail[] = await response.json();

        const getPriority = (status: number) => {
          switch (status) {
            case 1:
              return 0; // 受付中
            case 2:
              return 1; // 残りわずか
            case 3:
              return 2; // 受付終了間近
            case 4:
              return 3; // 本日終了
            case 5:
              return 4; // 未出勤
            default:
              return 5; // 不明
          }
        };

        const sortedData = data.sort(
          (a: CastDetail, b: CastDetail) =>
            getPriority(a.realTimeStatus) - getPriority(b.realTimeStatus)
        );

        setCastList(sortedData);
      } catch {
        // エラー時の処理（何もしない）
      }
    };

    fetchRealTimeData();
  }, []);
  return (
    <ul className={styles.listRealTime}>
      {castList.map((cast) => {
        const gradeClassName = gradeMap[cast.gradeId]?.className;
        return (
          <li
            key={cast.castId}
            className={`${styles.itemCast} ${styles[cast.shopId]}`}
          >
            <div
              className={`${styles.realTImeDetail} ${
                [3, 4, 5].includes(cast.realTimeStatus) ? styles.statusNa : ''
              }`}
            >
              {cast.realTimeDetail}
            </div>
            <div className={styles.wrapTodayTime}>
              <span>{cast.startTime}</span>
              <span>{cast.endTime}</span>
            </div>
            <Link href={`/${cast.shopId}/profile/?id=${cast.castId}`}>
              <div
                className={`${styles.wrapPhoto} ${gradeClassName ? styles[gradeClassName] : ''}`}
              >
                {cast.gradeId >= 1 && cast.gradeId <= 8 && (
                  <div className={styles.gradeFrame}></div>
                )}
                <span className={styles.gradeLabel}>
                  {gradeMap[cast.gradeId]?.label}
                </span>
                <Image
                  src={
                    cast.castImage && cast.castImage !== ''
                      ? cast.castImage
                      : `/images/cast/${cast.shopId}/no-image.webp`
                  }
                  alt={cast.castName}
                  fill
                />
              </div>
            </Link>
            <div className={styles.castProfile}>
              <div className={styles.wrapName}>
                <p className={styles.castName}>{cast.castName}</p>
                <div className={styles.shopName}>
                  {SHOP_LABELS[cast.shopId] || ''}
                </div>
              </div>
              <div className={styles.castSize}>
                <span className={styles.age}>{cast.age}</span>
                <span className={styles.tall}>{cast.tall}</span>
                <span className={styles.bust}>
                  {cast.bust}
                  <i>{cast.cup}</i>
                </span>
                <span className={styles.west}>{cast.west}</span>
                <span className={styles.hip}>{cast.hip}</span>
              </div>
            </div>
          </li>
        );
      })}
    </ul>
  );
};
export default BlockRealTIme;
