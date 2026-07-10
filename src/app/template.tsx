/* =======================================
 * 横浜ダンディーグループ 共通テンプレート
 * URL: /src/app/template.tsx
 * Referenced in: /src/app/layout.tsx
 * Created: 2026-07-08
 * Last updated: 2026-07-08
 * ======================================= */

'use client';

import { useLayoutEffect } from 'react';
import { usePathname } from 'next/navigation';

export default function Template({
  children,
}: {
  children: React.ReactNode;
}) {
  const pathname = usePathname();

  useLayoutEffect(() => {
    if ('scrollRestoration' in history) {
      history.scrollRestoration = 'manual';
    }

    const hash = window.location.hash;

    if (hash) {
      const id = hash.slice(1);

      requestAnimationFrame(() => {
        const element = document.getElementById(id);

        if (element) {
          element.scrollIntoView({ behavior: 'auto' });
          return;
        }

        setTimeout(() => {
          document.getElementById(id)?.scrollIntoView({ behavior: 'auto' });
        }, 100);
      });

      return;
    }

    window.scrollTo({ top: 0, left: 0, behavior: 'auto' });

    requestAnimationFrame(() => {
      window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
    });
  }, [pathname]);

  return <main>{children}</main>;
}
