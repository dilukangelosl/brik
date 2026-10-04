import { AbsoluteFill, useCurrentFrame } from "remotion";
import { C } from "../theme";

// Slowly drifting colour fields over a faint grid, shared by every scene.
export const Background: React.FC<{ intensity?: number }> = ({ intensity = 1 }) => {
  const f = useCurrentFrame();
  const a = Math.sin(f / 90) * 120;
  const b = Math.cos(f / 110) * 140;
  return (
    <AbsoluteFill style={{ background: C.bg, overflow: "hidden" }}>
      <AbsoluteFill
        style={{
          backgroundImage: "linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px)",
          backgroundSize: "64px 64px",
          maskImage: "radial-gradient(ellipse at center, black 30%, transparent 75%)",
        }}
      />
      <div style={{ position: "absolute", width: 900, height: 900, left: -200 + a, top: -350 + b / 2, borderRadius: "50%", background: C.violet, filter: "blur(180px)", opacity: 0.35 * intensity }} />
      <div style={{ position: "absolute", width: 800, height: 800, right: -220 - a / 2, bottom: -380 + b / 3, borderRadius: "50%", background: "#0891b2", filter: "blur(180px)", opacity: 0.3 * intensity }} />
    </AbsoluteFill>
  );
};
