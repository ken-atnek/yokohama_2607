/* =======================================
 * 横浜ダンディーグループ Splide 型補完
 * URL: /src/types/splide.d.ts
 * Referenced in: /src/components/area/BlockPickUp.tsx
 * Created: 2026-07-09
 * Last updated: 2026-07-09
 * ======================================= */

declare module '@splidejs/react-splide' {
  import type { ComponentType, CSSProperties, PropsWithChildren } from 'react';
  import type { Options } from '@splidejs/splide';

  export type { Options };

  export type SplideProps = PropsWithChildren<{
    options?: Options;
    className?: string;
    hasTrack?: boolean;
    tag?: string;
    'aria-label'?: string;
    'aria-labelledby'?: string;
  }>;

  export type SplideSlideProps = PropsWithChildren<{
    className?: string;
    style?: CSSProperties;
  }>;

  export const Splide: ComponentType<SplideProps>;
  export const SplideSlide: ComponentType<SplideSlideProps>;
}
