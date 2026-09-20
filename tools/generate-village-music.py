"""Render Conquer's original, sample-free village theme. Build tool; requires NumPy."""
from pathlib import Path
import json
import wave
import numpy as np

ROOT = Path(__file__).resolve().parents[1]
RATE = 22050
BEAT = 60 / 75
BARS = 16
LENGTH = round(BARS * 3 * BEAT * RATE)
mix = np.zeros(LENGTH, dtype=np.float64)


def note(midi, start, seconds, gain, voice):
    t = np.arange(round(seconds * RATE)) / RATE
    frequency = 440 * 2 ** ((midi - 69) / 12)
    phase = 2 * np.pi * frequency * t
    attack = np.minimum(1, t / (0.18 if voice == 'pad' else 0.035))
    release = np.minimum(1, np.maximum(0, (seconds - t) / 0.28))
    if voice == 'harp':
        wave_ = sum(weight * np.sin(h * phase) * np.exp(-t * (2 + h * 0.6))
                    for h, weight in [(1, 1), (2, .26), (3, .10), (4, .025)])
    elif voice == 'flute':
        phase += .025 * np.sin(2 * np.pi * 4.2 * t) * np.minimum(t, 1)
        wave_ = np.sin(phase) + .1 * np.sin(2 * phase) + .025 * np.sin(3 * phase)
    else:
        wave_ = .5 * np.sin(phase) + .24 * np.sin(phase * 1.001) + .24 * np.sin(phase * .999)
    values = gain * wave_ * attack * release
    # Wrap release tails into the beginning, making the exported loop seamless.
    indices = (round(start * RATE) + np.arange(len(t))) % LENGTH
    np.add.at(mix, indices, values)


# D major, a gentle 3/4 pulse. The second phrase answers the first.
chords = [(50, 54, 57), (47, 50, 54), (43, 47, 50), (45, 49, 52),
          (50, 54, 57), (47, 50, 54), (43, 47, 50), (45, 49, 52),
          (52, 55, 59), (43, 47, 50), (50, 54, 57), (45, 49, 52),
          (47, 50, 54), (43, 47, 50), (50, 54, 57), (45, 49, 52)]
melody = [
    [(0, 74, 1), (1, 78, .75), (2, 76, .75)],
    [(0, 74, 1.5), (2, 71, .75)],
    [(0, 74, 1), (1.5, 71, .5), (2, 69, .75)],
    [(0, 73, 2)],
    [(0, 74, .75), (1, 76, .75), (2, 78, .75)],
    [(0, 78, 1), (1.5, 74, 1)],
    [(0, 79, 1), (1.5, 78, .5), (2, 74, .75)],
    [(0, 76, 1.75)],
    [(0, 76, .75), (1, 79, 1.5)],
    [(0, 78, .75), (1, 74, 1.5)],
    [(0, 78, 1), (1.5, 76, .5), (2, 74, .75)],
    [(0, 73, 1.5)],
    [(0, 74, 1), (1.5, 71, 1)],
    [(0, 74, 1), (1.5, 71, .75)],
    [(0, 69, 1), (1, 74, 1.5)],
    [(0, 73, 1.5)],
]
for bar, chord in enumerate(chords):
    start = bar * 3 * BEAT
    for pitch in chord:
        note(pitch, start, 3 * BEAT + .4, .025, 'pad')
    for step, pitch in enumerate([chord[0] + 12, chord[2] + 12, chord[1] + 12,
                                   chord[2] + 12, chord[1] + 12, chord[2] + 12]):
        note(pitch, start + step * .5 * BEAT, 1.7, .105 if step % 2 == 0 else .072, 'harp')
    for beat, pitch, length in melody[bar]:
        note(pitch, start + beat * BEAT, length * BEAT + .12, .085, 'flute')

dry = mix.copy()
for delay, gain in [(0.137, .12), (.293, .09), (.431, .065), (.677, .04)]:
    mix += np.roll(dry, round(delay * RATE)) * gain
mix -= np.mean(mix)
mix *= .65 / np.max(np.abs(mix))
pcm = np.round(mix * 32767).astype('<i2')
target = ROOT / 'assets/audio/village-morning-v1.wav'
target.parent.mkdir(parents=True, exist_ok=True)
with wave.open(str(target), 'wb') as file:
    file.setnchannels(1)
    file.setsampwidth(2)
    file.setframerate(RATE)
    file.writeframes(pcm.tobytes())
print(json.dumps({'file':str(target), 'seconds':LENGTH / RATE, 'bytes':target.stat().st_size,
                  'peak':float(np.max(np.abs(mix))), 'rms':float(np.sqrt(np.mean(mix ** 2))),
                  'loop_boundary_step':float(abs(mix[-1] - mix[0]))}, indent=2))
