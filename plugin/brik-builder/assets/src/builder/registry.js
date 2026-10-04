// Extension points for builder features. Feature modules (features/*) register here;
// the core UI renders whatever is registered.
import { h } from './jsx.js';

const slots = {
  topBarLeft: [], // components rendered after the panel buttons
  topBarCenter: [], // next to the device switcher
  topBarRight: [], // before undo/redo
  nodeHeader: [], // under the element title in the settings panel: ({ node, def }) => …
  nodeFooter: [], // bottom of the settings panel
  fieldLabel: [], // small buttons in a field's label row: ({ node, fkey, field }) => …
  textAddons: [], // buttons next to text inputs: ({ value, onChange, field }) => …
  contextMenu: [], // { icon, label, action(node), when?(node) }
  canvasOverlay: [], // components drawn over the canvas area
};

const controls = {};
const modals = {};
const panels = [];

export function addSlot(name, item, order = 10) {
  slots[name].push({ item, order });
  slots[name].sort((a, b) => a.order - b.order);
}

export function getSlot(name) {
  return slots[name].map((s) => s.item);
}

/** Render every component registered for a slot. */
export function Slot({ name, ...props }) {
  return getSlot(name).map((C, i) => h(C, { key: i, ...props }));
}

export function registerControl(type, component) {
  controls[type] = component;
}

export function getControl(type) {
  return controls[type];
}

export function registerModal(type, component) {
  modals[type] = component;
}

export function getModal(type) {
  return modals[type];
}

/** Left panel tab: { id, label, icon, component, order }. */
export function registerPanel(panel) {
  panels.push(panel);
  panels.sort((a, b) => (a.order || 10) - (b.order || 10));
}

export function getPanels() {
  return panels;
}
