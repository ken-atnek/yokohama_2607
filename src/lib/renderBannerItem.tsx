/* =======================================
 * 共通バナーレンダリングエンジン
 * - バナー表示（画像 / 内部リンク / 外部リンク / モーダル）を status に応じて切替
 * - JSON からの読み込み処理も含め共通化
 *
 * URL: src/lib/renderBannerItem.tsx
 * Used in: BannerGroup.tsx / ShopTopMain.tsx など
 * Created: 2025-08-22
 * Last updated: 2025-08-22
 * ======================================= */
'use client';
import Image from 'next/image';
import Link from 'next/link';
import styles from '@/styles/ShopCommon.module.scss';
import ExternalLink from '@/components/common/ExternalLink';
import { ReactNode, useEffect, useState } from 'react';
export type BannerItem = {
  banId: string;
  status: number;
  banTitle: string;
  banImage: string;
  url?: string;
  linkTarget?: boolean;
  popUpImage?: string;
};

export const renderBannerItem = (
  item: BannerItem,
  setModalImage: (url: string) => void,
  isPriority?: boolean
): ReactNode => {
  switch (item.status) {
    case 1:
      return (
        <div className={styles.itemBan}>
          <Image
            src={item.banImage}
            alt={item.banTitle}
            width={325}
            height={119}
            {...(isPriority ? { priority: true } : {})}
          />
        </div>
      );
    case 2:
      return (
        <Link href={item.url!} className={styles.itemBan}>
          <Image
            src={item.banImage}
            alt={item.banTitle}
            width={325}
            height={119}
            {...(isPriority ? { priority: true } : {})}
          />
        </Link>
      );
    case 3:
      return (
        <ExternalLink href={item.url} className={styles.itemBan}>
          <Image
            src={item.banImage}
            alt={item.banTitle}
            width={325}
            height={119}
            {...(isPriority ? { priority: true } : {})}
          />
        </ExternalLink>
      );
    case 4:
      return (
        <button
          onClick={() => setModalImage(item.popUpImage!)}
          className={styles.itemBan}
          style={{ all: 'unset', cursor: 'pointer' }}
        >
          <Image
            src={item.banImage}
            alt={item.banTitle}
            width={325}
            height={119}
            {...(isPriority ? { priority: true } : {})}
          />
        </button>
      );
    default:
      return null;
  }
};

export const useBannerItems = (
  jsonPath: string
): [BannerItem[], (url: string | null) => void, string | null] => {
  const [items, setItems] = useState<BannerItem[]>([]);
  const [modalImage, setModalImage] = useState<string | null>(null);

  useEffect(() => {
    const fetchBannerData = async () => {
      try {
        // キャッシュバスティング用のタイムスタンプを追加
        const timestamp =
          process.env.NODE_ENV === 'development' ? Date.now() : '';
        const dataPath = `${jsonPath}${timestamp ? `?t=${timestamp}` : ''}`;

        const response = await fetch(dataPath);
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data: BannerItem[] = await response.json();

        // statusが1-4のアクティブなアイテムのみフィルタリング
        const activeItems = data.filter(
          (item) => item.status >= 1 && item.status <= 4
        );
        setItems(activeItems);
      } catch {
        // エラー時の処理（何もしない）
      }
    };

    fetchBannerData();
  }, [jsonPath]);

  return [items, setModalImage, modalImage];
};
