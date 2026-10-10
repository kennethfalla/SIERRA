'use strict';
const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict'), path = require('node:path');
const read = name => fs.readFileSync(path.join(__dirname, '..', name), 'utf8');
let now = 0, tick, reply = {allowed:true,retry_after:0,message:''}, calls = 0;
const button = {innerHTML:'Submit Report',setAttribute(){}}, node = {};
const context = {window:{addEventListener(){}},document:{addEventListener(){},removeEventListener(){}},Date:{now:()=>now},
    setInterval(fn){tick=fn;return 1;},clearInterval(){},fetch:async()=>{calls++;return {ok:true,json:async()=>reply};}};
vm.runInNewContext(read('assets/js/report-submission.js'),context);
const gate = context.window.SierraReportSubmission({button,node,url:'/fixture',initial:{allowed:false,retry_after:3,message:'Wait'}});
async function flush(){for(let i=0;i<15;i++)await Promise.resolve();}
(async()=>{
    assert.equal(button.disabled,true); assert.match(node.textContent,/00:03/);
    now=2000;tick();assert.match(node.textContent,/00:01/);
    now=3000;tick();await flush();assert.equal(calls,1);assert.equal(gate.canSubmit(),true);assert.equal(button.disabled,false);
    gate.setBusy(true);assert.equal(gate.canSubmit(),false);assert.equal(button.disabled,true);
    gate.setBusy(false);assert.equal(button.disabled,false);
    reply={allowed:false,retry_after:120,message:'Updated limit'};await gate.refresh();assert.equal(button.disabled,true);assert.match(node.textContent,/02:00/);
    const mapContext={window:{SierraMapSettings:{default_lat:0,default_lng:0,default_zoom:17,clustering_radius_meters:125}}};
    vm.runInNewContext(read('assets/js/map-layers.js'),mapContext);
    const mapSettings=mapContext.window.MapLayers.getSettings();assert.equal(mapSettings.default_lat,0);assert.equal(mapSettings.default_zoom,17);assert.equal(mapSettings.clustering_radius_meters,125);
    const manageSource=read('views/shared/manage_report.php');
    const reasonCode=manageSource.slice(manageSource.indexOf('function syncReportReason(field)'),manageSource.indexOf('function submitReportReason'));
    const choices=[{value:'The location or details could not be verified.',checked:true},{value:'Other',checked:false}];
    const customWrap={hidden:true};
    const custom={value:'',required:false,disabled:true,closest:()=>customWrap,focus(){}},hidden={value:''};
    const form={querySelector(selector){
        if(selector==='[name="custom_reason"]')return custom;
        if(selector==='[name="rejection_reason"]')return hidden;
        if(selector==='[name="reason_choice"][value="Other"]')return choices[1];
        return choices.slice().reverse().find(choice=>choice.checked);
    }};
    custom.form=form;choices.forEach(choice=>choice.form=form);
    const reasonContext={};vm.runInNewContext(reasonCode,reasonContext);
    reasonContext.syncReportReason(choices[0]);assert.equal(hidden.value,choices[0].value);assert.equal(custom.required,false);assert.equal(customWrap.hidden,true);assert.equal(custom.disabled,true);
    choices[0].checked=false;choices[1].checked=true;reasonContext.syncReportReason(choices[1]);assert.equal(customWrap.hidden,false);assert.equal(custom.disabled,false);assert.equal(custom.required,true);
    custom.value='Report submitted by mistake';reasonContext.syncReportReason(custom);assert.equal(hidden.value,custom.value);assert.equal(custom.required,true);
    choices[1].checked=false;choices[0].checked=true;reasonContext.syncReportReason(choices[0]);assert.equal(custom.value,'');assert.equal(hidden.value,choices[0].value);assert.equal(customWrap.hidden,true);assert.equal(custom.disabled,true);
    // Compile inline scripts after replacing their server-provided values.
    for(const name of ['views/citizen/submit_report.php','views/citizen/my_reports.php','views/auth/register.php','views/shared/manage_report.php','views/citizen/track_status.php','views/index.php']){
        let source=read(name).replace(/<\?php if\(\$isLoggedIn\): \?>([\s\S]*?)<\?php else: \?>([\s\S]*?)<\?php endif; \?>/g,'$1').replace(/<\?php[\s\S]*?\?>/g,'0');
        for(const match of source.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g))new vm.Script(match[1],{filename:name});
    }
    for(const name of ['assets/js/export-print.js','assets/js/dashboard-layout.js','assets/js/map.js','assets/js/hazard-map.js'])new vm.Script(read(name),{filename:name});
    console.log('Countdown unlock, loading guard, live limits, map settings, suggested/other reasons, and JavaScript syntax passed.');
})().catch(error=>{console.error(error);process.exitCode=1;});
