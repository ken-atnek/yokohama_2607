/* =======================================
 * 横浜ダンディーグループ NEW FACE
 * URL: /src/components/area/BlockNewFace.tsx
 * Referenced in: /src/app/top/page.tsx
 * Created: 2026-07-09
 * Last updated: 2026-07-09
 * ======================================= */

'use client';

import { useEffect, useState } from 'react';
import Image from 'next/image';
import Link from 'next/link';
import styles from '@/styles/AreaTop.module.scss';
import type { CastDetail } from '@/types/CastDetails';

const BlockNewFace = () => {
  // villa 全件
  const [villaCasts, setVillaCasts] = useState<CastDetail[]>([]);

  // PC 左右インデックス
  const [leftIndex, setLeftIndex] = useState(0);
  const [rightIndex, setRightIndex] = useState(0);

  // データ取得（villaのみ）
  useEffect(() => {
    let cancelled = false;

    const fetchNewFaceData = async () => {
      try {
        const timestamp =
          process.env.NODE_ENV === 'development' ? Date.now() : '';
        const dataPath = `/data/area-top/areaTopNewFace.json${
          timestamp ? `?t=${timestamp}` : ''
        }`;

        const response = await fetch(dataPath);
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data: CastDetail[] = await response.json();
        if (cancelled) return;

        const villaAll = data.filter((item) => item.shopId === 'villa');
        setVillaCasts(villaAll);

        if (villaAll.length > 0) {
          setLeftIndex(0);
          setRightIndex(villaAll.length - 1);
        } else {
          setLeftIndex(0);
          setRightIndex(0);
        }
      } catch {
        // エラー時は何もしない
      }
    };

    fetchNewFaceData();

    return () => {
      cancelled = true;
    };
  }, []);

  // PC 切替インターバル（6秒）
  useEffect(() => {
    const len = villaCasts.length;
    if (len === 0) return;

    const interval = setInterval(() => {
      setLeftIndex((prev) => (prev + 1) % len);
      setRightIndex((prev) => (prev - 1 + len) % len);
    }, 6000);

    return () => {
      clearInterval(interval);
    };
  }, [villaCasts]);

  // PC用カード（フェード対応）
  const renderCard = (
    cast: CastDetail,
    isActive: boolean,
    keySuffix: string
  ) => (
    <Link
      key={`${cast.castId}-${keySuffix}`}
      href={`/villa/profile/?id=${cast.castId}`}
      className={`${styles.wrapLink} ${styles.fadeItem} ${
        isActive ? styles.isActive : ''
      }`}
      aria-hidden={!isActive}
      tabIndex={isActive ? 0 : -1}
    >
      <div className={styles.wrapImage}>
        <Image
          src={
            cast.castImage && cast.castImage !== ''
              ? cast.castImage
              : `/images/cast/villa/no-image.webp`
          }
          alt={cast.castName}
          width={120}
          height={160}
        />
      </div>
      <div className={styles.wrapProfile}>
        <div className={styles.castName}>{cast.castName}</div>
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
    </Link>
  );

  return (
    <ul className={styles.blockNewFace}>
      {/* 左枠（先頭から進む） */}
      <li className={styles.villa}>
        <div className={styles.fadeStage}>
          {villaCasts.map((cast, idx) =>
            renderCard(cast, idx === leftIndex, 'L')
          )}
        </div>
      </li>

      {/* 右枠（最後から戻る） */}
      <li className={styles.villa}>
        <div className={styles.fadeStage}>
          {villaCasts.map((cast, idx) =>
            renderCard(cast, idx === rightIndex, 'R')
          )}
        </div>
      </li>
    </ul>
  );
};

export default BlockNewFace;
