import { C, inter } from "../theme";

export const BrowserFrame: React.FC<{ width: number; height: number; url?: string; children: React.ReactNode; style?: React.CSSProperties }> = ({ width, height, url = "brikwp.com", children, style }) => (
  <div
    style={{
      width,
      height,
      borderRadius: 18,
      overflow: "hidden",
      background: "#fff",
      boxShadow: `0 0 0 1px ${C.border}, 0 40px 120px -20px rgba(124,58,237,0.45), 0 20px 60px rgba(0,0,0,0.5)`,
      display: "flex",
      flexDirection: "column",
      ...style,
    }}
  >
    <div style={{ height: 44, flex: "none", background: "#18181b", display: "flex", alignItems: "center", gap: 8, padding: "0 16px" }}>
      {["#ff5f57", "#febc2e", "#28c840"].map((c) => (
        <span key={c} style={{ width: 12, height: 12, borderRadius: 6, background: c }} />
      ))}
      <div style={{ flex: 1, display: "flex", justifyContent: "center" }}>
        <div style={{ fontFamily: inter, fontSize: 15, color: C.muted, background: "#27272a", borderRadius: 8, padding: "5px 60px" }}>{url}</div>
      </div>
      <span style={{ width: 52 }} />
    </div>
    <div style={{ position: "relative", flex: 1, overflow: "hidden" }}>{children}</div>
  </div>
);
