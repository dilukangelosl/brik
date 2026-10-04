import { loadFont as loadSora } from "@remotion/google-fonts/Sora";
import { loadFont as loadInter } from "@remotion/google-fonts/Inter";
import { loadFont as loadMono } from "@remotion/google-fonts/JetBrainsMono";

export const sora = loadSora("normal", { weights: ["600", "700"], subsets: ["latin"] }).fontFamily;
export const inter = loadInter("normal", { weights: ["400", "500", "600"], subsets: ["latin"] }).fontFamily;
export const mono = loadMono("normal", { weights: ["400", "500"], subsets: ["latin"] }).fontFamily;

export const C = {
  bg: "#07070c",
  panel: "#0f0f17",
  border: "rgba(255,255,255,0.10)",
  text: "#f4f4f5",
  muted: "#a1a1aa",
  violet: "#8b5cf6",
  fuchsia: "#e879f9",
  cyan: "#22d3ee",
  green: "#4ade80",
};

export const GRADIENT = `linear-gradient(90deg, #a78bfa, ${C.fuchsia}, #67e8f9)`;

export const FPS = 30;
