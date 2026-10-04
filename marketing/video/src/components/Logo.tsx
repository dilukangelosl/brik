import { spring, useCurrentFrame, useVideoConfig } from "remotion";
import { C } from "../theme";

// Four bricks that fly in and lock together; the last one is solid.
export const Logo: React.FC<{ size?: number; delay?: number }> = ({ size = 120, delay = 0 }) => {
  const f = useCurrentFrame();
  const { fps } = useVideoConfig();
  const cell = size * 0.42;
  const gap = size * 0.1;
  const from = [
    [-1, -1],
    [1, -1],
    [-1, 1],
    [1, 1],
  ];
  return (
    <div style={{ width: size, height: size, borderRadius: size * 0.26, background: `linear-gradient(135deg, ${C.violet}, ${C.cyan})`, position: "relative", boxShadow: `0 0 ${size * 0.6}px rgba(139,92,246,0.55)`, transform: `scale(${spring({ frame: f - delay, fps, config: { damping: 14 } })})` }}>
      {from.map(([dx, dy], i) => {
        const s = spring({ frame: f - delay - 6 - i * 4, fps, config: { damping: 13, stiffness: 120 } });
        const x = (size - cell * 2 - gap) / 2 + (i % 2) * (cell + gap);
        const y = (size - cell * 2 - gap) / 2 + Math.floor(i / 2) * (cell + gap);
        return (
          <div
            key={i}
            style={{
              position: "absolute",
              left: x,
              top: y,
              width: cell,
              height: cell,
              borderRadius: cell * 0.28,
              border: `${size * 0.035}px solid #fff`,
              background: i === 3 ? "#fff" : "transparent",
              boxSizing: "border-box",
              opacity: s,
              transform: `translate(${dx * (1 - s) * size}px, ${dy * (1 - s) * size}px) rotate(${(1 - s) * 90 * dx}deg)`,
            }}
          />
        );
      })}
    </div>
  );
};
