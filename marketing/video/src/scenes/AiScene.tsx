import { AbsoluteFill, Img, interpolate, spring, staticFile, useCurrentFrame, useVideoConfig } from "remotion";
import { Background } from "../components/Background";
import { BrowserFrame } from "../components/BrowserFrame";
import { C, inter, mono, sora } from "../theme";

const PROMPT = "Build a landing page for Aurora, an analytics app — hero, features, pricing and FAQ. Make it feel premium, with motion.";
const CALLS = [
  ["get_guide", "read the building guide"],
  ["list_modules", "90+ elements available"],
  ["create_page", "Aurora — Product analytics"],
  ["create_template", "header · sticky, shrink on scroll"],
  ["create_template", "footer"],
  ["update_node", "polish hero divider"],
];

// The prompt types out, tool calls stream in, then the page the AI built scrolls by.
export const AiScene: React.FC<{ compact?: boolean }> = ({ compact = false }) => {
  const f = useCurrentFrame();
  const { fps } = useVideoConfig();
  const typed = PROMPT.slice(0, Math.max(0, Math.floor((f - 10) * 1.6)));
  const promptDone = 10 + PROMPT.length / 1.6;
  const callStart = promptDone + 8;
  const browserIn = spring({ frame: f - (callStart + CALLS.length * 9), fps, config: { damping: 200 } });
  const imgH = 11390 / 2160; // height / width of the capture
  const bw = compact ? 860 : 980;
  const bh = compact ? 700 : 760;
  const scroll = interpolate(f, [callStart + CALLS.length * 9 + 20, callStart + CALLS.length * 9 + 200], [0, bw * imgH - (bh - 44)], { extrapolateLeft: "clamp", extrapolateRight: "clamp" });
  const termW = compact ? 620 : 740;
  return (
    <AbsoluteFill>
      <Background />
      {!compact && (
        <div style={{ position: "absolute", top: 70, width: "100%", textAlign: "center", fontFamily: sora, fontWeight: 700, fontSize: 64, letterSpacing: "-0.03em", color: C.text, opacity: interpolate(f, [0, 15], [0, 1], { extrapolateRight: "clamp" }) }}>
          Or just ask your AI.
        </div>
      )}
      <AbsoluteFill style={{ flexDirection: "row", alignItems: "center", justifyContent: "center", gap: 48, paddingTop: compact ? 0 : 90 }}>
        <div style={{ width: termW, height: bh, borderRadius: 18, background: "#0c0c12", border: `1px solid ${C.border}`, boxShadow: "0 40px 100px rgba(0,0,0,0.6)", overflow: "hidden", fontFamily: mono, fontSize: compact ? 19 : 21, color: C.text }}>
          <div style={{ height: 44, background: "#18181b", display: "flex", alignItems: "center", gap: 8, padding: "0 16px" }}>
            {["#ff5f57", "#febc2e", "#28c840"].map((c) => (
              <span key={c} style={{ width: 12, height: 12, borderRadius: 6, background: c }} />
            ))}
            <span style={{ marginLeft: 12, fontFamily: inter, fontSize: 15, color: C.muted }}>AI assistant · connected to brik (MCP)</span>
          </div>
          <div style={{ padding: 28, lineHeight: 1.55 }}>
            <div style={{ color: C.violet }}>›</div>
            <div style={{ whiteSpace: "pre-wrap", minHeight: 150 }}>
              {typed}
              {f < promptDone + 6 && <span style={{ opacity: Math.floor(f / 8) % 2 ? 0 : 1, color: C.cyan }}>▍</span>}
            </div>
            <div style={{ marginTop: 18, display: "flex", flexDirection: "column", gap: 12 }}>
              {CALLS.map(([tool, note], i) => {
                const t = f - (callStart + i * 9);
                if (t < 0) return null;
                const done = t > 12;
                const s = spring({ frame: t, fps, config: { damping: 200 } });
                return (
                  <div key={i} style={{ display: "flex", gap: 12, alignItems: "baseline", opacity: s, transform: `translateX(${(1 - s) * -20}px)` }}>
                    <span style={{ color: done ? C.green : C.muted, width: 22 }}>{done ? "✓" : "◌"}</span>
                    <span style={{ color: C.cyan }}>{tool}</span>
                    <span style={{ color: C.muted, fontFamily: inter, fontSize: compact ? 17 : 19 }}>{note}</span>
                  </div>
                );
              })}
            </div>
          </div>
        </div>
        <div style={{ opacity: browserIn, transform: `translateX(${(1 - browserIn) * 80}px) scale(${0.95 + browserIn * 0.05})` }}>
          <BrowserFrame width={bw} height={bh} url="brikwp.com/aurora">
            <Img src={staticFile("shots/aurora-full.jpg")} style={{ width: "100%", transform: `translateY(${-scroll}px)` }} />
          </BrowserFrame>
        </div>
      </AbsoluteFill>
    </AbsoluteFill>
  );
};

export const aiDuration = () => Math.round(10 + PROMPT.length / 1.6 + 8 + CALLS.length * 9 + 240);
