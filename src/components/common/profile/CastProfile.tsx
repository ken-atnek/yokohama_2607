/* =======================================
 * 横浜ダンディーグループ キャスト詳細
 * URL: /src/components/common/profile/CastProfile.tsx
 * Referenced in: /src/app/dandy/profile/page.tsx
 * Created: 2025-09-06
 * Last updated: 2026-07-31
 * ======================================= */
'use client';

import styles from './ShopCastProfile.module.scss';
import { Splide, SplideSlide } from '@splidejs/react-splide';
import '@splidejs/react-splide/css';
import Image from 'next/image';
import Link from 'next/link';
import clsx from 'clsx';
import { usePathname, useSearchParams } from 'next/navigation';
import { useEffect, useState } from 'react';
import { CastGradeMap } from '@/data/CastGradeData';
import { Shops } from '@/data/AreaShopData';
import { getShopFromPath, getStoreClass } from '@/lib/shopUtils';
import { convertRemToPx } from '@/lib/convertRemToPx';
import { trackPageAccess } from '@/lib/accessCounterApi';
import { trackCastAccess } from '@/lib/accessCastCounterApi';
import type { CastDetail, RecommendationItem } from '@/types/CastDetails';
import ExternalLink from '@/components/common/ExternalLink';
import CastSchedule from './CastSchedule';
import CastBadgeIcons from '@/components/common/cast/CastBadgeIcons';
import { normalizeCastBase } from '@/lib/normalizeCast';
import { getRealTimeDetailText } from '@/lib/getRealTimeDetailText';
import {
  formatRealtimeTime,
  normalizeRealTimeData,
  REALTIME_STATUS_LABELS,
  type RealTimeData,
} from '@/lib/realtime';

const shopNameMap: Record<string, string> = {
  dandy: '横浜ダンディー',
  mr_dandy: 'ミスターダンディ',
  club_dandy: 'クラブダンディ',
};

type CastProfileDetail = CastDetail & {
  type: string[];
  recommendations: RecommendationItem[];
};

type RecommendationCard = RecommendationItem & {
  castName: string;
};

function normalizeCastDetail(
  source: Partial<CastDetail> & Record<string, unknown>,
  shop: string,
  castId: string
): CastProfileDetail {
  const normalized = normalizeCastBase(source, {
    shop,
    fallbackCastId: castId,
    profileImagesMode: 'fallback',
  });

  if (!normalized) {
    return {
      castId,
      castName: '',
      castNameEn: '',
      castImage: `/images/cast/${shop}/no-image.webp`,
      profileImages: [`/images/cast/${shop}/no-image.webp`],
      age: null,
      tall: 0,
      bust: 0,
      cup: '',
      waist: 0,
      hip: 0,
      shopId: shop,
      shopName: '',
      realTimeStatus: 0,
      realTimeDetail: '',
      realTimeComment: '',
      gradeId: 0,
      type: [],
      recommendations: [],
      badgeType: 'none',
      shopIconRank: null,
      areaIconRank: null,
    };
  }

  return {
    ...normalized,
    type: (normalized.type ?? []).slice(0, 4),
    photoBlogUrl:
      typeof source.photoBlogUrl === 'string'
        ? source.photoBlogUrl
        : typeof source.blogUrl === 'string'
          ? source.blogUrl
          : undefined,
    reservationUrl:
      typeof source.reservationUrl === 'string'
        ? source.reservationUrl
        : typeof source.reserveUrl === 'string'
          ? source.reserveUrl
          : undefined,
    recommendations: Array.isArray(source.recommendations)
      ? source.recommendations
          .filter(
            (item): item is RecommendationItem =>
              typeof item === 'object' &&
              item !== null &&
              typeof item.shopId === 'string' &&
              typeof item.castId === 'string'
          )
          .slice(0, 4)
      : [],
  };
}

export default function CastProfile() {
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const castId = searchParams.get('id');
  const [cast, setCast] = useState<CastProfileDetail | null>(null);
  const [recommendationCards, setRecommendationCards] = useState<
    RecommendationCard[]
  >([]);
  const [realTimeData, setRealTimeData] = useState<RealTimeData | null>(null);
  const [error, setError] = useState(false);

  const shop = getShopFromPath(pathname);
  const activeStoreClass = getStoreClass(shop);
  const shopData = Shops.find((shopItem) => shopItem.storeId === shop);
  const shopPhone = shopData?.phone || '';
  const noticeMailAddress = shopData?.noticeMailAddress || '';
  const hasReservationLink = Boolean(cast?.reservationUrl);
  const hasPhotoBlogLink = Boolean(cast?.photoBlogUrl);
  const hasNoticeMailLink = Boolean(noticeMailAddress && castId);
  const noticeMailHref = hasNoticeMailLink
    ? `mailto:${noticeMailAddress}?subject=${encodeURIComponent(`add:${castId}`)}&body=${encodeURIComponent('そのまま送信してください')}`
    : undefined;

  const [prevCastId, setPrevCastId] = useState<string | null>(null);
  const [nextCastId, setNextCastId] = useState<string | null>(null);
  const [prevCastName, setPrevCastName] = useState<string | null>(null);
  const [nextCastName, setNextCastName] = useState<string | null>(null);
  const type = searchParams.get('type') || 'ranking';
  const rankingType = searchParams.get('rankingType');

  useEffect(() => {
    try {
      let castOrderStr: string | null = null;
      if (type === 'ranking' && rankingType) {
        castOrderStr = sessionStorage.getItem(
          `castOrder_ranking_${rankingType}`
        );
      } else {
        castOrderStr = sessionStorage.getItem(`castOrder_${type}`);
      }
      if (!castOrderStr) return;

      type CastOrderItem = {
        id: string;
        name: string;
      };
      const castOrder = JSON.parse(castOrderStr) as CastOrderItem[];
      const currentId = searchParams.get('id');
      if (!currentId) return;

      const currentIndex = castOrder.findIndex((c) => c.id === currentId);
      if (currentIndex === -1) return;

      const prev = castOrder[currentIndex - 1];
      const next = castOrder[currentIndex + 1];

      setPrevCastId(prev?.id || null);
      setNextCastId(next?.id || null);
      setPrevCastName(prev?.name || null);
      setNextCastName(next?.name || null);
    } catch {
      // エラー時の処理（何もしない）
    }
  }, [type, rankingType, castId, searchParams]);

  useEffect(() => {
    if (cast) {
      const shopName = shopNameMap[shop] || '横浜ダンディーグループ';
      document.title = `${cast.castName}さんのプロフィール | ${shopName}`;

      const jsonLd = {
        '@context': 'https://schema.org',
        '@type': 'Person',
        name: cast.castName,
        description: `${cast.age ?? ''}歳 / ${cast.tall}cm / ${cast.bust}-${cast.waist}-${cast.hip} (${cast.cup})`,
        image: cast.castImage,
      };

      const script = document.createElement('script');
      script.type = 'application/ld+json';
      script.text = JSON.stringify(jsonLd);
      script.id = 'structured-data-cast';

      const existing = document.getElementById('structured-data-cast');
      if (existing) {
        existing.remove();
      }

      document.head.appendChild(script);
    }
  }, [cast, shop]);

  useEffect(() => {
    if (!castId) return;

    const fetchCastData = async () => {
      const timestamp = Date.now();
      const detailPaths = [
        `/db/cast/${shop}/${castId}/details.json`,
        `/cast/${shop}/${castId}/details.json`,
      ];

      try {
        for (const path of detailPaths) {
          const response = await fetch(`${path}?t=${timestamp}`);
          if (!response.ok) {
            continue;
          }

          const data = (await response.json()) as Partial<CastDetail> &
            Record<string, unknown>;
          setCast(normalizeCastDetail(data, shop, castId));
          setError(false);
          return;
        }

        const summaryResponse = await fetch(
          `/db/contents/${shop}/CastList.json?t=${timestamp}`
        );
        if (!summaryResponse.ok) {
          throw new Error('キャスト情報が見つかりません');
        }

        const summaryData = (await summaryResponse.json()) as Array<
          Partial<CastDetail> & Record<string, unknown>
        >;
        const summaryCast = summaryData.find((item) => item.castId === castId);
        if (!summaryCast) {
          throw new Error('キャスト情報が見つかりません');
        }

        setCast(normalizeCastDetail(summaryCast, shop, castId));
        setError(false);
      } catch {
        setCast(null);
        setError(true);
      }
    };

    fetchCastData();
  }, [castId, shop]);

  useEffect(() => {
    if (!castId) return;

    const fetchRealTimeData = async () => {
      const timestamp = Date.now();
      const realtimePaths = [
        `/db/cast/${shop}/${castId}/realtime.json`,
        `/cast/${shop}/${castId}/realtime.json`,
      ];

      try {
        for (const path of realtimePaths) {
          const response = await fetch(`${path}?t=${timestamp}`, {
            cache: 'no-store',
          });
          if (!response.ok) {
            continue;
          }

          const data = (await response.json()) as Partial<RealTimeData> &
            Record<string, unknown>;
          setRealTimeData(normalizeRealTimeData(data));
          return;
        }

        setRealTimeData(null);
      } catch {
        setRealTimeData(null);
      }
    };

    fetchRealTimeData();
  }, [castId, shop]);

  useEffect(() => {
    if (!cast || cast.recommendations.length === 0) {
      setRecommendationCards([]);
      return;
    }

    let cancelled = false;

    const fetchRecommendations = async () => {
      const timestamp = Date.now();
      const shopIds = Array.from(
        new Set(cast.recommendations.map((item) => item.shopId))
      );

      try {
        const listEntries = await Promise.all(
          shopIds.map(async (shopId) => {
            const response = await fetch(
              `/db/contents/${shopId}/CastList.json?t=${timestamp}`
            );
            if (!response.ok) {
              return [shopId, [] as CastDetail[]] as const;
            }

            const data = (await response.json()) as CastDetail[];
            return [shopId, data] as const;
          })
        );

        if (cancelled) return;

        const listMap = new Map<string, CastDetail[]>(listEntries);
        const resolved = cast.recommendations
          .map((item) => {
            const targetList = listMap.get(item.shopId) ?? [];
            const targetCast = targetList.find(
              (target) => target.castId === item.castId
            );
            if (!targetCast) {
              return null;
            }

            return {
              ...item,
              castName: targetCast.castName,
            } satisfies RecommendationCard;
          })
          .filter((item): item is RecommendationCard => item !== null);

        setRecommendationCards(resolved);
      } catch {
        if (!cancelled) {
          setRecommendationCards([]);
        }
      }
    };

    fetchRecommendations();

    return () => {
      cancelled = true;
    };
  }, [cast]);

  //ページ読み込み時にバックグラウンド処理でユーザーアクセス情報をログに保存する
  useEffect(() => {
    if (!castId) return;

    // 重複実行を防ぐためのフラグをチェック
    const key = `access_tracked_${shop}_${castId}`;
    if (sessionStorage.getItem(key)) return;
    const pageId = 'castProfile';
    trackPageAccess(shop, pageId);
    //キャストアクセスカウンター
    trackCastAccess(castId);
    // フラグを設定（ページリロード時にリセットされる）
    sessionStorage.setItem(key, 'true');
  }, [castId, shop]);
  //ページ読み込み時にバックグラウンド処理でユーザーアクセス情報をログに保存する

  if (!castId) return <p>キャストIDが指定されていません。</p>;
  if (error) return <p>キャスト情報が見つかりません。</p>;
  if (!cast) return <p>読み込み中...</p>;

  const grade = CastGradeMap[cast.gradeId];
  const ageLabel = cast.ageText || (cast.age !== null ? String(cast.age) : '');
  const displayTypes = cast.type.slice(0, 4);
  const displayProfileImages =
    cast.profileImages.length > 0 ? cast.profileImages : [cast.castImage];
  const hasTypeList = displayTypes.length > 0;
  const hasNewBadge =
    cast.badgeType === 'new' || cast.badgeType === 'new_kirakira';
  const hasNewKirakiraBadge = cast.badgeType === 'new_kirakira';
  const hasScheduleBadge =
    Boolean(cast.startTime && cast.endTime) || Boolean(cast.scheduleStatus);
  const hasRecommendations = recommendationCards.length > 0;
  const showServiceList =
    shop === 'dandy' &&
    (typeof cast.serviceHealth === 'boolean' ||
      typeof cast.serviceMat === 'boolean');
  const realtimeStatusLabel = realTimeData
    ? REALTIME_STATUS_LABELS[realTimeData.realTimeStatus]
    : undefined;
  const realtimeDetailText = getRealTimeDetailText({
    detail: realTimeData?.realTimeDetail,
    availableFrom: realTimeData?.availableFrom,
    shouldCompareTime: true,
  });
  const formattedRealtimeUpdateTime = formatRealtimeTime(
    realTimeData?.realTimeUpdatedAt
  );
  const hasRealtimeBox = Boolean(
    realtimeStatusLabel || realtimeDetailText || realTimeData?.realTimeComment
  );

  return (
    <>
      <section
        className={clsx(styles.containerContents, styles[activeStoreClass])}
      >
        <article className={styles.blockDetails}>
          <div className={styles.boxCastImage}>
            <CastBadgeIcons
              badgeType={cast.badgeType}
              shopIconRank={cast.shopIconRank}
              areaIconRank={cast.areaIconRank}
              wrapperClassName={styles.wrapBadge}
              imageClassName={styles.badgeImage}
            />
            <div
              className={clsx(
                styles.imageFrame,
                hasNewBadge && styles.hasNewBadge,
                hasNewKirakiraBadge && styles.hasNewKirakira,
                cast.gradeId >= 0 && styles[`grade${cast.gradeId}`]
              )}
            >
              <div className={styles.photoFrame}>
                <Splide
                  options={{
                    type: displayProfileImages.length > 1 ? 'loop' : 'slide',
                    perPage: 1,
                    perMove: 1,
                    gap: 0,
                    arrows: false,
                    pagination: displayProfileImages.length > 1,
                    autoplay: false,
                    drag: displayProfileImages.length > 1,
                  }}
                  aria-label={`${cast.castName}のプロフィール画像`}
                  className={styles.profileSplide}
                >
                  {displayProfileImages.map((imageSrc, index) => (
                    <SplideSlide key={`${cast.castId}-image-${index}`}>
                      <div className={styles.imageClip}>
                        <Image
                          src={imageSrc}
                          alt={`${cast.castName}の画像${index + 1}`}
                          width={580}
                          height={773}
                          priority={index === 0}
                        />
                      </div>
                    </SplideSlide>
                  ))}
                </Splide>
              </div>
              {cast.gradeId >= 1 && cast.gradeId <= 8 ? (
                <div className={styles.gradeFrame}></div>
              ) : null}
              {grade && grade.gradeId !== 0 ? (
                <p className={styles.gradeLabel}>{grade.labelEn}</p>
              ) : null}
              {cast.ranking && (
                <div
                  className={clsx(styles.itemRanking, {
                    [styles[`ranking${cast.ranking}`]]: cast.ranking,
                  })}
                >
                  <Image
                    src={`/images/common/ranking/${String(cast.ranking).padStart(2, '0')}.webp`}
                    alt={`ランキング ${cast.ranking}位`}
                    width={50} // 必要に応じてサイズを調整
                    height={50} // 必要に応じてサイズを調整
                  />
                </div>
              )}
            </div>
          </div>
          <div className={styles.boxProfile}>
            <Link href={`/${shop}/cast/`} className={styles.linkList}>
              一覧へ戻る
            </Link>
            {hasTypeList ? (
              <ul className={styles.listType}>
                {displayTypes.map((label, index) => (
                  <li key={`${cast.castId}-type-${index}`}>{label}</li>
                ))}
              </ul>
            ) : null}

            <div className={styles.innerProfile}>
              <h1 className={styles.castName}>
                {cast.castName}
                {cast.castNameEn ? <span>{cast.castNameEn}</span> : null}
              </h1>
              {hasScheduleBadge ? (
                <div className={styles.todayBadge}>本日出勤</div>
              ) : null}
              <div className={styles.wrapSize}>
                {ageLabel ? (
                  <span className={styles.age}>{ageLabel}</span>
                ) : null}
                <div className={styles.size}>
                  <span className={styles.tall}>{cast.tall}</span>
                  <span className={styles.bust}>
                    {cast.bust}
                    <i>{cast.cup}</i>
                  </span>
                  <span className={styles.waist}>{cast.waist}</span>
                  <span className={styles.hip}>{cast.hip}</span>
                </div>
              </div>
              {showServiceList ? (
                <ul className={styles.serviceList}>
                  <li
                    className={clsx(
                      cast.serviceHealth ? styles.isActive : styles.isInactive
                    )}
                  >
                    ヘルス
                  </li>
                  <li
                    className={clsx(
                      cast.serviceMat ? styles.isActive : styles.isInactive
                    )}
                  >
                    マット
                  </li>
                </ul>
              ) : null}
            </div>
            {hasRealtimeBox ? (
              <div className={styles.boxRealTime}>
                <div className={styles.headRealTime}>
                  <h2>リアルタイム情報</h2>
                  {formattedRealtimeUpdateTime ? (
                    <p>
                      update!
                      <time dateTime={realTimeData?.realTimeUpdatedAt}>
                        {formattedRealtimeUpdateTime}
                      </time>
                    </p>
                  ) : null}
                </div>
                {realtimeStatusLabel ? (
                  <p
                    className={clsx(
                      styles.statusRealTime,
                      styles[`status${realTimeData?.realTimeStatus}`]
                    )}
                  >
                    {realtimeStatusLabel}
                  </p>
                ) : null}
                {realtimeDetailText ? (
                  <p className={styles.detailRealTime}>{realtimeDetailText}</p>
                ) : null}
                {realTimeData?.realTimeComment ? (
                  <p className={styles.commentRealTime}>
                    {realTimeData.realTimeComment}
                  </p>
                ) : null}
              </div>
            ) : null}
            <nav className={styles.wrapLink}>
              <ExternalLink
                href={cast.reservationUrl}
                className={clsx(
                  styles.linkReservation,
                  hasReservationLink ? styles.isActive : styles.isInactive
                )}
                aria-disabled={!hasReservationLink}
                tabIndex={hasReservationLink ? undefined : -1}
              >
                <h2>WEB予約</h2>
                <span>
                  <svg className={styles.arrow}>
                    <use href="#iconArrowRight" />
                  </svg>
                  ご予約はこちら
                </span>
              </ExternalLink>
              <ExternalLink
                href={cast.photoBlogUrl}
                className={clsx(
                  styles.linkPhotoBlog,
                  hasPhotoBlogLink ? styles.isActive : styles.isInactive
                )}
                aria-disabled={!hasPhotoBlogLink}
                tabIndex={hasPhotoBlogLink ? undefined : -1}
              >
                <h2>写メ日記</h2>
                <span>
                  <svg className={styles.arrow}>
                    <use href="#iconArrowRight" />
                  </svg>
                  PHOTO BLOG
                </span>
              </ExternalLink>
            </nav>
            <ExternalLink
              href={noticeMailHref}
              className={clsx(
                styles.linkMailNotice,
                hasNoticeMailLink ? styles.isActive : styles.isInactive
              )}
              aria-disabled={!hasNoticeMailLink}
              tabIndex={hasNoticeMailLink ? undefined : -1}
            >
              <div className={styles.iconBell}>
                <svg>
                  <use href="#iconBell" />
                </svg>
              </div>
              <span>出勤お知らせメール登録はこちら</span>
              <svg className={styles.arrow}>
                <use href="#iconArrowRight" />
              </svg>
            </ExternalLink>
          </div>
        </article>
        {cast.telop ? (
          <div className={styles.blockTelop}>
            <span>{cast.telop}</span>
          </div>
        ) : null}
        <div className={styles.boxSchedule}>
          <span className={styles.sidebarH2}>schedule</span>
          <h2 className={styles.itemH2}>週間スケジュール</h2>
          {cast && <CastSchedule castId={cast.castId} shop={shop} />}
          <div className={styles.boxBottom}>
            {cast.reservationUrl && (
              <ExternalLink
                href={cast.reservationUrl}
                className={styles.linkReservation}
              >
                <h3>ご予約はこちら</h3>
                <p>{cast.castName}さんの予約状況を確認</p>
              </ExternalLink>
            )}
            <h4>【ご予約についてのお知らせ】</h4>
            <p>
              ◆ご新規様・一般会員様は、前日の午前６時より電話予約ができます。
            </p>
            <p>
              ◆ＶＩＰ会員様は、３日前の午前６時より電話予約ができます。※当日の電話予約も大丈夫です。
            </p>
            <div className={styles.wrapTel}>
              <h5>ご予約・お問い合わせ番号：</h5>
              {shopPhone ? (
                <ExternalLink href={`tel:${shopPhone}`}>
                  <span>{shopPhone}</span>
                </ExternalLink>
              ) : null}
            </div>
          </div>
        </div>
        {hasRecommendations ? (
          <div className={styles.blockRecommendation}>
            <h2>
              <span>RECOMMENDATION</span>
              このキャストを見た方におすすめのキャスト
            </h2>
            <nav>
              {recommendationCards.map((item, index) => (
                <Link
                  key={`${item.shopId}-${item.castId}-${index}`}
                  href={`/${item.shopId}/profile?id=${item.castId}`}
                >
                  <svg>
                    <use href="#iconArrowRight" />
                  </svg>
                  <span>{item.castName}</span>
                </Link>
              ))}
            </nav>
          </div>
        ) : null}
        <div className={styles.boxShopComment}>
          <div className={styles.boxInner}>
            <div className={styles.wrapShopComment}>
              {cast.shopComment && (
                <div
                  className={styles.shopComment}
                  dangerouslySetInnerHTML={{
                    __html: convertRemToPx(cast.shopComment),
                  }}
                />
              )}
            </div>
          </div>
        </div>

        <div className={styles.boxCastNav}>
          <nav>
            {prevCastId ? (
              <Link
                href={`/${shop}/profile?id=${prevCastId}&type=${type}${type === 'ranking' && rankingType ? `&rankingType=${rankingType}` : ''}`}
                className={styles.linkCastDetail}
              >
                {prevCastName}
                <br className="sp" />
                さんのページへ
              </Link>
            ) : (
              <div></div>
            )}
            <Link href={`/${shop}/cast/`} className={styles.linkCastList}>
              在籍一覧へ
            </Link>
            {nextCastId ? (
              <Link
                href={`/${shop}/profile?id=${nextCastId}&type=${type}${type === 'ranking' && rankingType ? `&rankingType=${rankingType}` : ''}`}
                className={styles.linkCastDetail}
              >
                {nextCastName}
                <br className="sp" />
                さんのページへ
              </Link>
            ) : (
              <div></div>
            )}
          </nav>
        </div>
      </section>
    </>
  );
}
