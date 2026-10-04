// Builder feature: performance score, findings and "Clean page".
import { registerPanel, registerModal, addSlot } from '../../registry.js';
import { PerformancePanel, CleanDialog, PerfBadge } from './Panel.jsx';
import { watch } from './state.js';

registerPanel({ id: 'performance', label: 'Performance', icon: 'gauge', component: PerformancePanel, order: 45 });
registerModal('perf-clean', CleanDialog);
addSlot('topBarRight', PerfBadge, 4);

watch();
