import test from 'node:test';
import assert from 'node:assert/strict';
import {partnerCredentials} from '../../resources/js/partner-client-key.js';
function setup(handler){
 globalThis.document={querySelector:()=>({content:'csrf-test'})};globalThis.window={confirm:()=>true};globalThis.fetch=handler;
 return partnerCredentials({baseUrl:'/settings/partner-keys'});
}
test('creation uses session CSRF and keeps one-time credentials separate from metadata',async()=>{
 const requests=[];const urls=[];const ui=setup(async(url,options)=>{urls.push(url);requests.push(options);return{ok:true,status:options.method==='POST'?201:200,json:async()=>options.method==='POST'?{data:{id:1,name:'ERP'},token:'bearer-once',client_key:'client-once'}:{data:[{id:1,name:'ERP',client_key_configured:true}]}};});
 ui.name=' ERP ';await ui.create();assert.equal(ui.created.token,'bearer-once');assert.equal(ui.created.clientKey,'client-once');assert.equal(ui.keys[0].token,undefined);assert.equal(requests[0].headers['X-CSRF-TOKEN'],'csrf-test');assert.equal(requests[0].credentials,'same-origin');assert.equal(JSON.parse(requests[0].body).sandbox,true);assert.equal(ui.busy,false);assert.deepEqual(urls,['/settings/partner-keys','/settings/partner-keys']);
});
test('rotation does not pretend to recover the bearer token',async()=>{
 const ui=setup(async(_,options)=>({ok:true,status:201,json:async()=>options.method==='POST'?{data:{id:2,name:'Legacy'},client_key:'rotated-client'}:{data:[]}}));await ui.rotate({id:2,client_key_configured:false});assert.equal(ui.created.token,null);assert.equal(ui.created.clientKey,'rotated-client');
});
test('failed generation reports validation errors and never fabricates credentials',async()=>{
 const ui=setup(async()=>({ok:false,status:422,json:async()=>({errors:{name:['Nama wajib diisi.']}})}));ui.name='ERP';await ui.create();assert.equal(ui.created,null);assert.equal(ui.error,'Nama wajib diisi.');assert.equal(ui.busy,false);
});
test('revoking another row does not discard a newly created pair that has not yet been copied',async()=>{
 const ui=setup(async(_,options)=>({ok:true,status:options.method==='DELETE'?204:200,json:async()=>({data:[]})}));ui.created={id:1,token:'new',clientKey:'new-client'};await ui.revoke({id:2});assert.equal(ui.created.clientKey,'new-client');await ui.revoke({id:1});assert.equal(ui.created,null);
});
