// Animated mesh gradient rendered with a small WebGL fragment shader.
import { on } from './_api.js';
import { settings, canvas, animate, pointer, onTheme } from './_bg.js';

const VERT = 'attribute vec2 p;void main(){gl_Position=vec4(p,0.,1.);}';

// Four colour points drift around a warped plane; colours blend by inverse distance,
// which gives the soft "mesh" look. The pointer gently pushes the plane around.
const FRAG = `
precision highp float;
uniform vec2 uRes;
uniform float uT;
uniform vec3 uC1, uC2, uC3, uC4;
uniform vec3 uM;
uniform float uA;
float h(vec2 p){return fract(sin(dot(p,vec2(127.1,311.7)))*43758.5453);}
float n(vec2 p){vec2 i=floor(p),f=fract(p);vec2 u=f*f*(3.-2.*f);
  return mix(mix(h(i),h(i+vec2(1.,0.)),u.x),mix(h(i+vec2(0.,1.)),h(i+vec2(1.,1.)),u.x),u.y);}
void main(){
  vec2 uv=gl_FragCoord.xy/uRes;
  float asp=uRes.x/uRes.y;
  vec2 p=vec2(uv.x*asp,uv.y);
  float t=uT*.0001;
  vec2 q=p+.32*vec2(n(p*1.7+vec2(t*2.,0.))-.5,n(p*1.7+vec2(5.2,-t*1.6))-.5)*2.;
  vec2 m=vec2(uM.x*asp,uM.y);
  vec2 dm=q-m;
  q+=uM.z*.18*dm*exp(-dot(dm,dm)*5.);
  vec2 a=vec2(asp*(.22+.16*sin(t*3.1)),.75+.18*cos(t*2.3));
  vec2 b=vec2(asp*(.78+.14*cos(t*2.7)),.68+.2*sin(t*3.7+1.));
  vec2 c=vec2(asp*(.6+.22*sin(t*1.9+2.)),.18+.16*cos(t*2.9));
  vec2 d=vec2(asp*(.25+.18*cos(t*2.2+4.)),.22+.2*sin(t*1.7+3.));
  float wa=1./pow(distance(q,a)+.06,2.6);
  float wb=1./pow(distance(q,b)+.06,2.6);
  float wc=1./pow(distance(q,c)+.06,2.6);
  float wd=1./pow(distance(q,d)+.06,2.6);
  vec3 col=(uC1*wa+uC2*wb+uC3*wc+uC4*wd)/(wa+wb+wc+wd);
  col+=(h(gl_FragCoord.xy+fract(t))-.5)*(4./255.);
  gl_FragColor=vec4(col*uA,uA);
}`;

function hueShift([r, g, b], deg, light = 0) {
  // Rotate hue in RGB space (Rodrigues around the grey axis), then lift brightness.
  const a = (deg * Math.PI) / 180;
  const c = Math.cos(a);
  const s = Math.sin(a);
  const k = (1 - c) / 3;
  const q = Math.sqrt(1 / 3) * s;
  const m = [c + k, k - q, k + q, k + q, c + k, k - q, k - q, k + q, c + k];
  return [
    Math.min(255, r * m[0] + g * m[1] + b * m[2] + light),
    Math.min(255, r * m[3] + g * m[4] + b * m[5] + light),
    Math.min(255, r * m[6] + g * m[7] + b * m[8] + light),
  ].map((v) => Math.max(0, v) / 255);
}

on('[data-brik-effect="gradient"]', (el) => {
  const s = settings(el);
  const ptr = pointer(el);
  let W = 1;
  let H = 1;
  let gl;
  let u = {};
  let redraw = () => {};

  // The gradient is smooth, so half resolution looks identical and costs a quarter.
  const made = canvas(
    el,
    s,
    (w, h) => {
      W = w;
      H = h;
      if (gl) gl.viewport(0, 0, gl.drawingBufferWidth, gl.drawingBufferHeight);
      redraw();
    },
    'webgl',
    s.lite ? 0.35 : 0.5
  );
  gl = made.ctx;
  if (!gl) {
    made.canvas.remove();
    el.classList.add('is-fallback');
    return;
  }

  const sh = (type, src) => {
    const o = gl.createShader(type);
    gl.shaderSource(o, src);
    gl.compileShader(o);
    return o;
  };
  const prog = gl.createProgram();
  gl.attachShader(prog, sh(gl.VERTEX_SHADER, VERT));
  gl.attachShader(prog, sh(gl.FRAGMENT_SHADER, FRAG));
  gl.linkProgram(prog);
  if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) {
    made.canvas.remove();
    el.classList.add('is-fallback');
    return;
  }
  gl.useProgram(prog);
  gl.bindBuffer(gl.ARRAY_BUFFER, gl.createBuffer());
  gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 3, -1, -1, 3]), gl.STATIC_DRAW);
  const loc = gl.getAttribLocation(prog, 'p');
  gl.enableVertexAttribArray(loc);
  gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);
  ['uRes', 'uT', 'uC1', 'uC2', 'uC3', 'uC4', 'uM', 'uA'].forEach((k) => (u[k] = gl.getUniformLocation(prog, k)));
  gl.viewport(0, 0, gl.drawingBufferWidth, gl.drawingBufferHeight);

  const setColors = () => {
    const [c1, c2] = s.colors;
    gl.uniform3fv(u.uC1, hueShift(c1, 0));
    gl.uniform3fv(u.uC2, hueShift(c2, 0));
    gl.uniform3fv(u.uC3, hueShift(c1, 50, 20));
    gl.uniform3fv(u.uC4, hueShift(c2, -60, -10));
  };
  setColors();
  onTheme(el, () => {
    s.read();
    setColors();
    redraw();
  });

  const m = { x: 0.5, y: 0.5, z: 0 };
  const draw = (t, dt) => {
    const active = s.interactive && ptr.active;
    const k = Math.min(1, (dt || 16) / 160);
    m.x += ((active ? ptr.x / W : 0.5) - m.x) * k;
    m.y += ((active ? 1 - ptr.y / H : 0.5) - m.y) * k;
    m.z += ((active ? 1 : 0) - m.z) * k;
    gl.uniform2f(u.uRes, gl.drawingBufferWidth, gl.drawingBufferHeight);
    gl.uniform1f(u.uT, t + 20000);
    gl.uniform3f(u.uM, m.x, m.y, m.z);
    gl.uniform1f(u.uA, 0.35 + s.intensity * 0.65);
    gl.drawArrays(gl.TRIANGLES, 0, 3);
  };
  redraw = animate(el, s, draw);
});
