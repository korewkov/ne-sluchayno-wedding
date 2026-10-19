import {cp,mkdir,readFile,writeFile} from 'node:fs/promises';
await mkdir('dist/private',{recursive:true});
for(const file of ['bootstrap.php','api.php','init.php','schema.sql','schema.mysql.sql','config.example.json'])await cp('server/'+file,'dist/private/'+file);
await cp('deploy/api.php','dist/public_html/api.php');await cp('deploy/.htaccess','dist/public_html/.htaccess');
console.log('Deployable files: dist/public_html + dist/private (no live credentials).');
