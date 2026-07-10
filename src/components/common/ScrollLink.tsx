'use client';

import Link, { type LinkProps } from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import type { ComponentPropsWithoutRef, MouseEvent, ReactNode } from 'react';

type AnchorProps = Omit<ComponentPropsWithoutRef<'a'>, 'href' | 'children'>;

type Props = LinkProps &
  AnchorProps & {
  children: ReactNode;
  className?: string;
  /** 同一ページ時のスクロールをスムーズにする */
  smooth?: boolean;
  /** スクロール先（基本は0でOK） */
  top?: number;
};

const normalizePathForCompare = (path: string): string => {
  if (path === '/') return '/';
  return path.replace(/\/+$/, '');
};

const getHrefParts = (
  href: LinkProps['href']
): { path: string | null; hash: string | null } => {
  if (typeof href === 'string') {
    // "/about/?a=1#sec" → path="/about/" hash="#sec"
    const [beforeHash, hashPart] = href.split('#');
    const pathOnly = (beforeHash ?? '').split('?')[0] ?? '';
    const hash = hashPart ? `#${hashPart}` : null;
    return { path: pathOnly || null, hash };
  }

  // UrlObject
  const path = href.pathname ?? null;
  const hashRaw =
    typeof href.hash === 'string' && href.hash.length > 0 ? href.hash : '';
  const hash = hashRaw
    ? hashRaw.startsWith('#')
      ? hashRaw
      : `#${hashRaw}`
    : null;

  return { path, hash };
};

const hrefToString = (href: LinkProps['href']): string => {
  if (typeof href === 'string') return href;

  const pathname = href.pathname ?? '/';

  const query = (() => {
    if (!href.query) return '';
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(href.query)) {
      if (value == null) continue;
      if (Array.isArray(value)) {
        for (const v of value) params.append(key, String(v));
      } else {
        params.append(key, String(value));
      }
    }
    return params.toString();
  })();

  const hashRaw =
    typeof href.hash === 'string' && href.hash.length > 0 ? href.hash : '';
  const hash = hashRaw
    ? hashRaw.startsWith('#')
      ? hashRaw
      : `#${hashRaw}`
    : '';

  return `${pathname}${query ? `?${query}` : ''}${hash}`;
};

export default function ScrollLink({
  href,
  children,
  className,
  smooth = true,
  top = 0,
  ...rest
}: Props) {
  const pathname = usePathname();
  const router = useRouter();

  const currentPath = normalizePathForCompare(pathname);
  const { path: targetPath, hash: targetHash } = getHrefParts(href);
  const compareTargetPath = targetPath
    ? normalizePathForCompare(targetPath)
    : null;

  const handleClick = (e: MouseEvent<HTMLAnchorElement>) => {
    // パスなし（#hashのみ）または同一ページなら「遷移」ではなく「スクロール」扱いにする
    if (!compareTargetPath || currentPath === compareTargetPath) {
      e.preventDefault();

      if (targetHash) {
        const el = document.querySelector(targetHash);
        if (el) {
          el.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto' });
          return;
        }
      }

      window.scrollTo({ top, behavior: smooth ? 'smooth' : 'auto' });
      return;
    }

    // 別ページ：遷移だけ（スクロールはいじらない）
    // Link側の自動スクロールは止めているので、必要なら template.tsx 側でトップ固定する想定
    e.preventDefault();

    // フォーカスが残るとスクロール復元がブレることがあるので消す
    if (document.activeElement instanceof HTMLElement) {
      document.activeElement.blur();
    }

    router.push(hrefToString(href));
  };

  return (
    <Link
      href={href}
      className={className}
      // Nextの自動スクロール（遷移後のscrollTo）を止めて、挙動を安定させる
      scroll={false}
      onClick={handleClick}
      {...rest}
    >
      {children}
    </Link>
  );
}
