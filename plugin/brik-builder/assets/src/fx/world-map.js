// World map: arcs start drawing once the map is first seen and pause while it is off screen.
import { on } from './_api.js';
import { pauseOffscreen } from './_3d.js';

on('.brik-wm', (el) => pauseOffscreen(el));
