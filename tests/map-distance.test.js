const assert = require('node:assert/strict');
const {group, distance} = require('../assets/js/map-clusters.js');
const origin = {latitude:15.3092, longitude:120.9033};
const point = (meters, id) => ({latitude:origin.latitude + meters / 111194.93,longitude:origin.longitude,id});
assert.deepEqual(group([point(0,1),point(30,2),point(300,3)],50).map(g=>g.map(p=>p.id)),[[1,2],[3]]);
assert.equal(group([point(0,1),point(30,2),point(60,3)],50).length,2,'a chain must not merge reports more than the configured distance apart');
assert.equal(group([point(0,1),point(30,2)],20).length,2,'admin radius is respected');
assert.equal(group([point(0,1),point(0,2)],0).length,2,'zero radius disables grouping');
assert.equal(group([{latitude:'bad',longitude:0},point(0,1)],50).length,1);
assert.equal(group([{latitude:0,longitude:179.9999},{latitude:0,longitude:-179.9999}],50).length,1,'distance works across the date line');
const many = Array.from({length:250},(_,i)=>point(i*13,i));
for(const members of group(many,50)) for(const a of members) for(const b of members)
    assert.ok(distance({lat:a.latitude,lng:a.longitude},{lat:b.latitude,lng:b.longitude})<=50.001);
assert.deepEqual(group(many,50),group([...many].reverse(),50),'grouping is independent of input order and screen zoom');
console.log('Map distance checks passed.');
