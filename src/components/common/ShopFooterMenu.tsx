'use client';
/* =======================================
 * 横浜ダンディーグループ 店舗 FOOTER MENU
 * URL: /src/components/common/ShopFooterMenu.tsx
 * Referenced in: /src/components/dandy/LayoutWrapperMain.tsx
 * Created: 2025-08-21
 * Last updated: 2026-08-04
 * ======================================= */
import styles from '@/styles/ShopCommon.module.scss';
import clsx from 'clsx';
import Link from 'next/link';
import ExternalLink from '@/components/common/ExternalLink';
import { usePathname } from 'next/navigation';
import { useEffect, useMemo, useState } from 'react';
import { getShopFromPath, getStoreClass } from '@/lib/shopUtils';
type NavItem = {
  id: string;
  href: string;
  label: string;
  target?: boolean;
};

type ShopFooterMenuProps = {
  className?: string;
  navMenu: NavItem[];
};

const ShopFooterMenu = ({ navMenu }: ShopFooterMenuProps) => {
  const pathname = usePathname();
  const shop = getShopFromPath(pathname);
  const activeStoreClass = getStoreClass(shop);
  const [isSpViewport, setIsSpViewport] = useState(false);

  useEffect(() => {
    const mediaQuery = window.matchMedia(
      '(max-width: 480px) and (orientation: portrait)'
    );

    const updateViewport = () => {
      setIsSpViewport(mediaQuery.matches);
    };

    updateViewport();
    mediaQuery.addEventListener('change', updateViewport);

    return () => {
      mediaQuery.removeEventListener('change', updateViewport);
    };
  }, []);

  const displayNavMenu = useMemo(() => {
    if (!isSpViewport) {
      return navMenu;
    }

    return navMenu.filter((item) => item.id !== 'navWeeklySchedule');
  }, [isSpViewport, navMenu]);

  return (
    <section className={clsx(styles.shopFooterMenu, styles[activeStoreClass])}>
      <h2>menu</h2>
      <nav>
        {displayNavMenu.map((item, index) =>
          item.target ? (
            <ExternalLink
              key={index}
              href={item.href}
              className={styles[item.id]}
            >
              {item.label}
            </ExternalLink>
          ) : (
            <Link
              key={index}
              href={item.href}
              className={clsx(
                styles[item.id],
                pathname === item.href && styles.active
              )}
            >
              {item.label}
            </Link>
          )
        )}
      </nav>
    </section>
  );
};

export default ShopFooterMenu;
