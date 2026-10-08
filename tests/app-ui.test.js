'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname,'../assets/js/app-ui.js'),'utf8');
const listeners={},windowEvents={},timers=[];
function control(tag='BUTTON') {
    const attrs={}, classes=new Set();
    return {tagName:tag,isConnected:true,disabled:false,style:{},dataset:{},textContent:'Save changes',
        classList:{add:c=>classes.add(c),remove:c=>classes.delete(c),contains:c=>classes.has(c)},
        getAttribute:k=>Object.hasOwn(attrs,k)?attrs[k]:null,setAttribute:(k,v)=>attrs[k]=v,removeAttribute:k=>delete attrs[k],
        hasAttribute:k=>Object.hasOwn(attrs,k),closest(){return this;}};
}
const badges=['reports','notifications','announcements'].map(key=>Object.assign(control('SPAN'),{dataset:{sidebarCount:key}}));
const document={readyState:'loading',addEventListener:(type,fn)=>listeners[type]=fn,querySelectorAll:()=>badges};
let requestResolve,requestReject,submits=0;
function Form() {this.target='';this.button=control();}
Form.prototype.querySelector=function(){return this.button;};
Form.prototype.submit=function(){submits++;};
const window={addEventListener:(type,fn)=>windowEvents[type]=fn,fetch:()=>new Promise((resolve,reject)=>{requestResolve=resolve;requestReject=reject;})};
const context={window,document,HTMLFormElement:Form,Map,Promise,URL,location:{href:'https://example.test/index.php',origin:'https://example.test',pathname:'/index.php',search:''},setTimeout:fn=>timers.push(fn)};
vm.runInNewContext(source,context);
async function flush(){for(let i=0;i<12;i++)await Promise.resolve();}
async function tick(){while(timers.length){timers.shift()();await flush();}}
function click(button){const event={target:button,button:0,preventDefault(){this.defaultPrevented=true;},stopImmediatePropagation(){this.stopped=true;}};listeners.click(event);return event;}
(async()=>{
    const button=control();click(button);
    // Browsers can drain microtasks between capture and the page's click handler.
    await flush();
    const request=window.fetch('/save');await flush();
    assert.equal(button.getAttribute('aria-busy'),'true');
    assert.equal(button.textContent,'Save changes');assert.equal(button.disabled,false,'loading must not alter submitted controls');
    assert.equal(click(button).stopped,true,'pending actions block repeated clicks');
    let bodyResolve;
    requestResolve({json:()=>new Promise(resolve=>bodyResolve=resolve)});
    const response=await request;const body=response.json();await tick();
    assert.equal(button.classList.contains('app-is-loading'),true,'keep loading while the response body is still arriving');
    bodyResolve({success:true,sidebar_counts:{reports:2,notifications:4,announcements:1}});await body;await tick();
    assert.equal(button.getAttribute('aria-busy'),null);
    assert.equal(badges[0].textContent,'2');assert.equal(badges[2].textContent,'1');
    click(button);const failed=window.fetch('/save').catch(e=>e);await flush();requestReject(new Error('Offline'));await failed;await tick();
    assert.equal(button.classList.contains('app-is-loading'),false,'errors release the button for retry');
    const form=new Form();listeners.submit({target:form,submitter:form.button,defaultPrevented:true});await flush();await tick();
    assert.equal(form.button.classList.contains('app-is-loading'),false,'cancelled validation cannot leave a spinner');
    form.submit();assert.equal(submits,1);assert.equal(form.button.classList.contains('app-is-loading'),true);
    windowEvents.pageshow();assert.equal(form.button.classList.contains('app-is-loading'),false,'back navigation resets pending states');
    window.SierraUI.updateSidebarCounts({reports:0,notifications:125,announcements:0});
    assert.equal(badges[0].hidden,true);assert.equal(badges[1].textContent,'99+');assert.equal(badges[2].hidden,true);
    const unrelated=window.fetch('/background');requestResolve({json:async()=>({})});await unrelated;await tick();
    assert.equal(button.classList.contains('app-is-loading'),false,'background polling is not tied to a previously clicked button');
    console.log('PASS loading lifetime, retry, validation, native submit, back navigation and sidebar counters');
})().catch(error=>{console.error(error);process.exitCode=1;});
