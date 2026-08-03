/* =======================================
 * 横浜ダンディーグループ ワンデイランキング
 * URL: /src/components/common/oneday/OneDayRanking.tsx
 * Referenced in: /src/app/dandy/oneday_ranking/page.tsx
 * Created: 2026-08-03
 * Last updated: 2026-08-03
 * ======================================= */
'use client';

import { useEffect, useState } from 'react';
import clsx from 'clsx';
import Image from 'next/image';
import Link from 'next/link';
import styles from './OneDayRanking.module.scss';
import { CastGradeMap } from '@/data/CastGradeData';
import ShopPageTitle from '@/components/common/ShopPageTitle';
import CastBadgeIcons from '@/components/common/cast/CastBadgeIcons';

type ShopId = 'dandy' | 'mr_dandy' | 'club_dandy';

type OneDayRankingCast = {
  rankID?: string;
  rank: number;
  shopID: ShopId;
  gradeId: number;
  castId: string;
  castName: string;
  castImage: string;
  age: number | null;
  ageText?: string;
  badgeType?: 'none' | 'new' | 'new_kirakira' | 'trial' | 'osusume' | 'event';
  shopIconRank?: number | null;
  areaIconRank?: number | null;
  tall: number;
  bust: number;
  cup: string;
  waist: number;
  hip: number;
};

type OneDayRankingData = {
  titleId: number;
  displayCount?: number;
  casts: OneDayRankingCast[];
};

type OneDayRankingProps = {
  shop: ShopId;
  jsonPath: string;
};

const DEFAULT_DISPLAY_COUNT = 5;

const getAgeLabel = (cast: OneDayRankingCast) => {
  if (cast.ageText) return cast.ageText;
  if (cast.age !== null) return String(cast.age);
  return '';
};

export default function OneDayRanking({
  shop,
  jsonPath,
}: OneDayRankingProps) {
  const [rankingData, setRankingData] = useState<OneDayRankingData | null>(
    null
  );
  const [isError, setIsError] = useState(false);

  useEffect(() => {
    fetch(`${jsonPath}?t=${Date.now()}`, { cache: 'no-store' })
      .then((res) => {
        if (!res.ok) throw new Error('fetch failed');
        return res.json();
      })
      .then((json) => {
        setRankingData(json as OneDayRankingData);
        setIsError(false);
      })
      .catch(() => setIsError(true));
  }, [jsonPath]);

  if (isError) {
    return (
      <p className={styles.itemError}>ランキングの読み込みに失敗しました。</p>
    );
  }

  if (!rankingData) {
    return null;
  }

  const casts = rankingData.casts.slice(
    0,
    Math.min(
      rankingData.displayCount ?? DEFAULT_DISPLAY_COUNT,
      DEFAULT_DISPLAY_COUNT
    )
  );

  return (
    <section className={styles.oneDayRanking}>
      <ShopPageTitle
        titleJp="ワンデイランキング"
        titleEn="one day ranking"
        shop={shop}
        variant="noLogo"
      />
      <ol className={styles.rankingList}>
        {casts.map((cast) => {
          const grade = CastGradeMap[cast.gradeId];
          const hasNewBadge = cast.badgeType === 'new';
          const hasNewKirakiraBadge = cast.badgeType === 'new_kirakira';

          return (
            <li
              key={cast.rankID ?? `${cast.shopID}-${cast.castId}-${cast.rank}`}
              className={styles.boxCast}
            >
              <Link
                href={`/${cast.shopID}/profile/?id=${cast.castId}&type=ranking&rankingType=oneday`}
                prefetch={false}
                className={styles.cardLink}
              >
                <CastBadgeIcons
                  badgeType={cast.badgeType}
                  shopIconRank={cast.shopIconRank}
                  areaIconRank={cast.areaIconRank}
                  wrapperClassName={styles.wrapBadge}
                  imageClassName={styles.badgeImage}
                />
                <div
                  className={clsx(
                    styles.wrapPhoto,
                    hasNewBadge && styles.hasNewBadge,
                    hasNewKirakiraBadge && styles.hasNewKirakira,
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
                  <div
                    className={clsx(styles.itemRanking, {
                      [styles[`ranking${cast.rank}`]]: cast.rank,
                    })}
                  >
                    <Image
                      src={`/images/common/ranking/${String(cast.rank).padStart(2, '0')}.webp`}
                      alt={`ランキング ${cast.rank}位`}
                      width={50}
                      height={50}
                    />
                  </div>
                </div>
              </Link>
              <div className={styles.castProfile}>
                <div className={styles.wrapName}>
                  <p className={styles.castName}>{cast.castName}</p>
                  <span className={styles.age}>{getAgeLabel(cast)}</span>
                </div>
                <div className={styles.castSize}>
                  <span className={styles.tall}>{cast.tall}</span>
                  <span className={styles.bust}>
                    {cast.bust}
                    <i>{cast.cup}</i>
                  </span>
                  <span className={styles.waist}>{cast.waist}</span>
                  <span className={styles.hip}>{cast.hip}</span>
                </div>
              </div>
            </li>
          );
        })}
      </ol>
    </section>
  );
}
