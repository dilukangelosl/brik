// 3D marquee: the motion itself is CSS; this only parks the animations while off screen.
import { on } from './_api.js';
import { pauseOffscreen } from './_3d.js';

on('.brik-m3d', (el) => pauseOffscreen(el));
