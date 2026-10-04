import { AbsoluteFill, interpolate, spring, useCurrentFrame, useVideoConfig } from "remotion";
import { Background } from "../components/Background";
import { Words } from "../components/Words";
import { C, GRADIENT, inter, sora } from "../theme";

export const Headline: React.FC = () => {
  const f = useCurrentFrame();
  const { fps } = useVideoConfig();
  const pop = spring({ frame: f - 32, fps, config: { damping: 12 } });
  const sub = interpolate(f, [58, 74], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });
  return (
    <AbsoluteFill>
      <Background />
      <AbsoluteFill style={{ alignItems: "center", justifyContent: "center", textAlign: "center", padding: 120 }}>
        <div style={{ fontFamily: sora, fontWeight: 700, fontSize: 118, lineHeight: 1.05, letterSpacing: "-0.04em", color: C.text }}>
          <Words text="Build any WordPress site." />
          <div style={{ marginTop: 10, transform: `scale(${0.8 + pop * 0.2})`, opacity: pop }}>
            <span style={{ backgroundImage: GRADIENT, WebkitBackgroundClip: "text", backgroundClip: "text", color: "transparent", backgroundSize: "200% auto", backgroundPosition: `${f * 1.5}% center` }}>Visually.</span>
          </div>
        </div>
        <div style={{ marginTop: 40, fontFamily: inter, fontSize: 40, color: C.muted, opacity: sub }}>Drag. Drop. Publish. No code required.</div>
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
