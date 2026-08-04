import type { MetadataRoute } from 'next';
import { isRealProduction, metadataBase } from '@/lib/env';

export const dynamic = 'force-static';

const staticPaths = [
  '/',
  '/top/',
  '/dandy/',
  '/dandy/top/',
  '/dandy/cast/',
  '/dandy/oneday_ranking/',
  '/dandy/realtime/',
  '/dandy/schedule/',
  '/dandy/system/',
  '/dandy/weekly-schedule/',
  '/mr_dandy/',
  '/mr_dandy/top/',
  '/mr_dandy/cast/',
  '/mr_dandy/oneday_ranking/',
  '/mr_dandy/realtime/',
  '/mr_dandy/schedule/',
  '/mr_dandy/system/',
  '/mr_dandy/weekly-schedule/',
  '/club_dandy/',
  '/club_dandy/top/',
  '/club_dandy/cast/',
  '/club_dandy/oneday_ranking/',
  '/club_dandy/realtime/',
  '/club_dandy/schedule/',
  '/club_dandy/system/',
  '/club_dandy/weekly-schedule/',
] as const;

export default function sitemap(): MetadataRoute.Sitemap {
  if (!isRealProduction) {
    return [];
  }

  const lastModified = new Date();

  return staticPaths.map((path) => ({
    url: new URL(path, metadataBase).toString(),
    lastModified,
  }));
}
