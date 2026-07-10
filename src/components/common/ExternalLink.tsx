/* =======================================
 * ふもと旅館 ExternalLink
 * URL: /src/components/common/ExternalLink.tsx
 * Referenced in: /src/components/common/Footer.tsx
 * Created: 2026-06-09
 * Last updated: 2026-06-30
 * ======================================= */

import type { AnchorHTMLAttributes, ReactNode } from 'react';

type Props = AnchorHTMLAttributes<HTMLAnchorElement> & {
  children: ReactNode;
};

export default function ExternalLink({ children, ...props }: Props) {
  return (
    <a target="_blank" rel="noopener noreferrer" {...props}>
      {children}
    </a>
  );
}
