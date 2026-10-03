// Dot pattern whose glow follows the pointer (the dots themselves are CSS).
import { on } from './_api.js';
import { settings, follow } from './_bg.js';

on('[data-brik-effect="dots"]', (el) => follow(el, settings(el)));
