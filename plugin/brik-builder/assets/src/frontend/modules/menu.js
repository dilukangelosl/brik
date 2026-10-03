import { on } from '../core.js';
import { setupDesktop } from './nav/desktop.js';
import { setupMobile } from './nav/mobile.js';

on('[data-brik-menu]', (nav) => {
  setupDesktop(nav);
  setupMobile(nav);
  // Marks the menu as scripted: CSS hands hover and focus handling over to the script.
  nav.dataset.ready = '';
});
