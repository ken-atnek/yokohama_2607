/* =======================================
 * 横浜ダンディーグループ 入口ショップバナー一覧
 * URL: /src/components/entrance/CastList.tsx
 * Referenced in: /src/components/entrance/Entrance.tsx
 * Created: 2026-07-08
 * Last updated: 2026-07-08
 * ======================================= */
'use client';
import styles from '@/components/entrance/Entrance.module.scss';
import Image from 'next/image';
import { useEffect, useState } from 'react';
import GroupLogo from '/public/images/logo/dg.webp';
type ListItem = {
  castName: string;
  areaName: string;
  shopName: string;
  areaImage: string;
  castImage: string;
};

const EntranceCastList = () => {
  const [CastData, setCastData] = useState<ListItem[]>([]);

  useEffect(() => {
    const fetchCastData = async () => {
      try {
        // キャッシュバスティング用のタイムスタンプを追加
        const timestamp =
          process.env.NODE_ENV === 'development' ? Date.now() : '';
        const dataPath = `/db/contents/entranceCastData.json${timestamp ? `?t=${timestamp}` : ''}`;

        const response = await fetch(dataPath);
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data: ListItem[] = await response.json();
        setCastData(data);
      } catch {
        // エラー時の処理（何もしない）
      }
    };

    fetchCastData();
  }, []);

  return (
    <ul className={styles.entranceCastList}>
      {CastData.map((item, index) => (
        <li key={index}>
          <div className={styles.itemCastImage}>
            <Image
              src={item.castImage}
              alt={item.castName}
              width={192}
              height={288}
            />
          </div>
          <div className={styles.itemAreaImage}>
            <Image
              src={item.areaImage}
              alt={item.areaName}
              width={64}
              height={648}
            />
          </div>
          <p className={styles.areaName}>{item.areaName}</p>
          <p className={styles.castName}>{item.castName}</p>
          <p className={styles.shopName}>{item.shopName}</p>
        </li>
      ))}
      <li className={styles.mobileLogo}>
        <Image
          src={GroupLogo}
          width={100}
          height={60}
          alt="HOT POINT GROUP"
          priority
        />
      </li>
    </ul>
  );
};

export default EntranceCastList;
