// Conditional writes in Blobs SDK v11 may report modified=true on HTTP errors.
// Throw before the SDK can treat an unsuccessful storage request as a save.
export function storageFetch(fetcher=globalThis.fetch){
 return async (input,init)=>{
  const response=await fetcher(input,init);
  const method=String(init?.method||(input instanceof Request?input.method:'GET')).toUpperCase();
  if(!response.ok&&!(response.status===404&&method==='GET')&&!(response.status===412&&method==='PUT')){
   await response.body?.cancel();
   throw new Error(`Storage request failed (${response.status})`);
  }
  return response;
 };
}
