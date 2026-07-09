/* =======================================
 * バナー表示共通コンポーネント（リスト型）
 * - エリア・店舗・イベント等のJSONデータを読み込み、バナーを表示
 * - renderBannerItem + useBannerItems を使用し構成
 *
 * URL: src/components/common/BannerGroup.tsx
 * Used in: src/app/page.tsx, ShopTopMain.tsx 他
 * Created: 2025-08-20
 * Last updated: 2025-08-22
 * ======================================= */
'use client';
import { renderBannerItem, useBannerItems } from '@/lib/renderBannerItem';
import styles from '@/styles/AreaTop.module.scss';
import clsx from 'clsx';
import Image from 'next/image';
import { createPortal } from 'react-dom';
import { useEffect, useState } from 'react';

type Props = {
  jsonPath: string;
  title?: string;
  className?: string;
};

const BannerGroup = ({ jsonPath, title, className }: Props) => {
  const [items, setModalImage, modalImage] = useBannerItems(jsonPath);
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  const modal =
    modalImage && mounted
      ? createPortal(
          <div className="modal-overlay" onClick={() => setModalImage(null)}>
            <div className={styles.modalContent}>
              <Image
                src={modalImage}
                alt="ポップアップ画像"
                width={650}
                height={238}
              />
            </div>
          </div>,
          document.body
        )
      : null;

  return (
    <>
      <article className={clsx(styles.boxBanGroup, className)}>
        {title && <h3>{title}</h3>}
        <ul>
          {items.map((item) => (
            <li key={item.banId}>{renderBannerItem(item, setModalImage)}</li>
          ))}
        </ul>
      </article>
      {modal}
    </>
  );
};

export default BannerGroup;
