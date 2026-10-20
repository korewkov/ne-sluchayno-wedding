import { getStore } from '@netlify/blobs';
import { createHandler } from './lib/backend.mjs';
let handler;
export default (request, context) => {
 handler ||= createHandler({store:getStore({name:process.env.CONTEXT==='production'?'wedding-private-v1':`wedding-private-preview-${process.env.DEPLOY_ID||'local'}`,consistency:'strong'})});
 return handler(request, context);
};
export const config = { path: '/api/*' };
