/* =======================================
 * 横浜ダンディーグループ BannerGroup
 * URL: /src/components/common/BannerGroup.tsx
 * Referenced in: /src/app/top/page.tsx
 * Created: 2025-08-20
 * Last updated: 2026-07-09
 * ======================================= */
'use client';

import { renderBannerItem, useBannerItems } from '@/components/common/renderBannerItem';
import styles from './BannerGroup.module.scss';
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
          <div className={styles.modalOverlay} onClick={() => setModalImage(null)}>
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
