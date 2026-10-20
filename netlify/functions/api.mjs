import { getStore } from '@netlify/blobs';
import { createHandler } from './lib/backend.mjs';
import { storeName } from './lib/deployment.mjs';
let handler;
export default (request, context) => {
 handler ||= createHandler({store:getStore({name:storeName,consistency:'strong'})});
 return handler(request, context);
};
export const config = { path: '/api/*' };
