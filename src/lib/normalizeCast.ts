/* =======================================
 * 横浜ダンディーグループ キャスト正規化ユーティリティ
 * URL: /src/lib/normalizeCast.ts
 * Referenced in: /src/components/common/profile/CastProfile.tsx
 * Created: 2026-07-17
 * Last updated: 2026-07-17
 * ======================================= */
import type { CastDetail, ScheduleTimeSlot } from '@/types/CastDetails';

type NormalizeCastOptions = {
  shop: string;
  fallbackCastId?: string;
  profileImagesMode?: 'empty' | 'fallback';
};

const isNonEmptyString = (value: unknown): value is string =>
  typeof value === 'string' && value !== '';

const normalizeBadgeType = (
  badgeType: unknown
): CastDetail['badgeType'] | undefined => {
  return badgeType === 'none' ||
    badgeType === 'new' ||
    badgeType === 'new_kirakira' ||
    badgeType === 'trial' ||
    badgeType === 'osusume' ||
    badgeType === 'event'
    ? badgeType
    : undefined;
};

const normalizeTimeSlots = (
  timeSlots: unknown
): ScheduleTimeSlot[] | undefined => {
  if (!Array.isArray(timeSlots)) {
    return undefined;
  }

  const validTimeSlots = timeSlots.filter(
    (slot): slot is ScheduleTimeSlot =>
      typeof slot === 'object' &&
      slot !== null &&
      isNonEmptyString(slot.startTime) &&
      isNonEmptyString(slot.endTime)
  );

  return validTimeSlots.length > 0 ? validTimeSlots : undefined;
};

export function normalizeCastBase(
  source: Partial<CastDetail> & Record<string, unknown>,
  { shop, fallbackCastId, profileImagesMode = 'empty' }: NormalizeCastOptions
): CastDetail | null {
  const castId =
    typeof source.castId === 'string' ? source.castId : fallbackCastId;

  if (!castId) {
    return null;
  }

  if (!isNonEmptyString(source.castName)) {
    return null;
  }

  const fallbackImage = isNonEmptyString(source.castImage)
    ? source.castImage
    : `/images/cast/${shop}/no-image.webp`;
  const profileImages =
    Array.isArray(source.profileImages) && source.profileImages.length > 0
      ? source.profileImages.filter(
          (image): image is string => isNonEmptyString(image)
        )
      : profileImagesMode === 'fallback'
        ? [fallbackImage]
        : [];

  return {
    castId,
    castName: source.castName,
    castNameEn: typeof source.castNameEn === 'string' ? source.castNameEn : '',
    castNameKana:
      typeof source.castNameKana === 'string' ? source.castNameKana : undefined,
    castImage: fallbackImage,
    movie: typeof source.movie === 'string' ? source.movie : undefined,
    moviePosterImage:
      typeof source.moviePosterImage === 'string'
        ? source.moviePosterImage
        : undefined,
    castImageSquare:
      typeof source.castImageSquare === 'string'
        ? source.castImageSquare
        : undefined,
    profileImages,
    age: typeof source.age === 'number' ? source.age : null,
    ageText: typeof source.ageText === 'string' ? source.ageText : undefined,
    tall:
      typeof source.tall === 'number'
        ? source.tall
        : typeof source.heightCm === 'number'
          ? source.heightCm
          : 0,
    bust: typeof source.bust === 'number' ? source.bust : 0,
    cup: typeof source.cup === 'string' ? source.cup : '',
    west:
      typeof source.west === 'number'
        ? source.west
        : typeof source.waistCm === 'number'
          ? source.waistCm
          : 0,
    hip: typeof source.hip === 'number' ? source.hip : 0,
    type: Array.isArray(source.type)
      ? source.type.filter((item): item is string => typeof item === 'string')
      : undefined,
    shopId: typeof source.shopId === 'string' ? source.shopId : shop,
    shopName: typeof source.shopName === 'string' ? source.shopName : '',
    realTimeStatus:
      typeof source.realTimeStatus === 'number' ? source.realTimeStatus : 0,
    realTimeDetail:
      typeof source.realTimeDetail === 'string' ? source.realTimeDetail : '',
    realTimeComment:
      typeof source.realTimeComment === 'string' ? source.realTimeComment : '',
    startTime: isNonEmptyString(source.startTime) ? source.startTime : undefined,
    endTime: isNonEmptyString(source.endTime) ? source.endTime : undefined,
    timeSlots: normalizeTimeSlots(source.timeSlots),
    scheduleStatus: isNonEmptyString(source.scheduleStatus)
      ? source.scheduleStatus
      : undefined,
    gradeId: typeof source.gradeId === 'number' ? source.gradeId : 0,
    ranking: typeof source.ranking === 'number' ? source.ranking : undefined,
    serviceHealth:
      typeof source.serviceHealth === 'boolean'
        ? source.serviceHealth
        : undefined,
    serviceMat:
      typeof source.serviceMat === 'boolean' ? source.serviceMat : undefined,
    badgeType: normalizeBadgeType(source.badgeType),
    shopIconRank:
      typeof source.shopIconRank === 'number' ? source.shopIconRank : null,
    areaIconRank:
      typeof source.areaIconRank === 'number' ? source.areaIconRank : null,
    ratings: Array.isArray(source.ratings) ? source.ratings : undefined,
    blogUrl: typeof source.blogUrl === 'string' ? source.blogUrl : undefined,
    reviewUrl:
      typeof source.reviewUrl === 'string' ? source.reviewUrl : undefined,
    reserveUrl:
      typeof source.reserveUrl === 'string' ? source.reserveUrl : undefined,
    photoBlogUrl:
      typeof source.photoBlogUrl === 'string'
        ? source.photoBlogUrl
        : undefined,
    reservationUrl:
      typeof source.reservationUrl === 'string'
        ? source.reservationUrl
        : undefined,
    telop: typeof source.telop === 'string' ? source.telop : undefined,
    castMessage:
      typeof source.castMessage === 'string' ? source.castMessage : undefined,
    shopComment:
      typeof source.shopComment === 'string' ? source.shopComment : undefined,
    questions: Array.isArray(source.questions) ? source.questions : undefined,
    recommendations: Array.isArray(source.recommendations)
      ? source.recommendations.filter(
          (item): item is { shopId: string; castId: string } =>
            typeof item === 'object' &&
            item !== null &&
            typeof item.shopId === 'string' &&
            typeof item.castId === 'string'
        )
      : undefined,
    joinedDate:
      typeof source.joinedDate === 'string' ? source.joinedDate : undefined,
    rankID: typeof source.rankID === 'string' ? source.rankID : undefined,
    rank: typeof source.rank === 'number' ? source.rank : undefined,
  };
}
