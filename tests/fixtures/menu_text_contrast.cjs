'use strict';

// Browser-side audit of visible text on CSS colour surfaces. Artwork is reported for visual review.
module.exports = async (page, selector) => page.locator(selector).evaluate(root => {
  const rgba = value => {
    const numbers = value.match(/[\d.]+/g)?.map(Number) || [0, 0, 0, 0];
    return [...numbers.slice(0, 3), numbers[3] ?? 1];
  };
  const over = (top, bottom) => {
    const alpha = top[3] + bottom[3] * (1 - top[3]);
    return alpha ? [...top.slice(0, 3).map((channel, i) => (channel * top[3] + bottom[i] * bottom[3] * (1 - top[3])) / alpha), alpha] : [0, 0, 0, 0];
  };
  const luminance = colour => colour.slice(0, 3).map(c => c / 255).map(c => c <= .04045 ? c / 12.92 : ((c + .055) / 1.055) ** 2.4).reduce((sum, c, i) => sum + c * [.2126, .7152, .0722][i], 0);
  const contrast = (a, b) => { const x = luminance(a), y = luminance(b); return (Math.max(x, y) + .05) / (Math.min(x, y) + .05); };
  const failures = [], manual = [];
  let checked = 0;
  for (const element of root.querySelectorAll('*')) {
    if (element.closest('svg,script,style,[aria-hidden="true"]')) continue;
    const textNodes = [...element.childNodes].filter(node => node.nodeType === Node.TEXT_NODE && /[\p{L}\p{N}]/u.test(node.textContent));
    if (!textNodes.length) continue;
    const style = getComputedStyle(element);
    if (style.visibility !== 'visible' || !element.checkVisibility({ checkOpacity: true, checkVisibilityCSS: true })) continue;
    const elementRect = element.getBoundingClientRect();
    if (elementRect.width <= 1 || elementRect.height <= 1) continue;
    const range = document.createRange(); range.selectNodeContents(element);
    const rect = range.getBoundingClientRect();
    let clip = { left: 0, top: 0, right: innerWidth, bottom: innerHeight };
    for (let parent = element.parentElement; parent; parent = parent.parentElement) {
      const css = getComputedStyle(parent), bounds = parent.getBoundingClientRect();
      if (/(hidden|auto|scroll|clip)/.test(css.overflowX)) { clip.left = Math.max(clip.left, bounds.left); clip.right = Math.min(clip.right, bounds.right); }
      if (/(hidden|auto|scroll|clip)/.test(css.overflowY)) { clip.top = Math.max(clip.top, bounds.top); clip.bottom = Math.min(clip.bottom, bounds.bottom); }
    }
    if (Math.min(rect.right, clip.right) - Math.max(rect.left, clip.left) < 2 || Math.min(rect.bottom, clip.bottom) - Math.max(rect.top, clip.top) < 2) continue;
    let pairs = [[rgba(style.color), [0, 0, 0, 0]]], uncertain = false;
    for (let parent = element; parent; parent = parent.parentElement) {
      const css = getComputedStyle(parent), base = rgba(css.backgroundColor);
      let surfaces = [base];
      if (css.backgroundImage !== 'none' && pairs.some(pair => pair.some(colour => colour[3] < .999))) {
        if (css.backgroundImage.includes('url(')) { uncertain = true; break; }
        const stops = css.backgroundImage.match(/rgba?\([^)]+\)/g);
        if (!stops) { uncertain = true; break; }
        surfaces = stops.map(stop => over(rgba(stop), base));
      }
      const opacity = Number(css.opacity);
      pairs = pairs.flatMap(pair => surfaces.map(surface => pair.map(colour => {
        const result = over(colour, surface); result[3] *= opacity; return result;
      })));
    }
    const sample = { text: textNodes.map(node => node.textContent.trim()).join(' ').slice(0, 100), selector: element.tagName.toLowerCase() + (element.id ? '#' + element.id : '') + (element.className ? '.' + String(element.className).trim().replace(/\s+/g, '.') : '') };
    if (uncertain) { manual.push(sample); continue; }
    checked++;
    const ratio = Math.min(...pairs.map(pair => contrast(...pair.map(colour => over(colour, [255, 255, 255, 1])))));
    const minimum = parseFloat(style.fontSize) >= 24 || (parseFloat(style.fontSize) >= 18.66 && Number(style.fontWeight) >= 700) ? 3 : 4.5;
    if (ratio + .01 < minimum) failures.push({ ...sample, ratio: Math.round(ratio * 100) / 100, minimum, colour: style.color, background: style.backgroundColor });
  }
  return { checked, failures, manual };
});
