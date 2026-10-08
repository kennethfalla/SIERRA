'use strict';
const fs=require('node:fs'), vm=require('node:vm'), assert=require('node:assert/strict');
const source=fs.readFileSync(require('node:path').join(__dirname,'../assets/js/theme.js'),'utf8');
function runtime(saved,deviceDark=false,blocked=false,embeddedPage=null) {
    const listeners={}, events={}, media={matches:deviceDark,addEventListener:(_,fn)=>media.change=fn};
    const store={value:saved,getItem(){if(blocked)throw Error('blocked');return this.value;},setItem(_,v){if(blocked)throw Error('blocked');this.value=v;}};
    const classes=new Set();
    const document={readyState:'loading',documentElement:{dataset:{},style:{},classList:{add:value=>classes.add(value)}},querySelectorAll:()=>[],dispatchEvent(){},addEventListener:(type,fn)=>listeners[type]=fn};
    const window={matchMedia:()=>media,addEventListener:(type,fn)=>events[type]=fn};
    if(embeddedPage){window.frameElement={hasAttribute:()=>true};window.location={href:'https://example.test/index.php?page='+embeddedPage};window.top={location:{replace:url=>window.redirect=url}};}
    vm.runInNewContext(source,{window,document,localStorage:store,URL,CustomEvent:function(){}});
    return {window,document,events,media,store,classes};
}
let app=runtime('dark');
assert.equal(app.document.documentElement.dataset.theme,'dark','saved choice is applied before DOM readiness');
app.window.SierraTheme.set('light');assert.equal(app.store.value,'light');assert.equal(app.document.documentElement.dataset.theme,'light');
app.window.SierraTheme.set('system');app.media.matches=true;app.media.change();assert.equal(app.document.documentElement.dataset.theme,'dark');
app.window.SierraTheme.set('light');app.media.change();assert.equal(app.document.documentElement.dataset.theme,'light','explicit choice takes priority over device changes');
app.events.storage({key:'sierra-theme',newValue:'dark'});assert.equal(app.document.documentElement.dataset.theme,'dark','other tabs and auth frames stay synchronized');
app.events.beforeprint();assert.equal(app.document.documentElement.dataset.theme,'light');app.events.afterprint();assert.equal(app.document.documentElement.dataset.theme,'dark');
assert.equal(app.window.SierraTheme.get(),'dark','printing does not change the saved preference');
app=runtime('invalid',true);assert.equal(app.window.SierraTheme.get(),'system');assert.equal(app.document.documentElement.dataset.theme,'dark');
app=runtime(null,false,true);app.window.SierraTheme.set('dark');assert.equal(app.document.documentElement.dataset.theme,'dark','theme still works when storage is unavailable');
app=runtime('dark',false,false,'login');assert.equal(app.classes.has('auth-embedded'),true,'account pages use the popup layout');
app=runtime('dark',false,false,'dashboard');assert.equal(app.window.redirect,'https://example.test/index.php?page=dashboard');assert.equal(app.classes.has('auth-embedded'),false,'workspace redirects before inheriting popup sizing');
console.log('PASS initial appearance, persistence, system preference, tab sync, printing and blocked storage');
