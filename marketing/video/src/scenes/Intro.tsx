import { AbsoluteFill, interpolate, spring, useCurrentFrame, useVideoConfig } from "remotion";
import { Background } from "../components/Background";
import { Logo } from "../components/Logo";
import { C, inter, sora } from "../theme";

export const Intro: React.FC = () => {
  const f = useCurrentFrame();
  const { fps } = useVideoConfig();
  const word = spring({ frame: f - 22, fps, config: { damping: 200 } });
  const tag = interpolate(f, [40, 58], [0, 1], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });
  return (
    <AbsoluteFill>
      <Background />
      <AbsoluteFill style={{ alignItems: "center", justifyContent: "center", gap: 36 }}>
        <div style={{ display: "flex", alignItems: "center", gap: 36 }}>
          <Logo size={170} />
          <div style={{ fontFamily: sora, fontWeight: 700, fontSize: 170, color: C.text, letterSpacing: "-0.04em", opacity: word, transform: `translateX(${(1 - word) * -40}px)`, clipPath: `inset(0 ${(1 - word) * 100}% 0 0)` }}>Brik</div>
        </div>
        <div style={{ fontFamily: inter, fontSize: 36, color: C.muted, opacity: tag, transform: `translateY(${(1 - tag) * 16}px)` }}>The open-source visual builder for WordPress</div>
      </AbsoluteFill>
    </AbsoluteFill>
  );
};
