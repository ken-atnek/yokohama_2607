/* =======================================
 * 横浜ダンディーグループ NEW FACE
 * URL: /src/components/area/BlockNewFace.tsx
 * Referenced in: /src/app/top/page.tsx
 * Created: 2026-07-09
 * Last updated: 2026-07-09
 * ======================================= */

'use client';

import { useEffect, useState } from 'react';
import type { CSSProperties } from 'react';
import Image from 'next/image';
import Link from 'next/link';
import styles from '@/styles/AreaTop.module.scss';
import { Shops } from '@/data/AreaShopData';
import type { CastDetail } from '@/types/CastDetails';

type NewFaceCast = Pick<
  CastDetail,
  | 'shopId'
  | 'castId'
  | 'castName'
  | 'castImage'
  | 'age'
  | 'tall'
  | 'bust'
  | 'cup'
  | 'west'
  | 'hip'
  | 'shopName'
>;

type ShopId = 'club_dandy' | 'dandy' | 'mr_dandy';
type NewFaceGroups = Record<ShopId, NewFaceCast[]>;
type ActiveIndices = Record<ShopId, number>;

const SHOP_ORDER: ShopId[] = ['club_dandy', 'dandy', 'mr_dandy'];
const FADE_INTERVAL_MS = 6000;
const FADE_STAGGER_MS = 1200;
const zoomDelayMap: Record<ShopId, string> = {
  club_dandy: '0ms',
  dandy: `${FADE_STAGGER_MS}ms`,
  mr_dandy: `${FADE_STAGGER_MS * 2}ms`,
};
const shopNameMap = Object.fromEntries(
  Shops.map((shop) => [shop.storeId, shop.name])
) as Record<string, string>;
const shopColorMap = Object.fromEntries(
  Shops.map((shop) => [shop.storeId, shop.shopColor ?? 'transparent'])
) as Record<string, string>;

const createEmptyGroups = (): NewFaceGroups => ({
  club_dandy: [],
  dandy: [],
  mr_dandy: [],
});

const createInitialIndices = (): ActiveIndices => ({
  club_dandy: 0,
  dandy: 0,
  mr_dandy: 0,
});

const getRandomIndex = (length: number) => {
  if (length <= 1) return 0;
  return Math.floor(Math.random() * length);
};

const BlockNewFace = () => {
  const [newFaceGroups, setNewFaceGroups] = useState<NewFaceGroups>(
    createEmptyGroups
  );
  const [activeIndices, setActiveIndices] = useState<ActiveIndices>(
    createInitialIndices
  );

  useEffect(() => {
    let cancelled = false;

    const fetchNewFaceData = async () => {
      try {
        const timestamp =
          process.env.NODE_ENV === 'development' ? Date.now() : '';
        const dataPath = `/db/contents/area/areaTopNewFace.json${
          timestamp ? `?t=${timestamp}` : ''
        }`;

        const response = await fetch(dataPath);
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = (await response.json()) as NewFaceCast[];
        if (cancelled) return;

        const grouped = createEmptyGroups();
        data.forEach((item) => {
          if (!SHOP_ORDER.includes(item.shopId as ShopId) || !item.castImage) {
            return;
          }

          const shopId = item.shopId as ShopId;
          grouped[shopId].push({
            ...item,
            shopName: item.shopName || shopNameMap[shopId] || '',
          });
        });

        setNewFaceGroups(grouped);
        setActiveIndices({
          club_dandy: getRandomIndex(grouped.club_dandy.length),
          dandy: getRandomIndex(grouped.dandy.length),
          mr_dandy: getRandomIndex(grouped.mr_dandy.length),
        });
      } catch {
        // エラー時は何もしない
      }
    };

    fetchNewFaceData();

    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    const hasAnyRotation = SHOP_ORDER.some(
      (shopId) => newFaceGroups[shopId].length > 1
    );
    if (!hasAnyRotation) {
      return;
    }

    const timeouts: ReturnType<typeof setTimeout>[] = [];
    const intervals: ReturnType<typeof setInterval>[] = [];

    SHOP_ORDER.forEach((shopId, index) => {
      const length = newFaceGroups[shopId].length;
      if (length <= 1) {
        return;
      }

      const tick = () => {
        setActiveIndices((prev) => ({
          ...prev,
          [shopId]: (prev[shopId] + 1) % length,
        }));
      };

      const delay = index * FADE_STAGGER_MS;
      const timeout = setTimeout(() => {
        tick();
        const interval = setInterval(tick, FADE_INTERVAL_MS);
        intervals.push(interval);
      }, delay);

      timeouts.push(timeout);
    });

    return () => {
      timeouts.forEach((timeout) => clearTimeout(timeout));
      intervals.forEach((interval) => clearInterval(interval));
    };
  }, [newFaceGroups]);

  const renderCard = (
    cast: NewFaceCast,
    isActive: boolean,
    keySuffix: string
  ) => {
    const ageLabel =
      cast.ageText || (cast.age !== null ? String(cast.age) : '');

    return (
      <Link
      key={`${cast.shopId}-${cast.castId}-${keySuffix}`}
      href={`/${cast.shopId}/profile/?id=${cast.castId}`}
      className={`${styles.wrapLink} ${styles.fadeItem} ${
        isActive ? styles.isActive : ''
      }`}
      style={
        {
          '--shop-color': shopColorMap[cast.shopId],
          '--zoom-delay': zoomDelayMap[cast.shopId as ShopId],
        } as CSSProperties
      }
      aria-hidden={!isActive}
      tabIndex={isActive ? 0 : -1}
    >
      <div className={styles.wrapImage}>
        <Image src={cast.castImage} alt={cast.castName} width={416} height={612} />
      </div>
      <div className={styles.wrapProfile}>
        <div className={styles.castName}>{cast.castName}</div>
        <div className={styles.castSize}>
          <span className={styles.age}>{ageLabel}</span>
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
  };

  return (
    <ul className={styles.blockNewFace}>
      {SHOP_ORDER.map((shopId) => {
        const casts = newFaceGroups[shopId];
        if (casts.length === 0) {
          return null;
        }

        const activeIndex = activeIndices[shopId] ?? 0;

        return (
          <li key={shopId}>
            <div className={styles.fadeStage}>
              {casts.map((cast, idx) =>
                renderCard(cast, idx === activeIndex, `${shopId}-${idx}`)
              )}
            </div>
          </li>
        );
      })}
    </ul>
  );
};

export default BlockNewFace;
