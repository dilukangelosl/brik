// Spotlight: CSS beams sweep in once, a soft pool of light then follows the pointer.
import { on } from './_api.js';
import { settings, follow } from './_bg.js';

on('[data-brik-effect="spotlight"]', (el) => follow(el, settings(el)));
