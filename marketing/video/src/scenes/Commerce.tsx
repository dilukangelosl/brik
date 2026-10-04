import { AbsoluteFill, Img, interpolate, spring, staticFile, useCurrentFrame, useVideoConfig } from "remotion";
import { Background } from "../components/Background";
import { BrowserFrame } from "../components/BrowserFrame";
import { C, inter, sora } from "../theme";

const CHIPS = ["WooCommerce", "Theme builder", "Mega menus", "Custom post types", "Listings & filters", "Forms that create posts"];

export const Commerce: React.FC = () => {
  const f = useCurrentFrame();
  const { fps } = useVideoConfig();
  const a = spring({ frame: f - 6, fps, config: { damping: 200 } });
  const b = spring({ frame: f - 16, fps, config: { damping: 200 } });
  const drift = interpolate(f, [0, 180], [0, -40]);
  return (
    <AbsoluteFill>
      <Background />
      <div style={{ position: "absolute", top: 80, width: "100%", textAlign: "center", fontFamily: sora, fontWeight: 700, fontSize: 72, letterSpacing: "-0.03em", color: C.text }}>Stores, content, everything.</div>
      <AbsoluteFill style={{ alignItems: "center", justifyContent: "center", paddingTop: 60 }}>
        <div style={{ position: "relative", width: 1500, height: 640 }}>
          <div style={{ position: "absolute", left: 0, top: 40 + drift, opacity: a, transform: `translateY(${(1 - a) * 60}px) rotate(-2deg)` }}>
            <BrowserFrame width={820} height={560} url="brikwp.com/product/hoodie">
              <Img src={staticFile("shots/product.jpg")} style={{ width: "100%" }} />
            </BrowserFrame>
          </div>
          <div style={{ position: "absolute", right: 0, top: 0 - drift, opacity: b, transform: `translateY(${(1 - b) * 60}px) rotate(2deg)` }}>
            <BrowserFrame width={820} height={560} url="brikwp.com/shop">
              <Img src={staticFile("shots/shop.jpg")} style={{ width: "100%" }} />
            </BrowserFrame>
          </div>
        </div>
      </AbsoluteFill>
      <div style={{ position: "absolute", bottom: 70, width: "100%", display: "flex", justifyContent: "center", gap: 16, flexWrap: "wrap" }}>
        {CHIPS.map((c, i) => {
          const s = spring({ frame: f - 30 - i * 5, fps, config: { damping: 200 } });
          return (
            <span key={c} style={{ fontFamily: inter, fontSize: 26, color: C.text, padding: "12px 24px", borderRadius: 999, border: `1px solid ${C.border}`, background: "rgba(255,255,255,0.05)", opacity: s, transform: `translateY(${(1 - s) * 20}px)` }}>
              {c}
            </span>
          );
        })}
      </div>
    </AbsoluteFill>
  );
};
