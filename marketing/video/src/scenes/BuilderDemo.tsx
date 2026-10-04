import { AbsoluteFill, interpolate, Sequence, staticFile, useCurrentFrame, useVideoConfig } from "remotion";
import { Video } from "@remotion/media";
import { Background } from "../components/Background";
import { BrowserFrame } from "../components/BrowserFrame";
import { Callout } from "../components/Callout";

// Cuts from the real screen recording: [start s, end s, speed, caption].
export const SEGMENTS: [number, number, number, string][] = [
  [6, 14, 1.8, "Drop in a ready-made section"],
  [22, 30, 1.4, "Edit text right on the page"],
  [66, 78, 2, "Stack sections in seconds"],
  [80, 92, 2, "Restyle the whole site at once"],
  [94, 102, 1.8, "Every breakpoint, live"],
  [108, 115, 1.6, "Publish"],
];

export const segmentFrames = (fps: number) => SEGMENTS.map(([a, b, r]) => Math.round(((b - a) * fps) / r));
export const builderDuration = (fps: number) => segmentFrames(fps).reduce((x, y) => x + y, 0);

export const BuilderDemo: React.FC<{ frameWidth?: number; showBackground?: boolean }> = ({ frameWidth = 1400, showBackground = true }) => {
  const f = useCurrentFrame();
  const { fps, height } = useVideoConfig();
  const frames = segmentFrames(fps);
  const tilt = interpolate(f, [0, 30], [14, 0], { extrapolateRight: "clamp" });
  const scale = interpolate(f, [0, 30], [0.9, 1], { extrapolateRight: "clamp" });
  const h = (frameWidth * 1000) / 1600 + 44;
  let from = 0;
  return (
    <AbsoluteFill>
      {showBackground && <Background intensity={0.8} />}
      <AbsoluteFill style={{ alignItems: "center", justifyContent: "center", perspective: 1800 }}>
        <div style={{ transform: `rotateX(${tilt}deg) scale(${scale})`, marginTop: height > 900 ? -30 : 0 }}>
          <BrowserFrame width={frameWidth} height={h} url="brikwp.com/wp-admin · Brik builder">
            {SEGMENTS.map(([a, b, r], i) => {
              const start = from;
              from += frames[i];
              return (
                <Sequence key={i} from={start} durationInFrames={frames[i]} layout="none">
                  <Video src={staticFile("builder.mp4")} trimBefore={a * fps} trimAfter={b * fps} playbackRate={r} muted style={{ position: "absolute", inset: 0, width: "100%", height: "100%", objectFit: "cover" }} />
                </Sequence>
              );
            })}
          </BrowserFrame>
        </div>
      </AbsoluteFill>
      {(() => {
        let start = 0;
        return SEGMENTS.map(([, , , text], i) => {
          const s = start;
          start += frames[i];
          return (
            <Sequence key={`c${i}`} from={s + 4} durationInFrames={frames[i] - 4}>
              <AbsoluteFill style={{ alignItems: "center", justifyContent: "flex-end", paddingBottom: height > 900 ? 40 : 24 }}>
                <Callout step={String(i + 1)} text={text} duration={frames[i] - 4} />
              </AbsoluteFill>
            </Sequence>
          );
        });
      })()}
    </AbsoluteFill>
  );
};
