'use strict';
const fs=require('node:fs'), vm=require('node:vm'), assert=require('node:assert/strict');
const source=fs.readFileSync(require('node:path').join(__dirname,'../assets/js/theme.js'),'utf8');
function runtime(saved,deviceDark=false,blocked=false,embeddedPage=null,phone=false) {
    const listeners={}, events={}, media={matches:deviceDark,addEventListener:(_,fn)=>media.change=fn};
    const store={value:saved,removeItem(){if(blocked)throw Error("blocked");this.value=null;},getItem(){if(blocked)throw Error('blocked');return this.value;},setItem(_,v){if(blocked)throw Error('blocked');this.value=v;}};
    const classes=new Set();
    const viewport={content:'width=device-width, initial-scale=1',getAttribute(){return this.content;},setAttribute(_,value){this.content=value;}};
    const document={readyState:'loading',documentElement:{dataset:{},style:{},classList:{add:value=>classes.add(value)}},querySelector:()=>viewport,querySelectorAll:()=>[],dispatchEvent(){},addEventListener:(type,fn)=>listeners[type]=fn};
    const window={screen:{width:phone?390:1440,height:900},matchMedia:q=>q==='(pointer: coarse)'?{matches:phone}:media,addEventListener:(type,fn)=>events[type]=fn};
    if(embeddedPage){window.frameElement={hasAttribute:()=>true};window.location={href:'https://example.test/index.php?page='+embeddedPage};window.top={location:{replace:url=>window.redirect=url}};}
    vm.runInNewContext(source,{window,document,localStorage:store,URL,CustomEvent:function(){}});
    return {window,document,events,media,store,classes,viewport};
}
let app;
for(const saved of ['dark','system','light',null]) {
 app=runtime(saved,true);assert.equal(app.document.documentElement.dataset.theme,'light');assert.equal(app.document.documentElement.style.colorScheme,'light');assert.equal(app.store.value,null);assert.equal(app.window.SierraTheme,undefined);assert.equal(app.media.change,undefined);
}
app=runtime('dark',true,true);assert.equal(app.document.documentElement.dataset.theme,'light','blocked storage still uses light');
app=runtime('dark',false,false,'login');assert.equal(app.classes.has('auth-embedded'),true);
app=runtime('dark',false,false,'dashboard');assert.equal(app.window.redirect,'https://example.test/index.php?page=dashboard');
console.log('PASS light appearance, removed preferences, blocked storage and auth redirect');
app=runtime('light',false,false,null,true);assert.match(app.viewport.content,/initial-scale=0\.8/);assert.match(app.viewport.content,/user-scalable=yes/);
app=runtime('light');assert.equal(app.viewport.content,'width=device-width, initial-scale=1','desktop keeps its original scale');
app=runtime('light',false,false,'login',true);assert.equal(app.viewport.content,'width=device-width, initial-scale=1','embedded auth does not apply a second scale');
console.log('PASS phone initial scale, pinch zoom, desktop scale and embedded auth');
