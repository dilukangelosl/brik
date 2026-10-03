(()=>{var y=(n,e)=>window.brik.on(n,e),C=()=>window.matchMedia("(prefers-reduced-motion: reduce)").matches,b=()=>document.body.classList.contains("brik-canvas-mode");function M(n,e){let o=!1,i=0,r=0,t=u=>{let a=r?Math.min(u-r,64):16;r=u,e(u,a),i=requestAnimationFrame(t)},s=()=>{!i&&o&&!document.hidden&&(r=0,i=requestAnimationFrame(t))},d=()=>{cancelAnimationFrame(i),i=0},c=new IntersectionObserver(([u])=>{o=u.isIntersecting,o?s():d()});return c.observe(n),document.addEventListener("visibilitychange",()=>document.hidden?d():s()),()=>{d(),c.disconnect()}}function A(n,e){let o=()=>{let i=Math.min(window.devicePixelRatio||1,2),r=n.parentElement.getBoundingClientRect();n.width=Math.max(1,Math.round(r.width*i)),n.height=Math.max(1,Math.round(r.height*i)),n.style.width=`${r.width}px`,n.style.height=`${r.height}px`,e&&e(r.width,r.height,i)};new ResizeObserver(o).observe(n.parentElement),o()}function R(n,e,o="#888"){if(!e)return o;let i=document.createElement("span");i.style.color=e,i.style.display="none",n.appendChild(i);let r=getComputedStyle(i).color;return i.remove(),r||o}var h;function F(n,e){if(!h){let i=document.createElement("canvas");i.width=i.height=1,h=i.getContext("2d",{willReadFrequently:!0})}h.clearRect(0,0,1,1),h.fillStyle="#888",h.fillStyle=R(n,e,"#888"),h.fillRect(0,0,1,1);let o=h.getImageData(0,0,1,1).data;return[o[0],o[1],o[2]]}function E(n){let e=n.dataset,o={intensity:Math.max(0,Math.min(1,(Number(e.intensity)||0)/100)),speed:(Number(e.speed)||0)/100,interactive:e.interactive==="1",lite:b(),colors:[]};return o.still=C()||o.speed===0,o.read=()=>{o.colors=[F(n,"var(--fx-color)"),F(n,"var(--fx-color2)")]},o.read(),o}function S(n,e){let o=new MutationObserver(()=>{if(!n.isConnected)return o.disconnect();e()});o.observe(document.documentElement,{attributes:!0,attributeFilter:["class","data-theme"]}),o.observe(document.body,{attributes:!0,attributeFilter:["class"]})}function T(n,e,o,i="2d",r=0){let t=document.createElement("canvas");n.appendChild(t);let s=i==="2d"?t.getContext("2d"):t.getContext("webgl",{premultipliedAlpha:!0,antialias:!1,alpha:!0});return A(t,(d,c,u)=>{let a=r||(e.lite?Math.min(u,1):u);a!==u&&(t.width=Math.max(1,Math.round(d*a)),t.height=Math.max(1,Math.round(c*a)),u=a),o(d,c,u)}),{canvas:t,ctx:s}}function q(n,e,o,i=4e3){if(e.still){let d=()=>o(i,16);return d(),d}let r=0,t=0,s=M(n,(d,c)=>{if(!n.isConnected){s();return}if(e.lite){if(t+=c,t<30)return;c=t,t=0}r+=c*e.speed,o(r,c*e.speed)});return()=>{}}function B(n){let e={x:0,y:0,active:!1},o=n.parentElement||n;return o.addEventListener("pointermove",i=>{let r=n.getBoundingClientRect();e.x=i.clientX-r.left,e.y=i.clientY-r.top,e.active=!0},{passive:!0}),o.addEventListener("pointerleave",()=>{e.active=!1}),e}var L="attribute vec2 p;void main(){gl_Position=vec4(p,0.,1.);}",P=`
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
}`;function x([n,e,o],i,r=0){let t=i*Math.PI/180,s=Math.cos(t),d=Math.sin(t),c=(1-s)/3,u=Math.sqrt(1/3)*d,a=[s+c,c-u,c+u,c+u,s+c,c-u,c-u,c+u,s+c];return[Math.min(255,n*a[0]+e*a[1]+o*a[2]+r),Math.min(255,n*a[3]+e*a[4]+o*a[5]+r),Math.min(255,n*a[6]+e*a[7]+o*a[8]+r)].map(v=>Math.max(0,v)/255)}y('[data-brik-effect="gradient"]',n=>{let e=E(n),o=B(n),i=1,r=1,t,s={},d=()=>{},c=T(n,e,(l,m)=>{i=l,r=m,t&&t.viewport(0,0,t.drawingBufferWidth,t.drawingBufferHeight),d()},"webgl",e.lite?.35:.5);if(t=c.ctx,!t){c.canvas.remove(),n.classList.add("is-fallback");return}let u=(l,m)=>{let p=t.createShader(l);return t.shaderSource(p,m),t.compileShader(p),p},a=t.createProgram();if(t.attachShader(a,u(t.VERTEX_SHADER,L)),t.attachShader(a,u(t.FRAGMENT_SHADER,P)),t.linkProgram(a),!t.getProgramParameter(a,t.LINK_STATUS)){c.canvas.remove(),n.classList.add("is-fallback");return}t.useProgram(a),t.bindBuffer(t.ARRAY_BUFFER,t.createBuffer()),t.bufferData(t.ARRAY_BUFFER,new Float32Array([-1,-1,3,-1,-1,3]),t.STATIC_DRAW);let v=t.getAttribLocation(a,"p");t.enableVertexAttribArray(v),t.vertexAttribPointer(v,2,t.FLOAT,!1,0,0),["uRes","uT","uC1","uC2","uC3","uC4","uM","uA"].forEach(l=>s[l]=t.getUniformLocation(a,l)),t.viewport(0,0,t.drawingBufferWidth,t.drawingBufferHeight);let g=()=>{let[l,m]=e.colors;t.uniform3fv(s.uC1,x(l,0)),t.uniform3fv(s.uC2,x(m,0)),t.uniform3fv(s.uC3,x(l,50,20)),t.uniform3fv(s.uC4,x(m,-60,-10))};g(),S(n,()=>{e.read(),g(),d()});let f={x:.5,y:.5,z:0};d=q(n,e,(l,m)=>{let p=e.interactive&&o.active,w=Math.min(1,(m||16)/160);f.x+=((p?o.x/i:.5)-f.x)*w,f.y+=((p?1-o.y/r:.5)-f.y)*w,f.z+=((p?1:0)-f.z)*w,t.uniform2f(s.uRes,t.drawingBufferWidth,t.drawingBufferHeight),t.uniform1f(s.uT,l+2e4),t.uniform3f(s.uM,f.x,f.y,f.z),t.uniform1f(s.uA,.35+e.intensity*.65),t.drawArrays(t.TRIANGLES,0,3)})});})();
