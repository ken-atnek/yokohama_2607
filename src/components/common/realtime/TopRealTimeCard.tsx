/* =======================================
 * 横浜ダンディーグループ TOPリアルタイムカード
 * URL: /src/components/common/realtime/TopRealTimeCard.tsx
 * Referenced in: /src/components/common/ShopLeft.tsx
 * Created: 2026-07-20
 * Last updated: 2026-08-01
 * ======================================= */
'use client';

import Link from 'next/link';
import Image from 'next/image';
import { useEffect, useMemo, useState } from 'react';
import clsx from 'clsx';
import styles from './TopRealTimeCard.module.scss';
import { CastGradeMap } from '@/data/CastGradeData';
import { getRealTimeDetailText } from '@/lib/getRealTimeDetailText';
import { normalizeCastBase } from '@/lib/normalizeCast';
import type { CastDetail } from '@/types/CastDetails';

type Props = {
  shop: string;
};

type TopRealTimeData = CastDetail & {
  availableFrom?: string;
};

const getAgeLabel = (cast: TopRealTimeData) => {
  if (cast.ageText) return cast.ageText;
  if (cast.age !== null) return String(cast.age);
  return '';
};

function normalizeTopRealtime(
  source: Partial<CastDetail> & Record<string, unknown>,
  shop: string
): TopRealTimeData | null {
  const normalized = normalizeCastBase(source, {
    shop,
    profileImagesMode: 'empty',
  });

  if (!normalized) {
    return null;
  }

  return {
    ...normalized,
    availableFrom:
      typeof source.availableFrom === 'string' && source.availableFrom !== ''
        ? source.availableFrom
        : undefined,
  };
}

export default function TopRealTimeCard({
  shop,
}: Props) {
  const [data, setData] = useState<TopRealTimeData | null>(null);

  useEffect(() => {
    const fetchTopRealTime = async () => {
      try {
        const timestamp = Date.now();
        const response = await fetch(
          `/db/contents/${shop}/TopRealTime.json?t=${timestamp}`,
          { cache: 'no-store' }
        );

        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const json = (await response.json()) as Partial<CastDetail> &
          Record<string, unknown>;
        setData(normalizeTopRealtime(json, shop));
      } catch {
        setData(null);
      }
    };

    fetchTopRealTime();
  }, [shop]);

  const statusText = useMemo(() => {
    if (!data) {
      return '';
    }

    return getRealTimeDetailText({
      detail: data.realTimeDetail,
      availableFrom: data.availableFrom,
      shouldCompareTime: true,
    });
  }, [data]);

  const commentLines = useMemo(() => {
    if (!data?.realTimeComment) {
      return [];
    }

    const normalizedComment = data.realTimeComment.replace(/\r\n/g, '\n');
    const lines = normalizedComment.split('\n').filter((line) => line !== '');

    return lines.length > 0 ? lines : [normalizedComment];
  }, [data]);

  if (!data) {
    return null;
  }

  const grade = CastGradeMap[data.gradeId];

  return (
    <article className={styles.topRealTimeCard} data-top-realtime-card>
      <div className={styles.head}>
        <h2 className={styles.titleEn}>REAL TIME</h2>
        <p className={styles.titleJp}>リアルタイム情報</p>
      </div>
      <div className={styles.boxDetails}>
        <Link
          href={`/${shop}/profile/?id=${data.castId}&type=realtime`}
          className={clsx(
            styles.wrapPhoto,
            data.gradeId >= 0 && styles[`grade${data.gradeId}`]
          )}
          prefetch={false}
        >
          <div className={styles.photoFrame}>
            <Image
              src={data.castImage}
              alt={data.castName}
              fill
              sizes="220px"
            />
          </div>
          {grade && grade.gradeId !== 0 ? (
            <p className={styles.gradeLabel}>{grade.labelEn}</p>
          ) : null}
        </Link>
        <div className={styles.textBlock}>
          <h3 className={styles.castName}>{data.castName}</h3>
          <div className={styles.sizeHead}>
            {getAgeLabel(data) ? (
              <span className={styles.age}>{getAgeLabel(data)}</span>
            ) : null}
            {data.cup ? (
              <span className={styles.cup}>{data.cup} cup</span>
            ) : null}
          </div>
          <div className={styles.size}>
            <span className={styles.tall}>T.{data.tall}</span>
            <span className={styles.bust}>B.{data.bust}</span>
            <span className={styles.waist}>W.{data.waist}</span>
            <span className={styles.hip}>H.{data.hip}</span>
          </div>
          <div className={styles.comment}>
            {commentLines.map((line, index) => (
              <p key={`${data.castId}-${index}`}>{line}</p>
            ))}
          </div>
        </div>
      </div>
      <div className={styles.statusBalloon}>{statusText || '待ち時間なし'}</div>
      <Link href={`/${shop}/realtime/`} className={styles.linkButton}>
        <span>
          リアルタイムキャスト一覧
          <svg>
            <use href="#iconArrowRight" />
          </svg>
        </span>
      </Link>
    </article>
  );
}
