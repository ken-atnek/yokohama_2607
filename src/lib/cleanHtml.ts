export const convertFontToSpan = (html: string): string => {
  return html.replace(
    /<font color="(#[0-9a-fA-F]{6})">([\s\S]*?)<\/font>/g,
    (_, color, content) => {
      return `<span style="color: ${color}; font-size: 14px;">${content}</span>`;
    }
  );
};
