import {cp,mkdir,readdir,readFile,writeFile,stat} from 'node:fs/promises';
import {execFileSync} from 'node:child_process';
const prefix='/ne-sluchayno-wedding/';
execFileSync(process.execPath,['node_modules/vite/bin/vite.js','build','--config','vite.standalone.ts'],{stdio:'inherit'});
await mkdir('docs',{recursive:true});
await cp('dist/public_html','docs',{recursive:true});
async function patch(dir){for(const e of await readdir(dir,{withFileTypes:true})){const p=dir+'/'+e.name;if(e.isDirectory())await patch(p);else if(/\.(html|css|js)$/.test(p)){let s=await readFile(p,'utf8');for(const part of ['media','fonts','assets'])s=s.replaceAll('/'+part+'/',prefix+part+'/');s=s.replaceAll('href="/favicon.svg"','href="'+prefix+'favicon.svg"');await writeFile(p,s);}}}
await patch('docs');
await writeFile('docs/.nojekyll','');
console.log('GitHub Pages preview ready: docs/. PHP backend must be hosted separately.');
