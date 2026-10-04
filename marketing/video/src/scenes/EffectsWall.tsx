import { AbsoluteFill, Img, interpolate, staticFile, useCurrentFrame } from "remotion";
import { Background } from "../components/Background";
import { Words } from "../components/Words";
import { C, GRADIENT, inter, sora } from "../theme";

const COLS = [
  ["fx-globe", "fx-bento", "fx-retro", "fx-cards"],
  ["fx-beams", "fx-marquee", "fx-integrations", "aurora-hero"],
  ["fx-cards", "fx-retro", "fx-globe", "fx-beams"],
  ["fx-bento", "fx-integrations", "fx-marquee", "aurora-hero"],
];

// A tilted wall of real Brik sections drifting past, with the headline on top.
export const EffectsWall: React.FC = () => {
  const f = useCurrentFrame();
  const fade = interpolate(f, [0, 20], [0, 1], { extrapolateRight: "clamp" });
  return (
    <AbsoluteFill>
      <Background />
      <AbsoluteFill style={{ perspective: 2200, opacity: fade }}>
        <div style={{ position: "absolute", left: "50%", top: "50%", display: "flex", gap: 36, transform: "translate(-50%,-50%) rotateX(48deg) rotateZ(-30deg)", transformStyle: "preserve-3d" }}>
          {COLS.map((col, i) => (
            <div key={i} style={{ display: "flex", flexDirection: "column", gap: 36, transform: `translateY(${(i % 2 ? -1 : 1) * (f * 2.4) - (i % 2 ? 0 : 900)}px)` }}>
              {[...col, ...col].map((n, j) => (
                <Img key={j} src={staticFile(`shots/${n}.jpg`)} style={{ width: 640, height: 400, objectFit: "cover", objectPosition: "top", borderRadius: 18, boxShadow: "0 30px 60px rgba(0,0,0,0.6)", border: `1px solid ${C.border}` }} />
              ))}
            </div>
          ))}
        </div>
      </AbsoluteFill>
      <AbsoluteFill style={{ background: "radial-gradient(ellipse at center, rgba(7,7,12,0.92) 0%, rgba(7,7,12,0.55) 45%, rgba(7,7,12,0.2) 75%)" }} />
      <AbsoluteFill style={{ alignItems: "center", justifyContent: "center", textAlign: "center" }}>
        <div style={{ fontFamily: sora, fontWeight: 700, fontSize: 104, letterSpacing: "-0.04em", color: C.text, lineHeight: 1.05 }}>
          <Words text="90+ elements." delay={8} />
          <br />
          <Words text="17 animated backgrounds." delay={18} wordStyle={() => ({ backgroundImage: GRADIENT, WebkitBackgroundClip: "text", backgroundClip: "text", color: "transparent" })} />
        </div>
        <div style={{ marginTop: 30, fontFamily: inter, fontSize: 36, color: C.muted, opacity: interpolate(f, [40, 56], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp" }) }}>3D, WebGL and scroll effects — built in, no code.</div>
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
