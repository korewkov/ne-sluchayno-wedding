import {writeFileSync,mkdirSync} from 'node:fs';
const directory=new URL('../netlify/functions/lib/',import.meta.url);
mkdirSync(directory,{recursive:true});
const production=process.env.CONTEXT==='production';
const deployment=process.env.DEPLOY_ID||'local';
const storeName=production?'wedding-private-v1':`wedding-private-preview-${deployment}`;
writeFileSync(new URL('deployment.mjs',directory),`// Generated during build; runtime does not expose CONTEXT.\nexport const storeName=${JSON.stringify(storeName)};\n`);
