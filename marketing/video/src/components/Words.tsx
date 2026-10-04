import { spring, useCurrentFrame, useVideoConfig } from "remotion";

// Words rise into place one after another.
export const Words: React.FC<{ text: string; delay?: number; stagger?: number; style?: React.CSSProperties; wordStyle?: (i: number) => React.CSSProperties }> = ({ text, delay = 0, stagger = 4, style, wordStyle }) => {
  const f = useCurrentFrame();
  const { fps } = useVideoConfig();
  return (
    <span style={{ display: "inline-flex", flexWrap: "wrap", justifyContent: "center", columnGap: "0.28em", ...style }}>
      {text.split(" ").map((w, i) => {
        const s = spring({ frame: f - delay - i * stagger, fps, config: { damping: 18, stiffness: 140 } });
        return (
          <span key={i} style={{ display: "inline-block", opacity: Math.min(1, s * 1.4), transform: `translateY(${(1 - s) * 60}px)`, filter: `blur(${(1 - Math.min(1, s)) * 8}px)`, ...(wordStyle ? wordStyle(i) : {}) }}>
            {w}
          </span>
        );
      })}
    </span>
  );
};
