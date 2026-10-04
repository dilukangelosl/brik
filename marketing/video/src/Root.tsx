import { Composition } from "remotion";
import { TransitionSeries, linearTiming, springTiming } from "@remotion/transitions";
import { fade } from "@remotion/transitions/fade";
import { slide } from "@remotion/transitions/slide";
import { LightLeak } from "@remotion/light-leaks";
import { FPS } from "./theme";
import { Intro } from "./scenes/Intro";
import { Headline } from "./scenes/Headline";
import { BuilderDemo, builderDuration } from "./scenes/BuilderDemo";
import { EffectsWall } from "./scenes/EffectsWall";
import { AiScene, aiDuration } from "./scenes/AiScene";
import { Commerce } from "./scenes/Commerce";
import { Outro } from "./scenes/Outro";

const T = 18; // frames per transition

const scenes = [
  { c: Intro, d: 80 },
  { c: Headline, d: 110 },
  { c: BuilderDemo, d: builderDuration(FPS) },
  { c: EffectsWall, d: 170 },
  { c: AiScene, d: aiDuration() },
  { c: Commerce, d: 160 },
  { c: Outro, d: 150 },
];

const LAUNCH = scenes.reduce((a, s) => a + s.d, 0) - T * (scenes.length - 1);

const Launch: React.FC = () => (
  <TransitionSeries>
    {scenes.map(({ c: Scene, d }, i) => {
      const items = [
        <TransitionSeries.Sequence key={`s${i}`} durationInFrames={d}>
          <Scene />
        </TransitionSeries.Sequence>,
      ];
      if (i < scenes.length - 1) {
        items.push(
          <TransitionSeries.Transition
            key={`t${i}`}
            presentation={i % 2 ? slide({ direction: "from-right" }) : fade()}
            timing={i % 2 ? springTiming({ config: { damping: 200 }, durationInFrames: T }) : linearTiming({ durationInFrames: T })}
          />
        );
      }
      return items;
    })}
  </TransitionSeries>
);

// A light leak over the intro so the opening has some warmth.
const LaunchWithLeak: React.FC = () => (
  <>
    <Launch />
    <div style={{ position: "absolute", inset: 0, mixBlendMode: "screen", pointerEvents: "none" }}>
      <LightLeak durationInFrames={60} seed={3} hueShift={250} />
    </div>
  </>
);

export const RemotionRoot: React.FC = () => (
  <>
    <Composition id="Launch" component={LaunchWithLeak} durationInFrames={LAUNCH} fps={FPS} width={1920} height={1080} />
    <Composition id="BuilderLoop" component={() => <BuilderDemo frameWidth={1440} />} durationInFrames={builderDuration(FPS)} fps={FPS} width={1600} height={1000} />
    <Composition id="AiLoop" component={() => <AiScene compact />} durationInFrames={aiDuration()} fps={FPS} width={1600} height={1000} />
  </>
);
