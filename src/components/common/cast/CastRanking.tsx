/* =======================================
 * 横浜ダンディーグループ 共通ランキング本体
 * URL: /src/components/common/cast/CastRanking.tsx
 * Referenced in: /src/app/top/page.tsx, /src/components/common/top/TopCastRanking.tsx
 * Created: 2026-07-09
 * Last updated: 2026-07-17
 * ======================================= */

'use client';

import { Fragment, useEffect, useRef, useState } from 'react';
import type { CSSProperties } from 'react';
import clsx from 'clsx';
import Image from 'next/image';
import Link from 'next/link';
import styles from '@/components/common/cast/CastRanking.module.scss';
import { CastGradeMap } from '@/data/CastGradeData';
import { Shops } from '@/data/AreaShopData';
import type { CastDetail } from '@/types/CastDetails';

type RankingCast = Pick<
  CastDetail,
  | 'rankID'
  | 'rank'
  | 'castId'
  | 'castName'
  | 'castImage'
  | 'age'
  | 'ageText'
  | 'tall'
  | 'bust'
  | 'cup'
  | 'west'
  | 'hip'
  | 'gradeId'
> & {
  shopID: 'dandy' | 'mr_dandy' | 'club_dandy';
};

type RankingTab = {
  titleId: number;
  title: string;
  displayCount?: number;
  layoutGroups?: number[];
  accordion?: {
    enabled?: boolean;
    initialVisibleGroupCount?: number;
    insertAfterGroup?: number;
  };
  casts: RankingCast[];
};

type CastRankingProps = {
  jsonPath: string;
  showShopName?: boolean;
  rankIconType?: 'area' | 'dandy' | 'mr' | 'club';
  className?: string;
};

const shopColorMap = Object.fromEntries(
  Shops.map((shop) => [shop.storeId, shop.shopColor ?? '#000'])
) as Record<string, string>;
const shopNameEnMap = Object.fromEntries(
  Shops.map((shop) => [shop.storeId, shop.nameEn])
) as Record<string, string>;
const ACCORDION_BUTTON_LABEL_OPEN = 'キャストランキング一覧';
const ACCORDION_BUTTON_LABEL_CLOSE = 'キャストランキングを閉じる';

const getAgeLabel = (cast: RankingCast) => {
  if (cast.ageText) return cast.ageText;
  if (cast.age !== null) return `age.${cast.age}`;
  return '';
};

const getRankIconSrc = (
  rank: number | undefined,
  rankIconType: 'area' | 'dandy' | 'mr' | 'club'
) => {
  if (!rank || rank >= 11) return null;
  if (rank === 1) {
    return `/images/common/rank/${rankIconType}/rank01.webp`;
  }
  if (rank === 2) {
    return `/images/common/rank/${rankIconType}/rank02.webp`;
  }
  if (rank === 3) {
    return `/images/common/rank/${rankIconType}/rank03.webp`;
  }
  return `/images/common/rank/${rankIconType}/rank04.webp`;
};

const splitRankingGroups = (
  casts: RankingCast[],
  displayCount: number,
  layoutGroups?: number[]
) => {
  const visibleCasts = casts.slice(0, displayCount);
  if (!layoutGroups?.length) {
    return [visibleCasts].filter((group) => group.length > 0);
  }

  const groups: RankingCast[][] = [];
  let startIndex = 0;

  layoutGroups.forEach((count) => {
    if (startIndex >= visibleCasts.length) return;
    groups.push(visibleCasts.slice(startIndex, startIndex + count));
    startIndex += count;
  });

  if (startIndex < visibleCasts.length) {
    groups.push(visibleCasts.slice(startIndex));
  }

  return groups.filter((group) => group.length > 0);
};

export default function CastRanking({
  jsonPath,
  showShopName = false,
  rankIconType = 'area',
  className,
}: CastRankingProps) {
  const [rankingData, setRankingData] = useState<RankingTab[]>([]);
  const [selectedIndex, setSelectedIndex] = useState(0);
  const [isError, setIsError] = useState(false);
  const [isAccordionOpen, setIsAccordionOpen] = useState(false);
  const rankingRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    const path = `${jsonPath}?t=${Date.now()}`;

    fetch(path, { cache: 'no-store' })
      .then((res) => {
        if (!res.ok) throw new Error('fetch failed');
        return res.json();
      })
      .then((json) => {
        setRankingData(json as RankingTab[]);
        setIsError(false);
      })
      .catch(() => setIsError(true));
  }, [jsonPath]);

  useEffect(() => {
    setIsAccordionOpen(false);
  }, [selectedIndex, jsonPath]);

  if (isError) {
    return (
      <p className={styles.itemError}>ランキングの読み込みに失敗しました。</p>
    );
  }

  if (rankingData.length === 0) {
    return null;
  }

  const selectedRanking = rankingData[selectedIndex] ?? rankingData[0];
  const displayCount =
    selectedRanking.displayCount ?? selectedRanking.casts.length;
  const accordionSettings = selectedRanking.accordion;
  const groupedCasts = splitRankingGroups(
    selectedRanking.casts,
    displayCount,
    selectedRanking.layoutGroups
  );
  const initialVisibleGroupCount =
    accordionSettings?.initialVisibleGroupCount ?? 0;
  const isAccordionEnabled =
    accordionSettings?.enabled === true &&
    initialVisibleGroupCount > 0 &&
    initialVisibleGroupCount < groupedCasts.length;
  const collapsedLastGroupIndex = Math.min(
    Math.max(1, accordionSettings?.insertAfterGroup ?? initialVisibleGroupCount),
    initialVisibleGroupCount
  );
  const buttonGroupIndex = isAccordionOpen
    ? groupedCasts.length
    : collapsedLastGroupIndex;
  const accordionInsertAfterGroup = Math.min(
    Math.max(
      1,
      buttonGroupIndex
    ),
    groupedCasts.length
  );

  const handleAccordionToggle = () => {
    if (isAccordionOpen) {
      rankingRef.current?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
      });
    }

    setIsAccordionOpen((prev) => !prev);
  };

  const renderCard = (cast: RankingCast, key: string) => {
    const grade = CastGradeMap[cast.gradeId];
    const rankIconSrc = getRankIconSrc(cast.rank, rankIconType);

    return (
      <Link
        key={key}
        href={`/${cast.shopID}/profile/?id=${cast.castId}`}
        className={styles.cardLink}
      >
        <div
          className={clsx(
            styles.rankIcon,
            rankIconSrc ? styles.hasRankIcon : styles.noRankIcon
          )}
          style={
            rankIconSrc
              ? ({
                  '--rank-icon-image': `url(${rankIconSrc})`,
                } as CSSProperties)
              : undefined
          }
        >
          <span>NO.{cast.rank}</span>
        </div>
        <div
          className={clsx(
            styles.wrapPhoto,
            cast.gradeId >= 0 && styles[`grade${cast.gradeId}`]
          )}
        >
          <div className={styles.photoFrame}>
            <Image
              src={cast.castImage}
              alt={cast.castName}
              width={220}
              height={300}
            />
          </div>
          {grade && grade.gradeId !== 0 ? (
            <p className={styles.gradeLabel}>{grade.labelEn}</p>
          ) : null}
        </div>
        <div className={styles.textProfile}>
          {showShopName ? (
            <p
              className={styles.shopName}
              style={{ color: shopColorMap[cast.shopID] }}
            >
              {shopNameEnMap[cast.shopID]?.toUpperCase() ?? ''}
            </p>
          ) : null}
          <p className={styles.castName}>{cast.castName}</p>
          <div className={styles.sizeHead}>
            {getAgeLabel(cast) ? (
              <span className={styles.age}>{getAgeLabel(cast)}</span>
            ) : null}
            <span className={styles.cup}>{cast.cup} cup</span>
          </div>
          <div className={styles.size}>
            <span className={styles.tall}>T.{cast.tall}</span>
            <span className={styles.bust}>B.{cast.bust}</span>
            <span className={styles.west}>W.{cast.west}</span>
            <span className={styles.hip}>H.{cast.hip}</span>
          </div>
        </div>
      </Link>
    );
  };

  return (
    <div
      ref={rankingRef}
      className={clsx(styles.containerRanking, className && styles[className])}
    >
      {rankingData.length > 1 ? (
        <nav
          className={clsx(
            styles.rankingNav,
            styles[`len${rankingData.length}`]
          )}
        >
          {rankingData.map((item, index) => (
            <button
              key={item.titleId}
              type="button"
              className={clsx(
                styles.navButton,
                index === selectedIndex && styles.isActive
              )}
              onClick={() => setSelectedIndex(index)}
            >
              <span>{item.title}</span>
            </button>
          ))}
        </nav>
      ) : null}
      <div
        className={clsx(
          styles.rankingGroups,
          styles[`display${displayCount}`],
          isAccordionEnabled && styles.hasAccordion,
          isAccordionOpen && styles.isAccordionOpen
        )}
      >
        {groupedCasts.map((group, index) => {
          const isHiddenGroup =
            isAccordionEnabled &&
            !isAccordionOpen &&
            index + 1 > initialVisibleGroupCount;

          return (
            <Fragment key={`group-${index}`}>
              <div
                className={clsx(
                  styles.groupAccordion,
                  isHiddenGroup && styles.isCollapsedGroup
                )}
                aria-hidden={isHiddenGroup}
              >
                <section className={styles.groupList}>
                  {group.map((cast) =>
                    renderCard(
                      cast,
                      `${cast.shopID}-${cast.castId}-${cast.rankID ?? cast.rank}`
                    )
                  )}
                </section>
              </div>
              {isAccordionEnabled && index + 1 === accordionInsertAfterGroup ? (
                <button
                  type="button"
                  className={styles.accordionButton}
                  aria-expanded={isAccordionOpen}
                  onClick={handleAccordionToggle}
                >
                  <span className={styles.accordionLabel}>
                    {isAccordionOpen
                      ? ACCORDION_BUTTON_LABEL_CLOSE
                      : ACCORDION_BUTTON_LABEL_OPEN}
                  </span>
                </button>
              ) : null}
            </Fragment>
          );
        })}
      </div>
    </div>
  );
}
