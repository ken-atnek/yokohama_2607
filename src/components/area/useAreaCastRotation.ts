/* =======================================
 * 横浜ダンディーグループ エリアTOP キャストローテーション共通ロジック
 * URL: /src/components/area/useAreaCastRotation.ts
 * Referenced in: /src/components/area/BlockNewFace.tsx, /src/components/area/BlockPickUp.tsx
 * Created: 2026-07-20
 * Last updated: 2026-07-20
 * ======================================= */

import { useEffect, useState } from 'react';
import { Shops } from '@/data/AreaShopData';
import type { CastDetail } from '@/types/CastDetails';

export type AreaCastItem = Pick<
  CastDetail,
  | 'shopId'
  | 'castId'
  | 'castName'
  | 'castImage'
  | 'age'
  | 'ageText'
  | 'tall'
  | 'bust'
  | 'cup'
  | 'waist'
  | 'hip'
  | 'shopName'
>;

export type ShopId = 'club_dandy' | 'dandy' | 'mr_dandy';
type AreaCastGroups = Record<ShopId, AreaCastItem[]>;
type ActiveIndices = Record<ShopId, number>;

export const SHOP_ORDER: ShopId[] = ['club_dandy', 'dandy', 'mr_dandy'];
const FADE_INTERVAL_MS = 6000;
const FADE_STAGGER_MS = 1200;

export const zoomDelayMap: Record<ShopId, string> = {
  club_dandy: '0ms',
  dandy: `${FADE_STAGGER_MS}ms`,
  mr_dandy: `${FADE_STAGGER_MS * 2}ms`,
};

const shopNameMap = Object.fromEntries(
  Shops.map((shop) => [shop.storeId, shop.name])
) as Record<string, string>;

export const shopColorMap = Object.fromEntries(
  Shops.map((shop) => [shop.storeId, shop.shopColor ?? 'transparent'])
) as Record<string, string>;

const createEmptyGroups = (): AreaCastGroups => ({
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

export const useAreaCastRotation = (dataPath: string) => {
  const [groups, setGroups] = useState<AreaCastGroups>(createEmptyGroups);
  const [activeIndices, setActiveIndices] = useState<ActiveIndices>(
    createInitialIndices
  );

  useEffect(() => {
    let cancelled = false;

    const fetchData = async () => {
      try {
        const timestamp =
          process.env.NODE_ENV === 'development' ? Date.now() : '';
        const path = `${dataPath}${timestamp ? `?t=${timestamp}` : ''}`;

        const response = await fetch(path);
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = (await response.json()) as AreaCastItem[];
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

        setGroups(grouped);
        setActiveIndices({
          club_dandy: getRandomIndex(grouped.club_dandy.length),
          dandy: getRandomIndex(grouped.dandy.length),
          mr_dandy: getRandomIndex(grouped.mr_dandy.length),
        });
      } catch {
        // エラー時は何もしない
      }
    };

    fetchData();

    return () => {
      cancelled = true;
    };
  }, [dataPath]);

  useEffect(() => {
    const hasAnyRotation = SHOP_ORDER.some(
      (shopId) => groups[shopId].length > 1
    );
    if (!hasAnyRotation) {
      return;
    }

    const timeouts: ReturnType<typeof setTimeout>[] = [];
    const intervals: ReturnType<typeof setInterval>[] = [];

    SHOP_ORDER.forEach((shopId, index) => {
      const length = groups[shopId].length;
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
  }, [groups]);

  return { groups, activeIndices };
};
