/* =======================================
 * キャスト一覧 コンポーネント
 * URL: /src/components/common/cast/ShopCastList.tsx
 * Referenced in: /src/app/dandy/cast/page.tsx
 * Created: 2025-09-02
 * Last updated: 2026-07-17
 * ======================================= */
'use client';
import styles from './ShopCastList.module.scss';
import { usePathname } from 'next/navigation';
import clsx from 'clsx';
import { useEffect, useMemo, useState } from 'react';
import type { CastDetail } from '@/types/CastDetails';
import ShopItemCastList from '@/components/common/cast/ShopItemCastList';
import { getShopFromPath, getStoreClass } from '@/lib/shopUtils';
import { trackPageAccess } from '@/lib/accessCounterApi';
import ShopSwitchTabs from '@/components/common/ShopSwitchTabs';
import { CastGradeMap } from '@/data/CastGradeData';

const filters = [
  { id: 'today', label: '本日出勤' },
  { id: 'age', label: '年齢' },
  { id: 'height', label: '身長' },
  { id: 'cup', label: 'カップ' },
  { id: 'new', label: '新人' },
];

const gradeDisplayOrder = [7, 6, 5, 4, 3, 2, 1, 8, 0];

const timeToMinutes = (time?: string) => {
  if (!time || !/^\d{2}:\d{2}$/.test(time)) return null;
  const [hours, minutes] = time.split(':').map(Number);
  return hours * 60 + minutes;
};

const CastList = () => {
  const pathname = usePathname();
  const shop = getShopFromPath(pathname);
  const activeStoreClass = getStoreClass(shop);

  const [castList, setCastList] = useState<CastDetail[]>([]);

  useEffect(() => {
    //ページ読み込み時にバックグラウンド処理でユーザーアクセス情報をログに保存する
    const pageId = 'castList';
    trackPageAccess(shop, pageId);
    //ページ読み込み時にバックグラウンド処理でユーザーアクセス情報をログに保存する

    const fetchCastList = async () => {
      try {
        // キャストリストは出勤情報含むため常にキャッシュバスティング
        const timestamp = Date.now();
        const jsonPath = `/db/contents/${shop}/CastList.json?t=${timestamp}`;

        const response = await fetch(jsonPath);
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data: CastDetail[] = await response.json();
        setCastList(data);
      } catch {
        // エラー時の処理（ログ出力など不要なら空でOK）
        setCastList([]);
      }
    };

    fetchCastList();
  }, [shop]);

  const [activeFilter, setActiveFilter] = useState<string | null>(null);
  const handleFilterClick = (filterId: string) => {
    setActiveFilter(filterId);
  };

  const registeredCastList = useMemo(() => {
    const allCasts = [...castList];

    let filtered = allCasts;
    if (!activeFilter) {
      return allCasts;
    }
    if (activeFilter === 'today') {
      filtered = allCasts.filter(
        (cast) =>
          cast.scheduleStatus ||
          cast.startTime ||
          cast.endTime ||
          (Array.isArray(cast.timeSlots) && cast.timeSlots.length > 0)
      );
    } else if (activeFilter === 'new') {
      filtered = allCasts.filter(
        (cast) =>
          cast.badgeType === 'new' ||
          cast.badgeType === 'new_kirakira' ||
          cast.badgeType === 'trial'
      );
    }

    switch (activeFilter) {
      case 'today':
        filtered.sort((a, b) => {
          const startA = timeToMinutes(a.startTime);
          const startB = timeToMinutes(b.startTime);

          if (startA !== null && startB !== null) {
            return startA - startB;
          }
          if (startA !== null) return -1;
          if (startB !== null) return 1;

          return 0;
        });
        break;
      case 'age':
        filtered.sort((a, b) => {
          const safeAgeA = a.age ?? Number.MAX_SAFE_INTEGER;
          const safeAgeB = b.age ?? Number.MAX_SAFE_INTEGER;
          return safeAgeA - safeAgeB;
        });
        break;
      case 'height':
        filtered.sort((a, b) => a.tall - b.tall);
        break;
      case 'cup': {
        const cupOrder = [
          'a',
          'b',
          'c',
          'd',
          'e',
          'f',
          'g',
          'h',
          'i',
          'j',
          'k',
        ];
        filtered.sort((a, b) => {
          const aIndex = cupOrder.indexOf((a.cup || '').toLowerCase());
          const bIndex = cupOrder.indexOf((b.cup || '').toLowerCase());
          const safeA = aIndex === -1 ? 999 : aIndex;
          const safeB = bIndex === -1 ? 999 : bIndex;
          return safeB - safeA;
        });
        break;
      }
      default:
        filtered.sort((a, b) => a.gradeId - b.gradeId);
        break;
    }

    return filtered;
  }, [activeFilter, castList]);

  const groupedCastList = useMemo(() => {
    const groupedMap = new Map<number, CastDetail[]>();

    registeredCastList.forEach((cast) => {
      const group = groupedMap.get(cast.gradeId) ?? [];
      group.push(cast);
      groupedMap.set(cast.gradeId, group);
    });

    return Array.from(groupedMap.entries())
      .sort(([gradeA], [gradeB]) => {
        const orderA = gradeDisplayOrder.indexOf(gradeA);
        const orderB = gradeDisplayOrder.indexOf(gradeB);
        const safeOrderA = orderA === -1 ? Number.MAX_SAFE_INTEGER : orderA;
        const safeOrderB = orderB === -1 ? Number.MAX_SAFE_INTEGER : orderB;
        return safeOrderA - safeOrderB;
      })
      .map(([gradeId, casts]) => ({
        gradeId,
        title:
          gradeId === 0
            ? 'normal'
            : (CastGradeMap[gradeId]?.labelEn ?? '').toUpperCase(),
        casts,
      }));
  }, [registeredCastList]);

  // 表示キャスト順を sessionStorage に保存
  useEffect(() => {
    if (registeredCastList.length > 0) {
      const castOrder = registeredCastList.map((c) => ({
        id: c.castId,
        name: c.castName,
      }));
      sessionStorage.setItem('castOrder_castlist', JSON.stringify(castOrder));
    }
  }, [registeredCastList]);
  return (
    <>
      <div className={styles.blockSwitchTab}>
        <ShopSwitchTabs basePath="cast" variant="cast" />
      </div>
      <section
        className={clsx(styles.containerSearch, styles[activeStoreClass])}
      >
        <h3>more search</h3>
        <ul className={styles.filterList}>
          {filters.map((filter) => (
            <li key={filter.id}>
              <button
                type="button"
                className={clsx(styles.filterBtn, {
                  [styles.isActive]: activeFilter === filter.id,
                })}
                onClick={() => handleFilterClick(filter.id)}
              >
                {filter.label.split('').map((char, index) => (
                  <i key={index}>{char}</i>
                ))}
              </button>
            </li>
          ))}
          <li>
            <button
              type="button"
              className={clsx(styles.btnReset, {
                [styles.isActive]: activeFilter === null,
              })}
              onClick={() => setActiveFilter(null)}
            >
              リセット
            </button>
          </li>
        </ul>
      </section>
      <div className={clsx(styles.containerList, styles[activeStoreClass])}>
        {activeFilter ? (
          <ul className={clsx(styles.castList, styles.filteredCastList)}>
            {registeredCastList.map((cast) => (
              <ShopItemCastList key={cast.castId} cast={cast} />
            ))}
          </ul>
        ) : (
          groupedCastList.map((group) => (
            <section
              key={`grade-${group.gradeId}`}
              className={clsx(
                styles.groupBlock,
                styles[`grade${group.gradeId}`]
              )}
            >
              <h2>{group.title}</h2>
              <ul className={styles.castList}>
                {group.casts.map((cast) => (
                  <ShopItemCastList key={cast.castId} cast={cast} />
                ))}
              </ul>
            </section>
          ))
        )}
      </div>
    </>
  );
};

export default CastList;
