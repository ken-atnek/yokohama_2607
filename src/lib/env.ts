export const isRealProduction =
  process.env.NEXT_PUBLIC_IS_REAL_PROD === 'true';

export const metadataBase = new URL(
  process.env.NEXT_PUBLIC_METADATA_BASE ?? 'https://www.dandy-g.jp/'
);
