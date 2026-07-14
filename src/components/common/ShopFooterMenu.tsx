'use client';
/* =======================================
 *店舗 FOOTER MENU
 * URL: src/components/common/ShopFooterMenu.tsx
 * Created: 2025-08-21
 * Last updated: 2025-09-11
 * ======================================= */
import styles from '@/styles/ShopCommon.module.scss';
import clsx from 'clsx';
import Link from 'next/link';
import ExternalLink from '@/components/common/ExternalLink';
import { usePathname } from 'next/navigation';
import { getShopFromPath, getStoreClass } from '@/lib/shopUtils';
type NavItem = {
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
  return (
    <section className={clsx(styles.shopFooterMenu, styles[activeStoreClass])}>
      <h2>menu</h2>
      <nav>
        {navMenu.map((item, index) =>
          item.target ? (
            <ExternalLink key={index} href={item.href}>
              {item.label}
            </ExternalLink>
          ) : (
            <Link
              key={index}
              href={item.href}
              className={clsx(pathname === item.href && styles.active)}
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
