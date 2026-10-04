// Builder feature: data — dynamic data picker, visual query builder and display conditions.
import { registerControl, registerModal, addSlot } from '../../registry.js';
import { DataPickerModal, TextAddon, FieldLabelButton } from './DataPicker.jsx';
import { QueryControl, QueryDialog } from './QueryBuilder.jsx';
import { ConditionsControl, ConditionsDialog, ConditionsSection, CanvasBadges } from './Conditions.jsx';

registerModal('brik-data-picker', DataPickerModal);
registerModal('brik-query-builder', QueryDialog);
registerModal('brik-conditions', ConditionsDialog);

registerControl('query', QueryControl);
registerControl('conditions', ConditionsControl);

addSlot('textAddons', TextAddon);
addSlot('fieldLabel', FieldLabelButton);
addSlot('nodeFooter', ConditionsSection);
addSlot('canvasOverlay', CanvasBadges);
