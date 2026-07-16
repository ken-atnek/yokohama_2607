'use client';

import { useEffect, useState } from 'react';

const SvgDefs = () => {
  const [svgContent, setSvgContent] = useState('');

  useEffect(() => {
    fetch('/svg/object.svg')
      .then((res) => res.text())
      .then((text) => setSvgContent(text));
  }, []);

  return (
    <div
      style={{ display: 'none' }}
      dangerouslySetInnerHTML={{ __html: svgContent }}
      aria-hidden="true"
    />
  );
};

export default SvgDefs;
