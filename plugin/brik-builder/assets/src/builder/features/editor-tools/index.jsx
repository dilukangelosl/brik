// Builder feature: editor tools — responsive timeline, fluid values, breakpoint simulator,
// "why is this broken?" diagnosis, CSS inspector, designer/developer modes and export.
import * as store from '../../store.js';
import { addSlot, registerPanel, registerModal } from '../../registry.js';
import { Timeline, Fluid, FluidTypography } from './Responsive.jsx';
import { SimulatorButton, SimulatorOverlay } from './Simulator.jsx';
import { DiagnoseButton, diagnoseMenuItem } from './Diagnose.jsx';
import { InspectPanel } from './Inspector.jsx';
import { ModeToggle, CodeButton, CodeModal, DevToolsModal, readMode, applyMode, startTagging } from './DevMode.jsx';
import { MoreMenu, ExportModal, ConvertedBanner } from './Export.jsx';
import { injectStyle } from './util.js';

function NodeTools({ node, def }) {
  return (
    <div className="flex flex-wrap items-center gap-1.5" data-bk-node-tools>
      <DiagnoseButton node={node} />
      <FluidTypography node={node} def={def} />
      <CodeButton node={node} />
    </div>
  );
}

function FieldTools(props) {
  return (
    <>
      <Fluid {...props} />
      <Timeline {...props} />
    </>
  );
}

addSlot('fieldLabel', FieldTools, 5);
addSlot('nodeHeader', NodeTools, 20);
addSlot('topBarCenter', SimulatorButton, 10);
addSlot('canvasOverlay', SimulatorOverlay, 10);
addSlot('canvasOverlay', ConvertedBanner, 20);
addSlot('topBarRight', ModeToggle, 20);
addSlot('topBarRight', MoreMenu, 90);
addSlot('contextMenu', diagnoseMenuItem, 10);

registerPanel({ id: 'inspect', label: 'Inspect', icon: 'scan-search', wide: true, component: InspectPanel, order: 40 });
registerModal('bk-code', CodeModal);
registerModal('bk-devtools', DevToolsModal);
registerModal('bk-export', ExportModal);

// Designer mode hides developer-only parts (tagged with data-brik-dev).
injectStyle(
  'bk-editor-tools',
  `body.bk-designer [data-brik-dev]{display:none!important}
.bk-timeline button{transition:background-color .12s,color .12s}`
);

const mode = readMode();
applyMode(mode);
store.setState({ bkMode: mode });

function boot() {
  const app = document.getElementById('brik-app');
  if (app) startTagging();
  else setTimeout(boot, 50);
}
boot();
