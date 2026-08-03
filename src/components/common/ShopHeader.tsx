'use client';
/* =======================================
 * 横浜ダンディーグループ 店舗HEADER
 * URL: /src/components/common/ShopHeader.tsx
 * Referenced in: /src/components/dandy/LayoutWrapperMain.tsx
 * Created: 2025-08-21
 * Last updated: 2026-08-01
 * ======================================= */
import { usePathname } from 'next/navigation';
import styles from './ShopHeader.module.scss';
import clsx from 'clsx';
import Link from 'next/link';
import Image from 'next/image';
import { useEffect, useState, useRef, useCallback, useMemo } from 'react';
import ExternalLink from '@/components/common/ExternalLink';
import { getShopFromPath } from '@/lib/shopUtils';
import { Shops, type Shop } from '@/data/AreaShopData';
import ScrollLink from '@/components/common/ScrollLink';

type NavItem = {
  id: string;
  href: string;
  label: string;
  labelEn: string;
  target?: boolean;
};

type HeaderProps = {
  title: string;
  navMenu: NavItem[];
  selectedNavIds?: string[]; // 表示したいナビの ID を指定（省略時は全て表示）
};

export function getShopData(shop: string): Shop | null {
  return Shops.find((shopData) => shopData.storeId === shop) || null;
}

const Header = ({ title, navMenu, selectedNavIds }: HeaderProps) => {
  const pathname = usePathname();
  const pathShop = getShopFromPath(pathname);
  const shop = pathShop || pathname.split('/')[1] || '';
  const activeStoreClass = shop;
  const shopData = getShopData(shop);

  const filteredNavItems = useMemo(() => {
    if (!selectedNavIds || selectedNavIds.length === 0) {
      return navMenu;
    }

    return navMenu.filter((item) => selectedNavIds.includes(item.id));
  }, [navMenu, selectedNavIds]);

  const mobileHeadNavItems = useMemo(() => {
    return filteredNavItems;
  }, [filteredNavItems]);

  // navRecruitの項目を取得
  const recruitItem = useMemo(() => {
    if (mobileHeadNavItems.some((item) => item.id === 'navRecruit')) {
      return null;
    }

    return navMenu.find((item) => item.id === 'navRecruit');
  }, [mobileHeadNavItems, navMenu]);

  const [telop, setTelop] = useState<string>('');

  // ハンバーガーメニュー操作
  const [isOpen, setIsOpen] = useState(false);
  const toggleMenu = useCallback(() => setIsOpen(!isOpen), [isOpen]);
  const closeMenu = useCallback(() => setIsOpen(false), []);
  const navRef = useRef<HTMLDivElement>(null);
  const hamburgerRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (isOpen) {
      // メニューを開く時：スクロールを禁止
      document.documentElement.style.overflow = 'hidden';
      document.documentElement.style.height = '100vh';
    } else {
      // メニューを閉じる時：スタイルを復元
      document.documentElement.style.overflow = '';
      document.documentElement.style.height = '';
    }

    return () => {
      // クリーンアップ：コンポーネントがアンマウントされた時にスタイルを復元
      document.documentElement.style.overflow = '';
      document.documentElement.style.height = '';
    };
  }, [isOpen]);

  useEffect(() => {
    const handleOutsideClick = (event: MouseEvent) => {
      if (
        isOpen &&
        navRef.current &&
        hamburgerRef.current &&
        !navRef.current.contains(event.target as Node) &&
        !hamburgerRef.current.contains(event.target as Node) // ハンバーガーボタンを除外
      ) {
        closeMenu();
      }
    };
    document.addEventListener('click', handleOutsideClick, true);
    return () =>
      document.removeEventListener('click', handleOutsideClick, true);
  }, [isOpen, closeMenu]);

  useEffect(() => {
    const fetchTelopData = async () => {
      try {
        // 開発環境でのみキャッシュバスティング
        const timestamp =
          process.env.NODE_ENV === 'development' ? Date.now() : '';
        const dataPath = `/db/contents/${shop}/TopTelop.json${
          timestamp ? `?t=${timestamp}` : ''
        }`;

        const response = await fetch(dataPath);
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        setTelop(data.telopComment || ''); // undefined チェックを追加
      } catch {
        setTelop(''); // エラー時は空文字
      }
    };

    if (shop) {
      fetchTelopData();
    }
  }, [shop]);

  return (
    <header
      className={clsx(styles.containerHeader, styles[activeStoreClass])}
      // style={
      //   {
      //     '--baseColor': shopData?.shopColor ?? '#e776ad',
      //   } as CSSProperties
      // }
    >
      <article className={styles.headerTop}>
        <div className={styles.boxHead}>
          <p className={styles.areaName}>DANDY GROUP YOKOHAMA AREA</p>
          <p className={styles.pageTitle}>{title}</p>
        </div>
      </article>
      <div
        className={clsx(
          styles.boxNav,
          isOpen && styles.isOpen,
          !isOpen && styles.closing
        )}
        ref={navRef}
        onClick={(e) => {
          // オーバーレイ部分（背景）をクリックした場合のみメニューを閉じる
          if (e.target === e.currentTarget) {
            closeMenu();
          }
        }}
      >
        <div
          className={clsx(styles.mobileHamburgerHead, styles[activeStoreClass])}
        >
          <div className={styles.shopName}>
            {shopData?.logo ? (
              <Image
                src={shopData.logo}
                alt={shopData.name}
                width={240}
                height={80}
              />
            ) : null}
            <span>{shopData?.name}</span>
          </div>
          <ScrollLink
            href={`/${shop}/top/`}
            className={styles.mobileHome}
            onClick={closeMenu}
          >
            <svg>
              <use href="#mobile_home" />
            </svg>
          </ScrollLink>
        </div>
        <nav>
          {filteredNavItems.map((item, index) =>
            item.target ? (
              <ExternalLink
                key={index}
                href={item.href}
                className={styles[item.id]}
              >
                <span>
                  <i className={styles.en}>{item.labelEn}</i>
                  <i className={styles.jp}>{item.label}</i>
                </span>
              </ExternalLink>
            ) : (
              <ScrollLink
                key={index}
                href={item.href}
                onClick={closeMenu}
                className={clsx(
                  pathname === item.href && styles.active,
                  styles[item.id]
                )}
              >
                <span>
                  <i className={styles.en}>{item.labelEn}</i>
                  <i className={styles.jp}>{item.label}</i>
                </span>
              </ScrollLink>
            )
          )}
          <ExternalLink
            className={clsx(styles.mobileOnly, styles.linkTel)}
            href={`tel:${shopData?.phone || ''}`}
          >
            <span>{shopData?.phone}</span>
          </ExternalLink>
          <ExternalLink
            className={clsx(styles.mobileOnly, styles.linkMap)}
            href={shopData?.mapUrl}
          >
            <span>
              <i className={styles.en}>map</i>
              <i className={styles.jp}>地図</i>
            </span>
          </ExternalLink>
        </nav>
      </div>
      <hr className={styles.boxStripe} />
      <div className={styles.boxTelop}>
        <div className={styles.innerTelop}>
          <p>{telop}</p>
        </div>
      </div>
      <button
        ref={hamburgerRef}
        type="button"
        className={clsx(styles.hamburgerButton, isOpen && styles.isOpen)}
        onClick={toggleMenu}
        aria-expanded={isOpen}
        aria-label="メニューを開閉"
      >
        <span></span>
        <span></span>
        <span></span>
      </button>
      <div className={clsx(styles.mobilePageHead, styles[activeStoreClass])}>
        <div className={styles.contentsTop}>
          <div className={styles.name}>
            <span className={styles.en}>{shopData?.nameEn}</span>
            <span className={styles.jp}>{shopData?.name}</span>
          </div>
          <ExternalLink
            href={`tel:${shopData?.phone || ''}`}
            className={styles.mobilePhone}
          >
            <svg>
              <use href="#mobile_phone" />
            </svg>
          </ExternalLink>
        </div>
        <nav className={styles.mobileHeadNav}>
          {mobileHeadNavItems.map((item, index) =>
            item.target ? (
              <ExternalLink
                key={index}
                href={item.href}
                className={styles[item.id]}
              >
                {item.label}
              </ExternalLink>
            ) : (
              <Link key={index} href={item.href} className={styles[item.id]}>
                {item.label}
              </Link>
            )
          )}
        </nav>
        <div className={styles.linkRecruit}>
          {recruitItem && (
            <ExternalLink href={recruitItem.href}>
              <span>【PR】求人情報</span>
            </ExternalLink>
          )}
        </div>
      </div>
    </header>
  );
};

export default Header;
