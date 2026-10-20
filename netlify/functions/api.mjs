import { getStore } from '@netlify/blobs';
import { createHandler } from './lib/backend.mjs';
import { storeName } from './lib/deployment.mjs';
import { storageFetch } from './lib/storage-fetch.mjs';
let handler;
export default (request, context) => {
 handler ||= createHandler({store:getStore({name:storeName,consistency:'strong',fetch:storageFetch()})});
 return handler(request, context);
};
export const config = { path: '/api/*' };
