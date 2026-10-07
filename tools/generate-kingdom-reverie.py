"""Render an original Union of Kingdoms music proposal using only NumPy.

No reference recording, extracted melody or third-party samples are used here.
The game asset is mono; the listening master is stereo. Both are periodic.
"""
from pathlib import Path
import json
import math
import struct
import wave

import numpy as np


ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "artifacts/audio-reverie"
RATE = 24000
BPM = 64
BARS = 32
BEAT = 60 / BPM
FRAMES = round(BARS * 4 * BEAT * RATE)
RNG = np.random.default_rng(20261005)
MIX = np.zeros((FRAMES, 2), dtype=np.float64)
EVENTS = []


def smooth(value):
    return np.sin(np.clip(value, 0, 1) * np.pi / 2) ** 2


def add(pitch, beat, duration, level, voice="keys", pan=0):
    """Synthesize one note, wrapping its complete release over the loop seam."""
    start = beat * BEAT
    gate = duration * BEAT
    tail = {"keys": 2.2, "pluck": 1.7, "pad": 2.8, "bass": .75}[voice]
    length = gate + tail
    t = np.arange(round(length * RATE)) / RATE
    frequency = 440 * 2 ** ((pitch - 69) / 12)
    phase = 2 * np.pi * frequency * t
    if voice == "keys":
        # Soft struck strings: a rounded attack and rapidly darkening overtones.
        sound = np.zeros_like(t)
        for harmonic, strength in enumerate([1, .40, .21, .12, .07, .035, .018], 1):
            inharmonic = math.sqrt(1 + .00008 * harmonic * harmonic)
            p = phase * harmonic * inharmonic
            decay = np.exp(-t * (.70 + .36 * harmonic))
            sound += strength * (.72 * np.sin(p) + .28 * np.sin(p * 1.0007)) * decay
        env = smooth(t / .012) * smooth((length - t) / tail)
        sound += .012 * RNG.standard_normal(t.size) * np.exp(-t * 90)
    elif voice == "pluck":
        sound = sum(weight * np.sin(phase * h) * np.exp(-t * (1.25 + .66 * h))
                    for h, weight in [(1, 1), (2, .28), (3, .13), (4, .055), (5, .02)])
        env = smooth(t / .016) * smooth((length - t) / tail)
    elif voice == "pad":
        sound = (.66 * np.sin(phase) + .17 * np.sin(phase * 1.0011)
                 + .17 * np.sin(phase * .9989) + .07 * np.sin(phase * 2))
        env = smooth(t / 1.25) * smooth((length - t) / tail)
    else:
        sound = np.sin(phase) + .20 * np.sin(phase * 2) + .055 * np.sin(phase * 3)
        env = smooth(t / .065) * np.exp(-t * .24) * smooth((length - t) / tail)
    sound *= level * env
    stereo = sound[:, None] * np.array([math.sqrt((1 - pan) / 2), math.sqrt((1 + pan) / 2)])
    index = (round(start * RATE) + np.arange(t.size)) % FRAMES
    np.add.at(MIX, index, stereo)
    EVENTS.append((voice, beat, pitch, duration, level))


# D minor / F major colour, open ninths and a suspended turnaround.
# Each chord lasts two bars; voice leading is written for this composition.
HARMONY = [
    (38, (57, 60, 64, 65)), (34, (57, 60, 62, 65)),
    (41, (57, 60, 65, 67)), (36, (55, 60, 62, 64)),
    (43, (58, 62, 65, 69)), (34, (57, 60, 62, 65)),
    (38, (57, 60, 64, 65)), (36, (55, 60, 62, 67)),
    (38, (57, 60, 64, 65)), (41, (57, 60, 65, 67)),
    (34, (57, 60, 62, 65)), (36, (55, 60, 62, 64)),
    (43, (58, 62, 65, 69)), (34, (57, 60, 62, 65)),
    (38, (57, 60, 64, 65)), (38, (57, 60, 64, 65)),
]

# An independent call-and-response theme, with breathing space between phrases.
MELODY = [
    [(0, 77, .9), (1.5, 74, .7), (2.5, 69, 1.1)],
    [(.5, 72, .7), (1.5, 76, .8), (3, 74, 1.2)],
    [(.25, 72, .9), (1.75, 69, .75), (3, 65, 1)],
    [(1, 69, 1.0), (2.5, 74, 1.3)],
    [(0, 72, .75), (1, 77, 1.25), (3, 76, .7)],
    [(.5, 72, 1.1), (2.25, 69, 1.4)],
    [(.25, 74, .85), (1.75, 76, .7), (3, 72, 1.1)],
    [(1, 67, 1.6)],
    [(0, 74, 1.0), (1.5, 77, .75), (2.75, 81, .8)],
    [(.5, 79, 1), (2, 77, 1.3)],
    [(.25, 77, .8), (1.5, 74, .8), (3, 72, 1)],
    [(1, 69, 1.7)],
    [(0, 76, .65), (1, 77, .85), (2.5, 74, 1.1)],
    [(.5, 72, .9), (2, 69, 1.3)],
    [(.25, 72, .8), (1.5, 74, 1.6)],
    [(1, 69, 1.4), (3.25, 72, .6)],
    [(0, 77, 1.1), (1.75, 76, .75), (3, 74, .9)],
    [(.5, 69, 1.1), (2, 72, 1.3)],
    [(.25, 72, .7), (1.5, 69, .8), (2.75, 77, 1)],
    [(1, 76, 1), (2.5, 72, 1.3)],
    [(0, 74, .85), (1.5, 77, 1), (3, 74, .7)],
    [(.5, 72, 1.2), (2.5, 69, 1.3)],
    [(.25, 67, .85), (1.5, 72, 1), (3, 74, .75)],
    [(1, 76, 1.6)],
    [(0, 77, 1.1), (2, 74, 1.3)],
    [(.75, 69, 1.3)],
    [(.25, 72, 1), (2.25, 74, 1.2)],
    [(1, 69, 1.6)],
    [(0, 77, 1), (1.5, 76, .7), (2.75, 74, 1.1)],
    [(.75, 72, 1.1), (2.5, 69, 1.1)],
    [(.25, 72, 1), (2, 74, 1.5)],
    [(.75, 69, 1.1), (2.75, 76, .85)],
]

for section, (bass, chord) in enumerate(HARMONY):
    start = section * 8
    # Long, quiet harmony; restrained low end remains readable on phone speakers.
    for n, pitch in enumerate(chord):
        add(pitch, start - .4 + n * .015, 7.75, .013, "pad", (-.42, .22, -.16, .4)[n])
    for offset in (0, 4):
        add(bass + 12, start + offset + .025, 3.3, .052, "bass")
    for bar in (0, 1):
        # A loose six-note accompaniment: no rigid drum beat.
        for n, offset in enumerate((.05, .8, 1.55, 2.1, 2.85, 3.55)):
            pitch = chord[(0, 2, 1, 3, 1, 2)[n]]
            level = (.049 if n in (0, 3) else .035) * (1 + RNG.uniform(-.06, .06))
            add(pitch, start + bar * 4 + offset, .9, level, "pluck", -.22 if n % 2 else .22)

for bar, notes in enumerate(MELODY):
    for index, (offset, pitch, duration) in enumerate(notes):
        # Very small fixed humanisation; the same loop always renders identically.
        timing = RNG.uniform(-.010, .010) if offset else 0
        level = (.133 if index == 0 else .117) * (1 + RNG.uniform(-.04, .04))
        add(pitch, bar * 4 + offset + timing, duration, level, "keys", -.05)

# Diffuse, dark room reflections. Circular convolution keeps the full tail at
# the loop boundary; there is no fade to silence and no pasted reference audio.
dry = MIX.copy()
frequencies = np.fft.rfftfreq(FRAMES, 1 / RATE)
for channel in range(2):
    impulse = np.zeros(FRAMES)
    for delay, gain in [(.071, .11), (.137, .075), (.223, .053), (.389, .032)]:
        impulse[round((delay + channel * .009) * RATE)] += gain
    tail_n = round(1.9 * RATE)
    tail_t = np.arange(tail_n) / RATE
    diffuse = RNG.standard_normal(tail_n) * np.exp(-tail_t * 4.8) * smooth(tail_t / .045)
    diffuse *= .13 / np.sqrt(np.sum(diffuse ** 2))
    impulse[round(.045 * RATE):round(.045 * RATE) + tail_n] += diffuse
    response = np.fft.rfft(impulse) / (1 + (frequencies / 3800) ** 2)
    source = .72 * dry[:, channel] + .28 * dry[:, 1 - channel]
    MIX[:, channel] += np.fft.irfft(np.fft.rfft(source) * response, n=FRAMES)

MIX -= np.mean(MIX, axis=0)
MIX *= min(.38 / np.max(np.abs(MIX)), .065 / np.sqrt(np.mean(MIX ** 2)))


def write_wav(target, data):
    target.parent.mkdir(parents=True, exist_ok=True)
    pcm = np.round(np.clip(data, -.999, .999) * 32767).astype("<i2")
    with wave.open(str(target), "wb") as output:
        output.setnchannels(1 if data.ndim == 1 else data.shape[1])
        output.setsampwidth(2)
        output.setframerate(RATE)
        output.writeframes(pcm.tobytes())
    return pcm


def vlq(value):
    result = [value & 127]
    while value >> 7:
        value >>= 7
        result.insert(0, (value & 127) | 128)
    return bytes(result)


def write_midi(target):
    """Keep the composition editable independently of this synthesizer."""
    ticks = 480
    chunks = []
    tempo = round(60_000_000 / BPM).to_bytes(3, "big")
    meta = b"\x00\xff\x51\x03" + tempo + b"\x00\xff\x58\x04\x04\x02\x18\x08\x00\xff\x2f\x00"
    chunks.append(b"MTrk" + struct.pack(">I", len(meta)) + meta)
    for channel, (voice, program) in enumerate([("keys", 4), ("pluck", 24), ("pad", 89), ("bass", 32)]):
        events = []
        for kind, beat, pitch, duration, level in EVENTS:
            if kind != voice:
                continue
            start = max(0, round(beat * ticks))
            end = min(BARS * 4 * ticks, max(0, round((beat + duration) * ticks)))
            velocity = max(30, min(100, round(level * 380 + 24)))
            events += [(start, bytes([0x90 + channel, pitch, velocity])), (end, bytes([0x80 + channel, pitch, 0]))]
        data = b"\x00" + bytes([0xC0 + channel, program])
        previous = 0
        for tick, message in sorted(events, key=lambda item: (item[0], item[1][0])):
            data += vlq(tick - previous) + message
            previous = tick
        data += vlq(BARS * 4 * ticks - previous) + b"\xff\x2f\x00"
        chunks.append(b"MTrk" + struct.pack(">I", len(data)) + data)
    target.write_bytes(b"MThd" + struct.pack(">IHHH", 6, 1, len(chunks), ticks) + b"".join(chunks))


game = ROOT / "assets/audio/kingdom-reverie-v1.wav"
mono = MIX.mean(axis=1)
pcm = write_wav(game, mono)
write_wav(OUT / "Kingdom-Reverie-stereo.wav", MIX)
preview = MIX[:32 * RATE].copy()
preview[-2 * RATE:] *= smooth(np.arange(2 * RATE, 0, -1) / (2 * RATE))[:, None]
write_wav(OUT / "Kingdom-Reverie-preview.wav", preview)
write_wav(OUT / "loop-transition.wav", np.concatenate([MIX[-8 * RATE:], MIX[:8 * RATE]]))
write_midi(OUT / "Kingdom-Reverie.mid")
report = {
    "title": "Kingdom Reverie", "status": "listening proposal; not selected in the game",
    "bpm": BPM, "meter": "4/4", "bars": BARS, "seconds": FRAMES / RATE,
    "sample_rate": RATE, "game_channels": 1, "bytes": game.stat().st_size,
    "peak": float(np.max(np.abs(mono))), "rms": float(np.sqrt(np.mean(mono ** 2))),
    "boundary_step": abs(int(pcm[-1]) - int(pcm[0])) / 32768,
    "clipped_samples": int(np.sum(np.abs(pcm.astype(np.int32)) >= 32767)),
    "reference": "Speedy — Mersy BeatZ; public previews only; exact intro position unverified",
    "provenance": "Newly written notes and NumPy synthesis; no sampled reference audio",
}
assert report["boundary_step"] < .005, report
assert report["clipped_samples"] == 0, report
assert .035 < report["rms"] < .075, report
(OUT / "waveform-check.json").write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
print(json.dumps(report, indent=2))
