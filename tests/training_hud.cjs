'use strict';

// Deterministic clock + DOM fixture; does not create training orders or touch a database.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
let wallTime = Date.parse('2030-01-01T12:00:00Z');
class Clock extends Date { static now() { return wallTime; } }
class Element {
    constructor() { this.dataset = {}; this.style = {}; this.nodes = {}; this.textContent = ''; this.isConnected = false; }
    querySelector(selector) { return this.nodes[selector] ||= new Element(); }
    setAttribute(key, value) { this[key] = value; }
    append(element) { this.child = element; element.isConnected = true; }
    remove() { this.isConnected = false; }
}
const host = new Element();
const listeners = new Map();
let tick, cleared = false;
const document = {
    hidden: false,
    querySelector: selector => selector === '#hud-left-tools' ? host : null,
    createElement: () => new Element(),
    addEventListener: (name, fn) => listeners.set(name, fn),
    removeEventListener: name => listeners.delete(name),
};
const window = {setInterval: (fn, delay) => { assert.equal(delay, 1000); tick = fn; return 7; }, clearInterval: id => { assert.equal(id, 7); cleared = true; }};
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../assets/js/training-hud.js'), 'utf8'), {window, document, Date: Clock, Promise});
const Hud = window.ConquerTrainingHud;
const batch = (id, end, count = 20) => ({id, count, started_at: '2030-01-01 11:59:00', finishes_at: end});
const state = {troop_queue: []};
let serverTime = wallTime;
const summary = queue => Hud.summarize({troop_queue: queue}, serverTime);
assert.equal(summary([]).status, 'idle');
assert.equal(Hud.summarize(null, serverTime).status, 'loading');
assert.equal(summary([batch(1, '2030-01-01 12:01:01')]).seconds, 61, 'SQL timestamps must be UTC, independently of browser timezone');
assert.equal(summary([batch(1, '2030-01-01T13:01:01+01:00')]).seconds, 61, 'Explicit timezone offsets must be preserved');
assert.equal(summary([{...batch(1, '2030-01-01 12:01:00'), is_processed: '1'}]).status, 'idle');
const sorted = summary([batch(1, '2030-01-01 12:03:00', 10), batch(2, '2030-01-01 12:01:00', 30)]);
assert.equal(sorted.seconds, 60, 'Show earliest completion even when API order changes');
assert.equal(sorted.count, 40);
assert.equal(sorted.batches, 2);
assert.equal(summary([batch(1, 'bad timestamp')]).status, 'unknown', 'Never turn an invalid deadline into a false completion');
assert.equal(Hud.formatTime(59.1), '01:00');
assert.equal(Hud.formatTime(3601), '1:00:01');
assert.equal(Hud.formatTime(86401), '24:00:01');

async function flush() { for (let i = 0; i < 8; i++) await Promise.resolve(); }
async function main() {
    let requests = 0, settle, rejectNext = false;
    const hud = Hud({getState: () => state, now: () => serverTime, refresh: () => {
        requests++;
        if (rejectNext) return Promise.reject(new Error('offline'));
        return new Promise(resolve => { settle = resolve; });
    }});
    hud.update();
    const button = host.child;
    assert.equal(button.dataset.action, 'tab');
    assert.equal(button.dataset.id, 'army', 'Use the existing army navigation action');
    assert.ok(Object.hasOwn(button.dataset, 'cityOnly'));
    assert.equal(button.querySelector('.training-hud-time').textContent, 'Auftrag starten');
    assert.match(button['aria-label'], /Keine Truppen/);
    state.troop_queue = [batch(1, '2030-01-01 12:01:00')];
    hud.update();
    assert.equal(button.querySelector('.training-hud-time').textContent, '01:00');
    serverTime += 1000;
    tick();
    assert.equal(button.querySelector('.training-hud-time').textContent, '00:59', 'Countdown follows server clock even when client clock differs');
    state.troop_queue[0].finishes_at = '2030-01-01 12:00:10';
    hud.update();
    assert.equal(button.querySelector('.training-hud-time').textContent, '00:09', 'Speedup updates remaining time without remounting the HUD');
    serverTime += 9000;
    tick();
    await flush();
    assert.equal(requests, 1);
    assert.equal(button.querySelector('.training-hud-time').textContent, 'Wird bestätigt', 'Await server confirmation before saying ready');
    for (let i = 0; i < 20; i++) tick();
    await flush();
    assert.equal(requests, 1, 'Concurrent completion ticks must share one refresh');
    settle();
    await flush();
    tick();
    await flush();
    assert.equal(requests, 1, 'Unchanged stale queue must not refresh every second');
    wallTime += 15000;
    document.hidden = true;
    tick();
    await flush();
    assert.equal(requests, 1, 'Hidden pages do not request completion refreshes');
    document.hidden = false;
    rejectNext = true;
    listeners.get('visibilitychange')();
    await flush();
    assert.equal(requests, 2, 'Visibility recovery can retry after cooldown');
    tick();
    await flush();
    assert.equal(requests, 2, 'Failed completion refreshes also respect cooldown');
    state.troop_queue = [];
    hud.update();
    assert.equal(button.dataset.trainingStatus, 'idle', 'Cancellation or server-credited completion clears the timer');
    assert.equal(button.querySelector('.training-hud-time').textContent, 'Auftrag starten');
    assert.equal(button.querySelector('.training-hud-progress').style.width, '0%');
    hud.destroy();
    assert.ok(cleared);
    assert.equal(button.isConnected, false);
    assert.equal(listeners.size, 0);
    console.log('Training HUD: UTC/server clock, next batch, speedups, cancellation, completion throttling, recovery and cleanup passed.');
}
main().catch(error => { console.error(error); process.exitCode = 1; });
