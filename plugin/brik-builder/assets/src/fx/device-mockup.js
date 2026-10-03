// Device mockup: pointer tilt on hover and an off-screen pause for the float animation.
import { on, reducedMotion } from './_api.js';
import { canHover, hoverTilt, pauseOffscreen } from './_3d.js';

on('.brik-dm', (el) => {
  pauseOffscreen(el);
  const tilt = el.querySelector('.brik-dm-tilt');
  if (!tilt || !el.hasAttribute('data-brik-tilt') || reducedMotion() || !canHover()) return;
  hoverTilt(el, (x, y) => {
    tilt.style.transform = `rotateY(${(x * 9).toFixed(2)}deg) rotateX(${(-y * 7).toFixed(2)}deg)`;
    tilt.style.setProperty('--gx', `${Math.round(50 + x * 40)}%`);
    tilt.style.setProperty('--gy', `${Math.round(50 + y * 40)}%`);
  });
});
