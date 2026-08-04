import type { Metadata } from 'next';
import { isRealProduction } from '@/lib/env';

export function withCanonical(
  canonicalPath: string,
  metadata: Metadata
): Metadata {
  if (!isRealProduction) {
    return metadata;
  }

  return {
    ...metadata,
    alternates: {
      ...metadata.alternates,
      canonical: canonicalPath,
    },
  };
}
