// Dotted globe. Points live on a unit sphere and are rotated in the vertex shader, so a frame
// is one draw call per layer no matter how many dots there are. Without WebGL the same point
// lists are projected on the CPU and drawn to a 2D canvas.
import { on, loop, fitCanvas, reducedMotion } from './_api.js';
import { clamp, data, isSmall, landMask, onThemeChange, rgba, sphere } from './_3d.js';

const VERT = `
attribute vec3 aPos;
attribute vec4 aColor;
attribute vec2 aData;
uniform mat3 uRot;
uniform float uScale;
uniform float uPx;
uniform float uTime;
uniform float uMode;
uniform float uStill;
uniform vec4 uDot;
varying vec4 vColor;
varying float vRing;
void main() {
  vec3 p = uRot * aPos;
  vRing = uMode > 2.5 ? 1.0 : 0.0;
  gl_Position = vec4(p.xy * uScale, 0.0, 1.0);
  float front = smoothstep(-0.08, 0.3, p.z);
  float hidden = (p.z < 0.0 && dot(p.xy, p.xy) < 1.0) ? 1.0 : 0.0;
  float a = 1.0;
  float size = uPx;
  vec3 rgb = aColor.rgb;
  if (uMode < 0.5) {
    rgb = uDot.rgb;
    a = uDot.a * aColor.a * mix(0.07, 1.0, front) * (0.5 + 0.5 * clamp(p.z, 0.0, 1.0));
    size = uPx * (0.7 + 0.4 * clamp(p.z, 0.0, 1.0));
  } else if (uMode < 1.5) {
    float head = fract(uTime * 0.16 + aData.y) * 1.8;
    float trail = smoothstep(head - 0.5, head, aData.x) * step(aData.x, head);
    a = mix(max(0.3, trail), 0.9, uStill) * (1.0 - hidden * 0.94);
    size = uPx * mix(1.0, 1.6, trail);
  } else if (uMode < 2.5) {
    a = 1.0 - hidden * 0.9;
    size = uPx * 2.6;
  } else {
    float k = fract(uTime * 0.5 + aData.y);
    a = (1.0 - k) * 0.7 * (1.0 - hidden) * (1.0 - uStill);
    size = uPx * (3.0 + 9.0 * k);
  }
  vColor = vec4(rgb, a);
  gl_PointSize = size;
}`;

const FRAG = `
precision mediump float;
varying vec4 vColor;
varying float vRing;
void main() {
  float d = length(gl_PointCoord - 0.5);
  float a = vRing > 0.5 ? smoothstep(0.5, 0.42, d) * smoothstep(0.3, 0.4, d) : smoothstep(0.5, 0.3, d);
  if (a * vColor.a < 0.004) discard;
  gl_FragColor = vec4(vColor.rgb, vColor.a * a);
}`;

const SCALE = 0.84; // globe radius as a share of half the canvas; the CSS glow ring matches it

// Fibonacci sphere: evenly spread points, kept where the mask says land (and faintly on water).
function buildDots(mask, count, ocean) {
  const pos = [];
  const alpha = [];
  const golden = Math.PI * (3 - Math.sqrt(5));
  for (let i = 0; i < count; i++) {
    const y = 1 - (2 * (i + 0.5)) / count;
    const r = Math.sqrt(1 - y * y);
    const t = golden * i;
    const x = Math.cos(t) * r;
    const z = Math.sin(t) * r;
    const land = mask((Math.asin(y) * 180) / Math.PI, (Math.atan2(x, z) * 180) / Math.PI);
    if (land || (ocean && i % 3 === 0)) {
      pos.push(x, y, z);
      alpha.push(land ? 1 : 0.22);
    }
  }
  return { pos: new Float32Array(pos), alpha };
}

// Great-circle arcs lifted off the surface, sampled densely enough to read as lines.
function buildArcs(routes, colors) {
  const pos = [];
  const col = [];
  const dat = [];
  routes.forEach((route, n) => {
    const a = sphere(route.from[0], route.from[1]);
    const b = sphere(route.to[0], route.to[1]);
    const cos = clamp(a[0] * b[0] + a[1] * b[1] + a[2] * b[2], -1, 1);
    const omega = Math.acos(cos);
    if (omega < 0.001) return;
    const steps = Math.max(40, Math.round(omega * 110));
    const lift = 0.06 + 0.22 * (omega / Math.PI);
    const c1 = route.c1 || colors[0];
    const c2 = route.c2 || colors[1];
    const phase = (n * 0.37) % 1;
    for (let i = 0; i <= steps; i++) {
      const t = i / steps;
      const s1 = Math.sin((1 - t) * omega) / Math.sin(omega);
      const s2 = Math.sin(t * omega) / Math.sin(omega);
      const h = 1 + lift * Math.sin(Math.PI * t);
      pos.push((a[0] * s1 + b[0] * s2) * h, (a[1] * s1 + b[1] * s2) * h, (a[2] * s1 + b[2] * s2) * h);
      col.push(c1[0] + (c2[0] - c1[0]) * t, c1[1] + (c2[1] - c1[1]) * t, c1[2] + (c2[2] - c1[2]) * t, 1);
      dat.push(t, phase);
    }
  });
  return { pos: new Float32Array(pos), col: new Float32Array(col), dat: new Float32Array(dat) };
}

function buildMarkers(routes, colors) {
  const seen = new Set();
  const pos = [];
  const col = [];
  const dat = [];
  routes.forEach((route) => {
    [
      [route.from, route.c1 || colors[0]],
      [route.to, route.c2 || colors[1]],
    ].forEach(([p, c]) => {
      const key = p.join(',');
      if (seen.has(key)) return;
      seen.add(key);
      pos.push(...sphere(p[0], p[1]).map((v) => v * 1.005));
      col.push(c[0], c[1], c[2], 1);
      dat.push(0, (seen.size * 0.29) % 1);
    });
  });
  return { pos: new Float32Array(pos), col: new Float32Array(col), dat: new Float32Array(dat) };
}

function rotation(yaw, pitch) {
  const c = Math.cos(yaw);
  const s = Math.sin(yaw);
  const cp = Math.cos(pitch);
  const sp = Math.sin(pitch);
  // Column-major Rx(pitch) * Ry(yaw).
  return new Float32Array([c, sp * s, -cp * s, 0, cp, sp, s, -sp * c, cp * c]);
}

function glRenderer(canvas) {
  const gl = canvas.getContext('webgl', { alpha: true, antialias: true, premultipliedAlpha: false });
  if (!gl) return null;
  const shader = (type, src) => {
    const s = gl.createShader(type);
    gl.shaderSource(s, src);
    gl.compileShader(s);
    return gl.getShaderParameter(s, gl.COMPILE_STATUS) ? s : null;
  };
  const vs = shader(gl.VERTEX_SHADER, VERT);
  const fs = shader(gl.FRAGMENT_SHADER, FRAG);
  if (!vs || !fs) return null;
  const prog = gl.createProgram();
  gl.attachShader(prog, vs);
  gl.attachShader(prog, fs);
  gl.linkProgram(prog);
  if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) return null;
  gl.useProgram(prog);
  gl.enable(gl.BLEND);
  gl.blendFunc(gl.SRC_ALPHA, gl.ONE_MINUS_SRC_ALPHA);

  const loc = {};
  ['uRot', 'uScale', 'uPx', 'uTime', 'uMode', 'uStill', 'uDot'].forEach((n) => (loc[n] = gl.getUniformLocation(prog, n)));
  const attr = { aPos: gl.getAttribLocation(prog, 'aPos'), aColor: gl.getAttribLocation(prog, 'aColor'), aData: gl.getAttribLocation(prog, 'aData') };
  const layers = {};

  const buffer = (arr) => {
    const b = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, b);
    gl.bufferData(gl.ARRAY_BUFFER, arr, gl.STATIC_DRAW);
    return b;
  };
  const bind = (name, buf, size, fallback) => {
    const l = attr[name];
    if (l < 0) return;
    if (buf) {
      gl.bindBuffer(gl.ARRAY_BUFFER, buf);
      gl.enableVertexAttribArray(l);
      gl.vertexAttribPointer(l, size, gl.FLOAT, false, 0, 0);
    } else {
      gl.disableVertexAttribArray(l);
      gl.vertexAttrib4f(l, ...fallback);
    }
  };

  return {
    set(name, layer) {
      const old = layers[name];
      if (old) Object.values(old.buffers).forEach((b) => gl.deleteBuffer(b));
      const count = layer.pos.length / 3;
      const buffers = { pos: buffer(layer.pos) };
      if (layer.col) buffers.col = buffer(layer.col);
      if (layer.dat) buffers.dat = buffer(layer.dat);
      if (layer.alpha) buffers.col = buffer(new Float32Array(layer.alpha.flatMap((a) => [1, 1, 1, a])));
      layers[name] = { count, buffers };
    },
    draw(state) {
      gl.viewport(0, 0, canvas.width, canvas.height);
      gl.clearColor(0, 0, 0, 0);
      gl.clear(gl.COLOR_BUFFER_BIT);
      gl.uniformMatrix3fv(loc.uRot, false, state.rot);
      gl.uniform1f(loc.uScale, SCALE);
      gl.uniform1f(loc.uTime, state.time);
      gl.uniform1f(loc.uStill, state.still ? 1 : 0);
      gl.uniform4fv(loc.uDot, state.dot);
      [
        ['dots', 0, state.dotPx],
        ['arcs', 1, state.arcPx],
        ['markers', 3, state.arcPx],
        ['markers', 2, state.arcPx],
      ].forEach(([name, mode, px]) => {
        const layer = layers[name];
        if (!layer || !layer.count) return;
        gl.uniform1f(loc.uMode, mode);
        gl.uniform1f(loc.uPx, px);
        bind('aPos', layer.buffers.pos, 3);
        bind('aColor', layer.buffers.col, 4, [1, 1, 1, 1]);
        bind('aData', layer.buffers.dat, 2, [0, 0, 0, 0]);
        gl.drawArrays(gl.POINTS, 0, layer.count);
      });
    },
  };
}

// Same look on a 2D canvas: project on the CPU, square dots, painter's order is not needed
// because back-facing points are faint.
function canvasRenderer(canvas) {
  const ctx = canvas.getContext('2d');
  if (!ctx) return null;
  const layers = {};
  const css = (c, a) => `rgba(${Math.round(c[0] * 255)},${Math.round(c[1] * 255)},${Math.round(c[2] * 255)},${a.toFixed(3)})`;
  return {
    set(name, layer) {
      layers[name] = layer;
    },
    draw(state) {
      const w = canvas.width;
      const h = canvas.height;
      const r = (Math.min(w, h) / 2) * SCALE;
      const m = state.rot;
      ctx.clearRect(0, 0, w, h);
      const each = (layer, fn) => {
        const p = layer.pos;
        for (let i = 0, j = 0; i < p.length; i += 3, j++) {
          const x = m[0] * p[i] + m[3] * p[i + 1] + m[6] * p[i + 2];
          const y = m[1] * p[i] + m[4] * p[i + 1] + m[7] * p[i + 2];
          const z = m[2] * p[i] + m[5] * p[i + 1] + m[8] * p[i + 2];
          fn(w / 2 + x * r, h / 2 - y * r, z, j, x * x + y * y);
        }
      };
      if (layers.dots) {
        ctx.fillStyle = css(state.dot, 1);
        each(layers.dots, (x, y, z, j) => {
          if (z < -0.05) return;
          const s = state.dotPx * (0.7 + 0.4 * z);
          ctx.globalAlpha = state.dot[3] * layers.dots.alpha[j] * (0.5 + 0.5 * z);
          ctx.fillRect(x - s / 2, y - s / 2, s, s);
        });
        ctx.globalAlpha = 1;
      }
      if (layers.arcs) {
        const { col, dat } = layers.arcs;
        each(layers.arcs, (x, y, z, j, d2) => {
          if (z < 0 && d2 < 1) return;
          const t = dat[j * 2];
          const head = ((state.time * 0.16 + dat[j * 2 + 1]) % 1) * 1.8;
          const trail = t <= head ? clamp((t - (head - 0.5)) / 0.5) : 0;
          const a = state.still ? 0.9 : Math.max(0.3, trail);
          const s = state.arcPx * (1 + trail * 0.5);
          ctx.fillStyle = css(col.subarray(j * 4, j * 4 + 3), a);
          ctx.fillRect(x - s / 2, y - s / 2, s, s);
        });
      }
      if (layers.markers) {
        const { col } = layers.markers;
        each(layers.markers, (x, y, z, j, d2) => {
          if (z < 0 && d2 < 1) return;
          ctx.fillStyle = css(col.subarray(j * 4, j * 4 + 3), 1);
          ctx.beginPath();
          ctx.arc(x, y, state.arcPx * 1.3, 0, Math.PI * 2);
          ctx.fill();
        });
      }
    },
  };
}

on('.brik-globe-stage', (stage) => {
  const canvas = stage.querySelector('.brik-globe-canvas');
  if (!canvas) return;
  const cfg = data(stage, 'config');
  const routes = Array.isArray(cfg.routes) ? cfg.routes : [];
  const mask = landMask(stage.getAttribute('data-mask'));
  const calm = reducedMotion();

  const gl = glRenderer(canvas);
  const renderer = gl || canvasRenderer(canvas);
  if (!renderer) return;
  // The 2D fallback draws every dot itself, so it gets a sparser sphere.
  const cap = gl ? 60000 : 14000;
  stage.classList.add('is-ready');

  const state = {
    yaw: (-(cfg.lng || 0) * Math.PI) / 180,
    pitch: ((cfg.tilt || 0) * Math.PI) / 180,
    time: 0,
    still: calm,
    dot: [0.5, 0.5, 0.5, 0.85],
    dotPx: 2,
    arcPx: 2,
    rot: null,
  };
  let size = 0;
  let colors = [];

  const readColors = () => {
    const dot = rgba(stage, cfg.dot || 'var(--foreground)', [0.5, 0.5, 0.5, 1]);
    state.dot = [dot[0], dot[1], dot[2], cfg.dot ? dot[3] : 0.85];
    colors = [rgba(stage, cfg.arc, [0.13, 0.83, 0.93, 1]), rgba(stage, cfg.arc2, [0.65, 0.55, 0.98, 1])];
    const resolved = routes.map((r) => Object.assign({}, r, r.color ? { c1: rgba(stage, r.color), c2: rgba(stage, r.color) } : {}));
    renderer.set('arcs', buildArcs(resolved, colors));
    renderer.set('markers', cfg.markers ? buildMarkers(resolved, colors) : { pos: new Float32Array(0) });
  };

  // Dot density follows the rendered size so dots keep the same spacing at any width.
  let sparse = 1; // grows once if the device can't keep up
  const rebuildDots = (cssRadius) => {
    const spacing = (isSmall() ? 6 : 5.2) * sparse;
    const count = clamp(Math.round(4 * Math.PI * Math.pow(cssRadius / spacing, 2)), 3000, cap);
    renderer.set('dots', buildDots(mask, count, cfg.ocean));
  };

  const frame = () => {
    state.rot = rotation(state.yaw, state.pitch);
    renderer.draw(state);
  };

  readColors();
  fitCanvas(canvas, (w, h, dpr) => {
    const r = (Math.min(w, h) / 2) * SCALE;
    if (Math.abs(r - size) > 24 || !size) {
      size = r;
      rebuildDots(r);
    }
    const spacing = (isSmall() ? 6 : 5.2) * dpr * Math.sqrt(sparse);
    state.dotPx = Math.max(1.4, spacing * 0.55 * (cfg.dotSize || 1));
    state.arcPx = Math.max(2, 2.1 * dpr);
    frame();
  });
  onThemeChange(() => {
    readColors();
    frame();
  });

  // Drag to spin, with a little inertia. Vertical page scrolling stays native on touch.
  let spin = 0;
  let drag = null;
  if (cfg.interactive) {
    canvas.addEventListener('pointerdown', (e) => {
      drag = { x: e.clientX, y: e.clientY, t: performance.now() };
      spin = 0;
      canvas.setPointerCapture(e.pointerId);
      stage.classList.add('is-dragging');
    });
    canvas.addEventListener('pointermove', (e) => {
      if (!drag) return;
      const r = canvas.getBoundingClientRect().width / 2;
      const dx = (e.clientX - drag.x) / r;
      const dy = (e.clientY - drag.y) / r;
      const now = performance.now();
      state.yaw += dx;
      state.pitch = clamp(state.pitch + dy * 0.6, -0.8, 1.1);
      spin = (dx / Math.max(8, now - drag.t)) * 16;
      drag = { x: e.clientX, y: e.clientY, t: now };
      if (calm) frame();
    });
    const end = () => {
      drag = null;
      stage.classList.remove('is-dragging');
    };
    canvas.addEventListener('pointerup', end);
    canvas.addEventListener('pointercancel', end);
  }

  if (calm) return;

  const auto = (cfg.speed || 0) * 0.00016; // radians per millisecond
  let slow = 0;
  let frames = 0;
  loop(stage, (t, dt) => {
    state.time = t / 1000;
    // Software GL and weak GPUs: after a couple of seconds of slow frames, halve the dot count.
    if (sparse === 1 && frames < 150) {
      frames++;
      slow += dt > 24 ? 1 : 0;
      if (frames === 150 && slow > 90) {
        sparse = 1.45;
        state.dotPx *= Math.sqrt(sparse);
        rebuildDots(size);
      }
    }
    if (!drag) {
      spin *= 0.95;
      state.yaw += auto * dt + spin * (dt / 16);
    }
    frame();
  });
});
