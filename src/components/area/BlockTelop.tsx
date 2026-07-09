/* =======================================
 * 横浜ダンディーグループ テロップ
 * URL: /src/components/area/BlockTelop.tsx
 * Referenced in: /src/app/top/page.tsx
 * Created: 2025-08-19
 * Last updated: 2026-07-09
 * ======================================= */
'use client';
import { useEffect, useState } from 'react';
import styles from '@/styles/AreaTop.module.scss';

const BlockTelop = () => {
  const [telop, setTelop] = useState<string>('');

  useEffect(() => {
    const fetchTelopData = async () => {
      try {
        // キャッシュバスティング用のタイムスタンプを追加
        const timestamp =
          process.env.NODE_ENV === 'development' ? Date.now() : '';
        const dataPath = `/db/contents/area/areaTopTelop.json${timestamp ? `?t=${timestamp}` : ''}`;

        const response = await fetch(dataPath);
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        setTelop(data.telopComment);
      } catch {
        // エラー時の処理（何もしない）
      }
    };

    fetchTelopData();
  }, []);

  return (
    <article className={styles.blockTelop}>
      <p>{telop}</p>
    </article>
  );
};

export default BlockTelop;
