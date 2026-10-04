// Builder feature: design system (variables, CSS classes, components, design tree).
import { addSlot, registerPanel, registerModal } from '../../registry.js';
import { useStore, setState, openModal } from '../../store.js';
import { IconButton } from '../../ui.jsx';
import { DesignPanel } from './DesignPanel.jsx';
import { ClassChips, ClassEditorHost } from './Classes.jsx';
import { FieldVariableButton } from './VariablePicker.jsx';
import { SaveComponentModal, InstancePanel, MasterBanner, PolicyToggle } from './Components.jsx';
import { editingComponent } from './data.js';

registerPanel({ id: 'design-system', label: 'Design', icon: 'swatch-book', wide: true, tab: false, component: DesignPanel, order: 40 });
registerModal('ds-save-component', SaveComponentModal);

function DesignButton() {
  const left = useStore((s) => s.left);
  return <IconButton icon="swatch-book" label="Design system: tokens, variables, classes, components" active={left === 'design-system'} onClick={() => setState({ left: left === 'design-system' ? null : 'design-system' })} />;
}
addSlot('topBarLeft', DesignButton, 5);

// The class editor docks over the settings panel; the host is always mounted.
addSlot('canvasOverlay', ClassEditorHost);

function NodeHeader({ node }) {
  const master = editingComponent();
  if (node.type === 'global') {
    return <InstancePanel node={node} />;
  }
  return (
    <div className="space-y-2">
      {master && <MasterBanner />}
      <div className="flex items-start gap-1.5">
        <div className="min-w-0 flex-1">
          <ClassChips node={node} />
        </div>
        {!master && node.type !== 'column' && (
          <IconButton icon="component" size="icon-sm" label="Save as component" className="-my-0.5 shrink-0 text-muted-foreground hover:text-violet-600" onClick={() => openModal('ds-save-component', { id: node.id })} />
        )}
      </div>
    </div>
  );
}
addSlot('nodeHeader', NodeHeader);

addSlot('fieldLabel', PolicyToggle, 5);
addSlot('fieldLabel', FieldVariableButton);

addSlot('contextMenu', {
  icon: 'component',
  label: 'Save as component',
  action: (node) => openModal('ds-save-component', { id: node.id }),
  when: (node) => !['global', 'column'].includes(node.type) && !editingComponent(),
});
