import { AbsoluteFill, interpolate, spring, useCurrentFrame, useVideoConfig } from "remotion";
import { Background } from "../components/Background";
import { Logo } from "../components/Logo";
import { C, GRADIENT, inter, sora } from "../theme";

export const Outro: React.FC = () => {
  const f = useCurrentFrame();
  const { fps } = useVideoConfig();
  const s = spring({ frame: f - 10, fps, config: { damping: 200 } });
  const cta = spring({ frame: f - 28, fps, config: { damping: 14 } });
  return (
    <AbsoluteFill>
      <Background intensity={1.2} />
      <AbsoluteFill style={{ alignItems: "center", justifyContent: "center", gap: 34, textAlign: "center" }}>
        <Logo size={130} />
        <div style={{ fontFamily: sora, fontWeight: 700, fontSize: 110, letterSpacing: "-0.04em", color: C.text, opacity: s, transform: `translateY(${(1 - s) * 30}px)` }}>
          Free <span style={{ color: C.muted }}>&amp;</span> <span style={{ backgroundImage: GRADIENT, WebkitBackgroundClip: "text", backgroundClip: "text", color: "transparent" }}>open source</span>
        </div>
        <div style={{ display: "flex", gap: 20, opacity: cta, transform: `scale(${0.9 + cta * 0.1})` }}>
          <span style={{ fontFamily: inter, fontWeight: 600, fontSize: 34, color: "#0b0b10", background: "#fff", padding: "18px 40px", borderRadius: 14 }}>brikwp.com</span>
          <span style={{ fontFamily: inter, fontWeight: 500, fontSize: 34, color: C.text, border: `1px solid ${C.border}`, padding: "18px 40px", borderRadius: 14 }}>github.com/dilukangelosl/brik</span>
        </div>
        <div style={{ fontFamily: inter, fontSize: 28, color: C.muted, opacity: interpolate(f, [45, 60], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp" }) }}>GPL-2.0 · WordPress 6.3+ · Works with any theme</div>
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
