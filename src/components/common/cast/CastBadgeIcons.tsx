/* =======================================
 * 横浜ダンディーグループ キャストバッジアイコン
 * URL: /src/components/common/cast/CastBadgeIcons.tsx
 * Referenced in: /src/components/common/cast/ShopItemCastList.tsx
 * Created: 2026-07-16
 * Last updated: 2026-07-17
 * ======================================= */
import Image from 'next/image';
import type { CastDetail } from '@/types/CastDetails';

type Props = Pick<CastDetail, 'badgeType' | 'shopIconRank' | 'areaIconRank'> & {
  wrapperClassName?: string;
  imageClassName?: string;
};

const badgeTypeImageMap: Record<
  NonNullable<CastDetail['badgeType']>,
  string | null
> = {
  none: null,
  new: '/images/common/icon-new-face.webp',
  new_kirakira: '/images/common/icon-new-face.webp',
  trial: '/images/common/icon-trial.webp',
  osusume: '/images/common/icon-recommend.webp',
  event: null,
};

export default function CastBadgeIcons({
  badgeType,
  shopIconRank,
  areaIconRank,
  wrapperClassName,
  imageClassName,
}: Props) {
  const badgeTypeImage = badgeType ? badgeTypeImageMap[badgeType] : null;
  const shopIconRankImage =
    shopIconRank && shopIconRank >= 1
      ? `/images/common/list-ranking/shop/${String(shopIconRank).padStart(2, '0')}.webp`
      : null;
  const areaIconRankImage =
    areaIconRank && areaIconRank >= 1
      ? `/images/common/list-ranking/area/${String(areaIconRank).padStart(2, '0')}.webp`
      : null;

  return (
    <div className={wrapperClassName}>
      {badgeTypeImage ? (
        <Image
          className={imageClassName}
          src={badgeTypeImage}
          alt={badgeType ?? ''}
          width={80}
          height={58}
        />
      ) : null}
      {shopIconRankImage ? (
        <Image
          className={imageClassName}
          src={shopIconRankImage}
          alt={`店舗アイコンランキング ${shopIconRank}位`}
          width={80}
          height={58}
        />
      ) : null}
      {areaIconRankImage ? (
        <Image
          className={imageClassName}
          src={areaIconRankImage}
          alt={`エリアアイコンランキング ${areaIconRank}位`}
          width={80}
          height={58}
        />
      ) : null}
    </div>
  );
}
