// Animated beams: SVG paths from each side icon to the hub, redrawn on resize.
import { on, reducedMotion } from './_api.js';

const NS = 'http://www.w3.org/2000/svg';
let uid = 0;

const make = (tag, attrs) => {
  const el = document.createElementNS(NS, tag);
  for (const k in attrs) el.setAttribute(k, attrs[k]);
  return el;
};

on('.brik-beam', (root) => {
  const svg = root.querySelector('.brik-beam-svg');
  const core = root.querySelector('.brik-beam-core');
  if (!svg || !core) return;
  const id = `brik-beam-${++uid}`;
  const flow = root.dataset.flow || 'through';
  const curve = (parseFloat(root.dataset.curve) || 0) / 100;
  const still = reducedMotion();

  const draw = () => {
    const box = root.getBoundingClientRect();
    if (!box.width) return;
    svg.setAttribute('viewBox', `0 0 ${box.width} ${box.height}`);
    svg.replaceChildren();
    const defs = make('defs', {});
    svg.appendChild(defs);

    const c = core.getBoundingClientRect();
    const hub = { x: c.left - box.left + c.width / 2, y: c.top - box.top + c.height / 2, r: c.width / 2 };
    const nodes = [
      ...[...root.querySelectorAll('.brik-beam-col--left .brik-beam-node')].map((n) => [n, 'left']),
      ...[...root.querySelectorAll('.brik-beam-col--right .brik-beam-node')].map((n) => [n, 'right']),
    ];

    nodes.forEach(([node, side], i) => {
      const r = node.getBoundingClientRect();
      const nx = r.left - box.left + r.width / 2;
      const ny = r.top - box.top + r.height / 2;
      // Start at the icon edge facing the hub and end at the hub edge.
      const sx = side === 'left' ? nx + r.width / 2 : nx - r.width / 2;
      const ex = side === 'left' ? hub.x - hub.r : hub.x + hub.r;
      const ey = hub.y + (ny - hub.y) * 0.18;
      const mid = (sx + ex) / 2;
      const bend = (ny - ey) * curve * 0.35;
      const d = `M ${sx.toFixed(1)} ${ny.toFixed(1)} C ${mid.toFixed(1)} ${(ny - bend).toFixed(1)}, ${mid.toFixed(1)} ${(ey + bend).toFixed(1)}, ${ex.toFixed(1)} ${ey.toFixed(1)}`;

      // Gradient runs along the beam's direction of travel.
      const toHub = flow === 'inward' || (flow === 'through' && side === 'left');
      const gid = `${id}-g${i}`;
      const grad = make('linearGradient', {
        id: gid,
        gradientUnits: 'userSpaceOnUse',
        x1: toHub ? sx : ex,
        y1: toHub ? ny : ey,
        x2: toHub ? ex : sx,
        y2: toHub ? ey : ny,
      });
      grad.append(
        // Custom properties only work through style, not presentation attributes.
        make('stop', { offset: '0', style: 'stop-color:var(--brik-beam-g1);stop-opacity:0' }),
        make('stop', { offset: '0.35', style: 'stop-color:var(--brik-beam-g1)' }),
        make('stop', { offset: '1', style: 'stop-color:var(--brik-beam-g2)' })
      );
      defs.appendChild(grad);

      svg.appendChild(make('path', { d, class: 'brik-beam-track' }));
      if (still) return;
      const beam = make('path', {
        d,
        class: `brik-beam-light${toHub ? '' : ' is-reverse'}`,
        pathLength: '100',
        stroke: `url(#${gid})`,
      });
      beam.style.animationDelay = `calc(var(--brik-beam-dur) * ${((i * 0.37) % 1).toFixed(2)} * -1)`;
      svg.appendChild(beam);
    });
  };

  let raf = 0;
  const schedule = () => {
    cancelAnimationFrame(raf);
    raf = requestAnimationFrame(draw);
  };
  new ResizeObserver(schedule).observe(root);
  root.querySelectorAll('img').forEach((img) => img.addEventListener('load', schedule));
  if (document.fonts) document.fonts.ready.then(schedule);
  draw();
});
