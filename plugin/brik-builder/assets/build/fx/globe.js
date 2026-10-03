(()=>{var D=(o,t)=>window.brik.on(o,t),L=()=>window.matchMedia("(prefers-reduced-motion: reduce)").matches;function _(o,t){let e=!1,r=0,n=0,s=d=>{let m=n?Math.min(d-n,64):16;n=d,t(d,m),r=requestAnimationFrame(s)},l=()=>{!r&&e&&!document.hidden&&(n=0,r=requestAnimationFrame(s))},f=()=>{cancelAnimationFrame(r),r=0},h=new IntersectionObserver(([d])=>{e=d.isIntersecting,e?l():f()});return h.observe(o),document.addEventListener("visibilitychange",()=>document.hidden?f():l()),()=>{f(),h.disconnect()}}function z(o,t){let e=()=>{let r=Math.min(window.devicePixelRatio||1,2),n=o.parentElement.getBoundingClientRect();o.width=Math.max(1,Math.round(n.width*r)),o.height=Math.max(1,Math.round(n.height*r)),o.style.width=`${n.width}px`,o.style.height=`${n.height}px`,t&&t(n.width,n.height,r)};new ResizeObserver(e).observe(o.parentElement),e()}var R=(o,t=0,e=1)=>Math.min(e,Math.max(t,o));var I=()=>window.matchMedia("(max-width: 767px)").matches;function O(o,t,e={}){try{return JSON.parse(o.getAttribute(`data-${t}`)||"")||e}catch{return e}}var P;function S(o,t,e=[.5,.5,.5,1]){if(!t)return e;let r=document.createElement("span");if(r.style.cssText="display:none",r.style.color=t,!r.style.color)return e;o.appendChild(r);let n=getComputedStyle(r).color;if(r.remove(),P=P||document.createElement("canvas").getContext("2d",{willReadFrequently:!0}),!P)return e;P.clearRect(0,0,1,1),P.fillStyle="#000",P.fillStyle=n,P.fillRect(0,0,1,1);let[s,l,f,h]=P.getImageData(0,0,1,1).data;return[s/255,l/255,f/255,h/255]}function B(o){let t=0,e=()=>{clearTimeout(t),t=setTimeout(o,50)};window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change",e);let r=new MutationObserver(e);r.observe(document.documentElement,{attributes:!0,attributeFilter:["class","data-theme"]}),r.observe(document.body,{attributes:!0,attributeFilter:["class"]})}function q(o){let t=new Uint8Array(0);try{t=Uint8Array.from(atob(o||""),e=>e.charCodeAt(0))}catch{}return(e,r)=>{let n=Math.min(89,Math.max(0,Math.floor((90-e)/2))),s=Math.min(179,Math.max(0,Math.floor((r+180)/2))),l=n*180+s;return l>>3<t.length&&(t[l>>3]&128>>(l&7))!==0}}function E(o,t){let e=o*Math.PI/180,r=t*Math.PI/180;return[Math.cos(e)*Math.sin(r),Math.sin(e),Math.cos(e)*Math.cos(r)]}var U=`
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
}`,$=`
precision mediump float;
varying vec4 vColor;
varying float vRing;
void main() {
  float d = length(gl_PointCoord - 0.5);
  float a = vRing > 0.5 ? smoothstep(0.5, 0.42, d) * smoothstep(0.3, 0.4, d) : smoothstep(0.5, 0.3, d);
  if (a * vColor.a < 0.004) discard;
  gl_FragColor = vec4(vColor.rgb, vColor.a * a);
}`,T=.84;function N(o,t,e){let r=[],n=[],s=Math.PI*(3-Math.sqrt(5));for(let l=0;l<t;l++){let f=1-2*(l+.5)/t,h=Math.sqrt(1-f*f),d=s*l,m=Math.cos(d)*h,c=Math.sin(d)*h,a=o(Math.asin(f)*180/Math.PI,Math.atan2(m,c)*180/Math.PI);(a||e&&l%3===0)&&(r.push(m,f,c),n.push(a?1:.22))}return{pos:new Float32Array(r),alpha:n}}function Y(o,t){let e=[],r=[],n=[];return o.forEach((s,l)=>{let f=E(s.from[0],s.from[1]),h=E(s.to[0],s.to[1]),d=R(f[0]*h[0]+f[1]*h[1]+f[2]*h[2],-1,1),m=Math.acos(d);if(m<.001)return;let c=Math.max(40,Math.round(m*110)),a=.06+.22*(m/Math.PI),i=s.c1||t[0],p=s.c2||t[1],u=l*.37%1;for(let x=0;x<=c;x++){let g=x/c,A=Math.sin((1-g)*m)/Math.sin(m),w=Math.sin(g*m)/Math.sin(m),y=1+a*Math.sin(Math.PI*g);e.push((f[0]*A+h[0]*w)*y,(f[1]*A+h[1]*w)*y,(f[2]*A+h[2]*w)*y),r.push(i[0]+(p[0]-i[0])*g,i[1]+(p[1]-i[1])*g,i[2]+(p[2]-i[2])*g,1),n.push(g,u)}}),{pos:new Float32Array(e),col:new Float32Array(r),dat:new Float32Array(n)}}function H(o,t){let e=new Set,r=[],n=[],s=[];return o.forEach(l=>{[[l.from,l.c1||t[0]],[l.to,l.c2||t[1]]].forEach(([f,h])=>{let d=f.join(",");e.has(d)||(e.add(d),r.push(...E(f[0],f[1]).map(m=>m*1.005)),n.push(h[0],h[1],h[2],1),s.push(0,e.size*.29%1))})}),{pos:new Float32Array(r),col:new Float32Array(n),dat:new Float32Array(s)}}function X(o,t){let e=Math.cos(o),r=Math.sin(o),n=Math.cos(t),s=Math.sin(t);return new Float32Array([e,s*r,-n*r,0,n,s,r,-s*e,n*e])}function V(o){let t=o.getContext("webgl",{alpha:!0,antialias:!0,premultipliedAlpha:!1});if(!t)return null;let e=(c,a)=>{let i=t.createShader(c);return t.shaderSource(i,a),t.compileShader(i),t.getShaderParameter(i,t.COMPILE_STATUS)?i:null},r=e(t.VERTEX_SHADER,U),n=e(t.FRAGMENT_SHADER,$);if(!r||!n)return null;let s=t.createProgram();if(t.attachShader(s,r),t.attachShader(s,n),t.linkProgram(s),!t.getProgramParameter(s,t.LINK_STATUS))return null;t.useProgram(s),t.enable(t.BLEND),t.blendFunc(t.SRC_ALPHA,t.ONE_MINUS_SRC_ALPHA);let l={};["uRot","uScale","uPx","uTime","uMode","uStill","uDot"].forEach(c=>l[c]=t.getUniformLocation(s,c));let f={aPos:t.getAttribLocation(s,"aPos"),aColor:t.getAttribLocation(s,"aColor"),aData:t.getAttribLocation(s,"aData")},h={},d=c=>{let a=t.createBuffer();return t.bindBuffer(t.ARRAY_BUFFER,a),t.bufferData(t.ARRAY_BUFFER,c,t.STATIC_DRAW),a},m=(c,a,i,p)=>{let u=f[c];u<0||(a?(t.bindBuffer(t.ARRAY_BUFFER,a),t.enableVertexAttribArray(u),t.vertexAttribPointer(u,i,t.FLOAT,!1,0,0)):(t.disableVertexAttribArray(u),t.vertexAttrib4f(u,...p)))};return{set(c,a){let i=h[c];i&&Object.values(i.buffers).forEach(x=>t.deleteBuffer(x));let p=a.pos.length/3,u={pos:d(a.pos)};a.col&&(u.col=d(a.col)),a.dat&&(u.dat=d(a.dat)),a.alpha&&(u.col=d(new Float32Array(a.alpha.flatMap(x=>[1,1,1,x])))),h[c]={count:p,buffers:u}},draw(c){t.viewport(0,0,o.width,o.height),t.clearColor(0,0,0,0),t.clear(t.COLOR_BUFFER_BIT),t.uniformMatrix3fv(l.uRot,!1,c.rot),t.uniform1f(l.uScale,T),t.uniform1f(l.uTime,c.time),t.uniform1f(l.uStill,c.still?1:0),t.uniform4fv(l.uDot,c.dot),[["dots",0,c.dotPx],["arcs",1,c.arcPx],["markers",3,c.arcPx],["markers",2,c.arcPx]].forEach(([a,i,p])=>{let u=h[a];!u||!u.count||(t.uniform1f(l.uMode,i),t.uniform1f(l.uPx,p),m("aPos",u.buffers.pos,3),m("aColor",u.buffers.col,4,[1,1,1,1]),m("aData",u.buffers.dat,2,[0,0,0,0]),t.drawArrays(t.POINTS,0,u.count))})}}}function G(o){let t=o.getContext("2d");if(!t)return null;let e={},r=(n,s)=>`rgba(${Math.round(n[0]*255)},${Math.round(n[1]*255)},${Math.round(n[2]*255)},${s.toFixed(3)})`;return{set(n,s){e[n]=s},draw(n){let s=o.width,l=o.height,f=Math.min(s,l)/2*T,h=n.rot;t.clearRect(0,0,s,l);let d=(m,c)=>{let a=m.pos;for(let i=0,p=0;i<a.length;i+=3,p++){let u=h[0]*a[i]+h[3]*a[i+1]+h[6]*a[i+2],x=h[1]*a[i]+h[4]*a[i+1]+h[7]*a[i+2],g=h[2]*a[i]+h[5]*a[i+1]+h[8]*a[i+2];c(s/2+u*f,l/2-x*f,g,p,u*u+x*x)}};if(e.dots&&(t.fillStyle=r(n.dot,1),d(e.dots,(m,c,a,i)=>{if(a<-.05)return;let p=n.dotPx*(.7+.4*a);t.globalAlpha=n.dot[3]*e.dots.alpha[i]*(.5+.5*a),t.fillRect(m-p/2,c-p/2,p,p)}),t.globalAlpha=1),e.arcs){let{col:m,dat:c}=e.arcs;d(e.arcs,(a,i,p,u,x)=>{if(p<0&&x<1)return;let g=c[u*2],A=(n.time*.16+c[u*2+1])%1*1.8,w=g<=A?R((g-(A-.5))/.5):0,y=n.still?.9:Math.max(.3,w),b=n.arcPx*(1+w*.5);t.fillStyle=r(m.subarray(u*4,u*4+3),y),t.fillRect(a-b/2,i-b/2,b,b)})}if(e.markers){let{col:m}=e.markers;d(e.markers,(c,a,i,p,u)=>{i<0&&u<1||(t.fillStyle=r(m.subarray(p*4,p*4+3),1),t.beginPath(),t.arc(c,a,n.arcPx*1.3,0,Math.PI*2),t.fill())})}}}}D(".brik-globe-stage",o=>{let t=o.querySelector(".brik-globe-canvas");if(!t)return;let e=O(o,"config"),r=Array.isArray(e.routes)?e.routes:[],n=q(o.getAttribute("data-mask")),s=L(),l=V(t),f=l||G(t);if(!f)return;let h=l?6e4:14e3;o.classList.add("is-ready");let d={yaw:-(e.lng||0)*Math.PI/180,pitch:(e.tilt||0)*Math.PI/180,time:0,still:s,dot:[.5,.5,.5,.85],dotPx:2,arcPx:2,rot:null},m=0,c=[],a=()=>{let b=S(o,e.dot||"var(--foreground)",[.5,.5,.5,1]);d.dot=[b[0],b[1],b[2],e.dot?b[3]:.85],c=[S(o,e.arc,[.13,.83,.93,1]),S(o,e.arc2,[.65,.55,.98,1])];let M=r.map(v=>Object.assign({},v,v.color?{c1:S(o,v.color),c2:S(o,v.color)}:{}));f.set("arcs",Y(M,c)),f.set("markers",e.markers?H(M,c):{pos:new Float32Array(0)})},i=1,p=b=>{let M=(I()?6:5.2)*i,v=R(Math.round(4*Math.PI*Math.pow(b/M,2)),3e3,h);f.set("dots",N(n,v,e.ocean))},u=()=>{d.rot=X(d.yaw,d.pitch),f.draw(d)};a(),z(t,(b,M,v)=>{let C=Math.min(b,M)/2*T;(Math.abs(C-m)>24||!m)&&(m=C,p(C));let F=(I()?6:5.2)*v*Math.sqrt(i);d.dotPx=Math.max(1.4,F*.55*(e.dotSize||1)),d.arcPx=Math.max(2,2.1*v),u()}),B(()=>{a(),u()});let x=0,g=null;if(e.interactive){t.addEventListener("pointerdown",M=>{g={x:M.clientX,y:M.clientY,t:performance.now()},x=0,t.setPointerCapture(M.pointerId),o.classList.add("is-dragging")}),t.addEventListener("pointermove",M=>{if(!g)return;let v=t.getBoundingClientRect().width/2,C=(M.clientX-g.x)/v,F=(M.clientY-g.y)/v,k=performance.now();d.yaw+=C,d.pitch=R(d.pitch+F*.6,-.8,1.1),x=C/Math.max(8,k-g.t)*16,g={x:M.clientX,y:M.clientY,t:k},s&&u()});let b=()=>{g=null,o.classList.remove("is-dragging")};t.addEventListener("pointerup",b),t.addEventListener("pointercancel",b)}if(s)return;let A=(e.speed||0)*16e-5,w=0,y=0;_(o,(b,M)=>{d.time=b/1e3,i===1&&y<150&&(y++,w+=M>24?1:0,y===150&&w>90&&(i=1.45,d.dotPx*=Math.sqrt(i),p(m))),g||(x*=.95,d.yaw+=A*M+x*(M/16)),u()})});})();
