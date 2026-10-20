import http from 'node:http';
import { createHandler,passwordHash } from '../netlify/functions/lib/backend.mjs';
import { memoryStore } from './backend-memory.mjs';
const handler=createHandler({store:memoryStore(),env:{WEDDING_PUBLIC_URL:'http://127.0.0.1:5174',WEDDING_SESSION_SECRET:'local-preview-secret-only-0000000000000000',WEDDING_ADMIN_PASSWORD_HASH:passwordHash('Preview-Wedding-2026')}});
http.createServer(async(req,res)=>{try{const request=new Request(`http://127.0.0.1:8080${req.url}`,{method:req.method,headers:req.headers,...(!['GET','HEAD'].includes(req.method)?{body:req,duplex:'half'}:{})});const response=await handler(request,{ip:'127.0.0.1'});res.writeHead(response.status,Object.fromEntries(response.headers));res.end(Buffer.from(await response.arrayBuffer()));}catch{res.writeHead(500);res.end();}}).listen(8080,'127.0.0.1',()=>console.log('Test API on 127.0.0.1:8080'));
