// Builds installable zips in dist/: brik-builder.zip and brikwp.zip.
import { execFileSync } from 'node:child_process';
import { mkdirSync, rmSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('..', import.meta.url));
const dist = `${root}dist`;
rmSync(dist, { recursive: true, force: true });
mkdirSync(dist);

execFileSync('npm', ['run', 'build'], { cwd: root, stdio: 'inherit' });

const zip = (cwd, name, folder, exclude = []) =>
  execFileSync('zip', ['-rq', `${dist}/${name}`, folder, '-x', '*.DS_Store', ...exclude], { cwd, stdio: 'inherit' });

zip(`${root}plugin`, 'brik-builder.zip', 'brik-builder', ['brik-builder/assets/src/*/_modules.*', 'brik-builder/tests/*']);
zip(`${root}theme`, 'brikwp.zip', 'brikwp');
console.log('dist/brik-builder.zip, dist/brikwp.zip');
