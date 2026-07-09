/* =======================================
 * 横浜ダンディーグループ Footer
 * URL: /src/components/common/Footer.tsx
 * Referenced in: /src/app/top/page.tsx
 * Created: 2025-08-19
 * Last updated: 2026-07-09
 * ======================================= */

import styles from './Footer.module.scss';
import ExternalLink from '@/components/common/ExternalLink';
import { useMemo } from 'react';
import clsx from 'clsx';

type FooterProps = {
  className?: string;
};

const Footer = ({ className }: FooterProps) => {
  const areaData = useMemo(
    () => [
      {
        nameJp: '横浜',
        nameEn: 'yokohama',
        url: 'https://www.dandy-g.jp/top.html',
        shopColor: '#f0c4fb',
      },
      {
        nameJp: '京都',
        nameEn: 'kyoto',
        url: 'https://www.hot-point.co.jp/top.html',
        shopColor: '#c6ecc8',
      },
      {
        nameJp: '神戸',
        nameEn: 'kobe',
        url: 'http://www.hpg-kobe.jp/',
        shopColor: '#c4ddfb',
      },
      {
        nameJp: '福岡',
        nameEn: 'fukuoka',
        url: 'https://www.fukuoka-hotpoint.jp/top.html',
        shopColor: '#cfcfcf',
      },
      {
        nameJp: '熊本',
        nameEn: 'kumamoto',
        url: 'https://www.hotpoint.cc/top.html',
        shopColor: '#f9d5c0',
      },
    ],
    []
  );

  return (
    <footer className={clsx(styles.containerFooter, className)}>
      <nav className={styles.listArea}>
        {areaData.map((item, index) => (
          <ExternalLink
            key={index}
            href={item.url}
            className={styles.itemLink}
            style={{ '--shop-color': item.shopColor } as React.CSSProperties}
          >
            <span className={styles.nameEn}>{item.nameEn} area</span>
            <span className={styles.nameJp}>{item.nameJp}エリア</span>
          </ExternalLink>
        ))}
      </nav>
      <div className={styles.copyRight}>
        画像及び記事の無断使用・転用は固くお断り致します。
        <br className="sp" />
        <span>(C)&(P)横浜ダンディーグループ</span>
      </div>
    </footer>
  );
};

export default Footer;
