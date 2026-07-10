export const convertRemToPx = (html: string, basePx = 12): string => {
  return html.replace(/(\d*\.?\d+)rem/g, (_, rem) => {
    const px = parseFloat(rem) * basePx;
    return `${px}px`;
  });
};
