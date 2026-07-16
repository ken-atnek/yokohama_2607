/* =======================================
 * 横浜ダンディーグループ PICK UP
 * URL: /src/components/area/BlockPickUp.tsx
 * Referenced in: /src/app/top/page.tsx
 * Created: 2026-07-09
 * Last updated: 2026-07-09
 * ======================================= */

'use client';

import { useEffect, useMemo, useState } from 'react';
import type { CSSProperties } from 'react';
import Image from 'next/image';
import Link from 'next/link';
import { Splide, SplideSlide } from '@splidejs/react-splide';
import '@splidejs/react-splide/css';
import styles from '@/styles/AreaTop.module.scss';
import { Shops } from '@/data/AreaShopData';
import type { CastDetail } from '@/types/CastDetails';

type PickUpCast = Pick<
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
type PickUpGroups = Record<ShopId, PickUpCast[]>;
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

const createEmptyGroups = (): PickUpGroups => ({
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

const BlockPickUp = () => {
  const [pickUpGroups, setPickUpGroups] =
    useState<PickUpGroups>(createEmptyGroups);
  const [activeIndices, setActiveIndices] =
    useState<ActiveIndices>(createInitialIndices);

  useEffect(() => {
    let cancelled = false;

    const fetchPickUpData = async () => {
      try {
        const timestamp =
          process.env.NODE_ENV === 'development' ? Date.now() : '';
        const dataPath = `/db/contents/area/areaTopPickUp.json${
          timestamp ? `?t=${timestamp}` : ''
        }`;

        const response = await fetch(dataPath);
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = (await response.json()) as PickUpCast[];
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

        setPickUpGroups(grouped);
        setActiveIndices({
          club_dandy: getRandomIndex(grouped.club_dandy.length),
          dandy: getRandomIndex(grouped.dandy.length),
          mr_dandy: getRandomIndex(grouped.mr_dandy.length),
        });
      } catch {
        // エラー時の処理（何もしない）
      }
    };

    fetchPickUpData();

    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    const hasAnyRotation = SHOP_ORDER.some(
      (shopId) => pickUpGroups[shopId].length > 1
    );
    if (!hasAnyRotation) {
      return;
    }

    const timeouts: ReturnType<typeof setTimeout>[] = [];
    const intervals: ReturnType<typeof setInterval>[] = [];

    SHOP_ORDER.forEach((shopId, index) => {
      const length = pickUpGroups[shopId].length;
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
  }, [pickUpGroups]);

  const mobileVisibleCasts = useMemo(
    () =>
      SHOP_ORDER.map((shopId) => {
        const casts = pickUpGroups[shopId];
        if (casts.length === 0) return null;
        return casts[activeIndices[shopId]] ?? casts[0];
      }).filter((cast): cast is PickUpCast => cast !== null),
    [activeIndices, pickUpGroups]
  );

  const renderCastCard = (
    cast: PickUpCast,
    options?: {
      isActive?: boolean;
      keySuffix?: string;
    }
  ) => {
    const isActive = options?.isActive ?? true;
    const keySuffix = options?.keySuffix ?? 'default';
    const ageLabel =
      cast.ageText || (cast.age !== null ? String(cast.age) : '');

    return (
      <Link
        key={`${cast.shopId}-${cast.castId}-${keySuffix}`}
        href={`/${cast.shopId}/profile/?id=${cast.castId}`}
        className={`${styles.wrapLink} ${styles.fadeItem} ${isActive ? styles.isActive : ''}`}
        style={
          {
            '--shop-color': shopColorMap[cast.shopId],
            '--zoom-delay': zoomDelayMap[cast.shopId as ShopId],
          } as CSSProperties
        }
        aria-hidden={!isActive}
        tabIndex={isActive ? 0 : -1}
      >
        <div className={styles.shopName}>{cast.shopName}</div>
        <div className={styles.wrapImage}>
          <Image
            src={cast.castImage}
            alt={cast.castName}
            width={416}
            height={612}
            priority
          />
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
    <>
      <ul className={`${styles.blockPickUp} ${styles.pcVersion}`}>
        {SHOP_ORDER.map((shopId) => {
          const casts = pickUpGroups[shopId];
          if (casts.length === 0) {
            return null;
          }

          const activeIndex = activeIndices[shopId] ?? 0;

          return (
            <li key={shopId}>
              <div className={styles.fadeStage}>
                {casts.map((cast, idx) =>
                  renderCastCard(cast, {
                    isActive: idx === activeIndex,
                    keySuffix: `pc-${shopId}-${idx}`,
                  })
                )}
              </div>
            </li>
          );
        })}
      </ul>
      <div className={styles.mobileBlockPickUp}>
        {mobileVisibleCasts.length >= 2 ? (
          <Splide
            options={{
              type: 'slide',
              perPage: 1,
              perMove: 1,
              gap: '5vw',
              arrows: false,
              pagination: true,
              autoplay: false,
              drag: true,
            }}
            className={styles.castSplide}
          >
            {mobileVisibleCasts.map((cast) => (
              <SplideSlide key={`${cast.shopId}-${cast.castId}`}>
                {renderCastCard(cast, {
                  keySuffix: `sp-${cast.shopId}-${cast.castId}`,
                })}
              </SplideSlide>
            ))}
          </Splide>
        ) : mobileVisibleCasts.length === 1 ? (
          <div className={styles.singleCastWrapper}>
            {renderCastCard(mobileVisibleCasts[0], {
              keySuffix: `sp-single-${mobileVisibleCasts[0].shopId}`,
            })}
          </div>
        ) : null}
      </div>
    </>
  );
};

export default BlockPickUp;
