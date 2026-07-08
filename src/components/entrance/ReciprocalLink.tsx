/* =======================================
 * 横浜ダンディーグループ 相互リンク一覧
 * URL: /src/components/entrance/ReciprocalLink.tsx
 * Referenced in: /src/components/entrance/Entrance.tsx
 * Created: 2026-07-08
 * Last updated: 2026-07-08
 * ======================================= */
'use client';

import { useState, useEffect, useCallback } from 'react';
import { usePathname } from 'next/navigation';
import Image from 'next/image';
import styles from '@/components/entrance/Entrance.module.scss';
import clsx from 'clsx';
// 相互リンクの型定義
type ReciprocalLinkItem = {
  banId: string;
  status: boolean;
  name: string;
  url?: string;
  thumbnail?: string;
  body?: string;
};

type ReciprocalLinkBlock = {
  blockId: string;
  status: boolean;
  list: ReciprocalLinkItem[];
};

const ReciprocalLink = () => {
  const pathname = usePathname();
  const [reciprocalData, setReciprocalData] = useState<ReciprocalLinkBlock[]>(
    []
  );
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const getDataPath = useCallback(() => {
    let basePath = '/db/contents/group/ReciprocalLink.json';

    if (pathname.startsWith('/dandy')) {
      basePath = '/db/contents/dandy/ReciprocalLink.json';
    } else if (pathname.startsWith('/mr_dandy')) {
      basePath = '/db/contents/mr_dandy/ReciprocalLink.json';
    } else if (pathname.startsWith('/club_dandy')) {
      basePath = '/db/contents/club_dandy/ReciprocalLink.json';
    }

    // キャッシュバスティング用のタイムスタンプを追加
    const timestamp = Date.now();
    return `${basePath}?t=${timestamp}`;
  }, [pathname]);

  useEffect(() => {
    const fetchReciprocalLinks = async () => {
      try {
        const dataPath = getDataPath();

        const response = await fetch(dataPath);

        if (!response.ok) {
          // 404エラーの場合はデフォルトファイルを試行
          if (
            response.status === 404 &&
            !dataPath.startsWith('/db/contents/group/ReciprocalLink.json')
          ) {
            const fallbackResponse = await fetch(
              '/db/contents/group/ReciprocalLink.json'
            );
            if (!fallbackResponse.ok) {
              throw new Error(
                `デフォルトファイルも見つかりません: ${fallbackResponse.status}`
              );
            }
            const fallbackText = await fallbackResponse.text();
            const fallbackData: ReciprocalLinkBlock[] =
              JSON.parse(fallbackText);
            const activeBlocks = fallbackData.filter((block) => block.status);
            setReciprocalData(activeBlocks);
            return;
          }
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const text = await response.text();
        const data: ReciprocalLinkBlock[] = JSON.parse(text);
        const activeBlocks = data.filter((block) => block.status);
        setReciprocalData(activeBlocks);
      } catch {
        setError('相互リンクデータの取得に失敗しました');
      } finally {
        setLoading(false);
      }
    };

    fetchReciprocalLinks();
  }, [getDataPath]);

  const renderLinkItem = (item: ReciprocalLinkItem) => {
    // statusがfalseの場合は非表示
    if (!item.status) return null;

    // bodyがある場合はHTMLをそのまま表示
    if (item.body) {
      return (
        <li
          key={item.banId}
          className={styles.reciprocalItem}
          dangerouslySetInnerHTML={{ __html: item.body }}
        />
      );
    }

    // 通常のリンク表示
    if (item.url) {
      return (
        <li key={item.banId} className={styles.reciprocalItem}>
          <li className={styles.reciprocalLink}>
            {item.thumbnail && (
              <Image
                src={item.thumbnail}
                alt={item.name}
                width={236}
                height={68}
                className={styles.reciprocalThumbnail}
              />
            )}
          </li>
        </li>
      );
    }

    return null;
  };

  if (loading) {
    return (
      <article className={styles.boxReciprocalLink}>
        <div className={styles.loading}>読み込み中...</div>
      </article>
    );
  }

  if (error) {
    return (
      <article className={styles.boxReciprocalLink}>
        <div className={styles.error}>エラー: {error}</div>
      </article>
    );
  }

  return (
    <article className={styles.boxReciprocalLink}>
      {reciprocalData.map((block) => (
        <ul
          key={block.blockId}
          className={clsx(
            styles.reciprocalBlock,
            styles[`reciprocalBlock--${block.blockId}`]
          )}
        >
          {block.list.map(renderLinkItem)}
        </ul>
      ))}
    </article>
  );
};

export default ReciprocalLink;
