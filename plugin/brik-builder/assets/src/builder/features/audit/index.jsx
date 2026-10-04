// Builder feature: quality audits (accessibility, SEO structure, responsive problems).
import { registerPanel, addSlot } from '../../registry.js';
import * as store from '../../store.js';
import { AuditPanel, AuditBadge } from './Panel.jsx';
import { setAudit, run, startAuto } from './engine.js';

registerPanel({ id: 'audit', label: 'Audit', icon: 'shield-check', component: AuditPanel, order: 40, wide: true });

addSlot('topBarRight', AuditBadge, 5);

addSlot('contextMenu', {
  icon: 'shield-check',
  label: 'Audit this element',
  action(node) {
    setAudit((s) => ({ scope: node.id, tab: s.tab === 'responsive' ? 'a11y' : s.tab }));
    store.setState({ left: 'audit' });
    run({ full: true });
  },
});

startAuto();
