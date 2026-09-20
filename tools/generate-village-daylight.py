"""Original, quieter Conquer village theme with bright, soft tones. Requires NumPy."""
from pathlib import Path
import json
import wave
import numpy as np

ROOT = Path(__file__).resolve().parents[1]
RATE = 22050
BEAT = 60 / 84
LENGTH = round(16 * 4 * BEAT * RATE)
mix = np.zeros(LENGTH, dtype=np.float64)


def note(midi, beat, beats, volume, voice):
    seconds = beats * BEAT + .5
    t = np.arange(round(seconds * RATE)) / RATE
    phase = 2 * np.pi * 440 * 2 ** ((midi - 69) / 12) * t
    attack = np.minimum(1, t / (.16 if voice == 'flute' else .025))
    release = np.minimum(1, np.maximum(0, (seconds - t) / .5))
    if voice == 'bell':
        sound = (np.sin(phase) * np.exp(-t * 2.1)
                 + .16 * np.sin(phase * 2) * np.exp(-t * 4)
                 + .035 * np.sin(phase * 3) * np.exp(-t * 6))
    elif voice == 'harp':
        sound = (np.sin(phase) + .12 * np.sin(phase * 2)) * np.exp(-t * 2.8)
    else:
        sound = np.sin(phase + .018 * np.sin(2 * np.pi * 4 * t))
    indices = (round(beat * BEAT * RATE) + np.arange(len(t))) % LENGTH
    np.add.at(mix, indices, sound * attack * release * volume)


# G major, spacious four-beat phrases; a new melody rather than a transposition.
chords = [(55, 59, 62), (62, 66, 69), (64, 67, 71), (60, 64, 67),
          (55, 59, 62), (60, 64, 67), (57, 60, 64), (62, 66, 69),
          (64, 67, 71), (59, 62, 66), (60, 64, 67), (55, 59, 62),
          (57, 60, 64), (60, 64, 67), (55, 59, 62), (62, 66, 69)]
melody = [
    [(0, 79, 1), (1.5, 83, .5), (2.5, 86, 1)],
    [(0, 81, 1.5), (2.5, 78, .75)],
    [(0, 79, .75), (1, 83, .75), (2.5, 81, 1)],
    [(0, 79, 2)],
    [(0, 83, 1), (1.5, 81, .5), (2.5, 79, 1)],
    [(0, 76, 1), (2, 79, 1.25)],
    [(0, 81, 1.25), (2, 84, .75)],
    [(0, 81, 2)],
    [(0, 83, .75), (1, 86, 1), (2.5, 83, .75)],
    [(0, 81, 1), (2, 78, 1)],
    [(0, 79, 1), (1.5, 84, .75), (3, 83, .5)],
    [(0, 79, 2)],
    [(0, 81, 1), (1.5, 79, .5), (2.5, 76, 1)],
    [(0, 79, 1), (2, 76, 1)],
    [(0, 74, .75), (1, 79, 1.5)],
    [(0, 78, 1.5), (2.5, 81, .75)],
]
for bar, chord in enumerate(chords):
    start = bar * 4
    for step, pitch in enumerate([chord[0], chord[2], chord[1] + 12, chord[2]]):
        note(pitch, start + step, 1.7, .07 if step == 0 else .05, 'harp')
    for beat, pitch, length in melody[bar]:
        note(pitch, start + beat, length, .085, 'bell')
    # A quiet answering flute, with rests to keep the texture light.
    if bar % 4 in (1, 3):
        note(melody[bar][0][1] - 12, start + .25, 2.25, .012, 'flute')

dry = mix.copy()
for delay, gain in [(.19, .10), (.37, .07), (.61, .04)]:
    mix += np.roll(dry, round(delay * RATE)) * gain
mix -= np.mean(mix)
# Lower both average and peak level than the first theme; preserve saved volume.
mix *= min(.32 / np.max(np.abs(mix)), .07 / np.sqrt(np.mean(mix ** 2)))
target = ROOT / 'assets/audio/village-daylight-v1.wav'
with wave.open(str(target), 'wb') as output:
    output.setnchannels(1)
    output.setsampwidth(2)
    output.setframerate(RATE)
    output.writeframes(np.round(mix * 32767).astype('<i2').tobytes())
print(json.dumps({'file': str(target), 'seconds': LENGTH / RATE,
                  'peak': float(np.max(np.abs(mix))),
                  'rms': float(np.sqrt(np.mean(mix ** 2))),
                  'loop_boundary_step': float(abs(mix[-1] - mix[0]))}, indent=2))
