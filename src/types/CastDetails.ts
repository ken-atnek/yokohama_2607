export type RatingItem = {
  label: string;
  score: number;
};

export type QAItem = {
  question: string;
  answer: string;
};

export type CastDetail = {
  rankID?: string;
  rank?: number;
  castId: string;
  castName: string;
  castNameEn: string;
  castImage: string;
  movie?: string;
  moviePosterImage?: string;
  castImageSquare?: string;
  profileImages: string[];
  age: number | null;
  ageText?: string;
  tall: number;
  bust: number;
  cup: string;
  west: number;
  hip: number;
  shopId: string;
  shopName: string;
  realTimeStatus: number;
  realTimeDetail: string;
  realTimeComment: string;
  startTime?: string;
  endTime?: string;
  scheduleStatus?: string;
  gradeId: number;
  ranking?: number;
  serviceHealth?: boolean;
  serviceMat?: boolean;
  badgeType?: 'none' | 'new' | 'new_kirakira' | 'trial' | 'osusume' | 'event';
  shopIconRank?: number | null;
  areaIconRank?: number | null;
  ratings?: RatingItem[];
  photoBlogUrl?: string;
  reservationUrl?: string;
  castMessage?: string;
  shopComment?: string;
  questions?: QAItem[];
};
