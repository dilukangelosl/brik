import { spring, useCurrentFrame, useVideoConfig, interpolate } from "remotion";
import { C, inter } from "../theme";

// Pill caption that springs in and fades out near the end of its sequence.
export const Callout: React.FC<{ step?: string; text: string; duration: number; style?: React.CSSProperties }> = ({ step, text, duration, style }) => {
  const f = useCurrentFrame();
  const { fps } = useVideoConfig();
  const s = spring({ frame: f, fps, config: { damping: 200 } });
  const out = interpolate(f, [duration - 10, duration], [1, 0], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });
  return (
    <div
      style={{
        display: "inline-flex",
        alignItems: "center",
        gap: 14,
        padding: "14px 24px 14px 14px",
        borderRadius: 999,
        background: "rgba(15,15,23,0.85)",
        border: `1px solid ${C.border}`,
        backdropFilter: "blur(12px)",
        color: C.text,
        fontFamily: inter,
        fontSize: 30,
        fontWeight: 500,
        boxShadow: "0 20px 50px rgba(0,0,0,0.45)",
        opacity: s * out,
        transform: `translateY(${(1 - s) * 30}px) scale(${0.96 + s * 0.04})`,
        ...style,
      }}
    >
      {step && (
        <span style={{ width: 42, height: 42, borderRadius: 21, display: "grid", placeItems: "center", background: `linear-gradient(135deg, ${C.violet}, ${C.cyan})`, fontSize: 20, fontWeight: 600 }}>{step}</span>
      )}
      {text}
    </div>
  );
};
