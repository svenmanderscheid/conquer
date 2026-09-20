"""Render a quiet, periodic 128-second Conquer theme. Build-only NumPy dependency."""
from pathlib import Path
import json
import wave
import numpy as np

ROOT = Path(__file__).resolve().parents[1]
RATE = 22050
SECONDS = 128  # 32 four-beat bars, 60 BPM; no intro or outro.
SIZE = RATE * SECONDS
mix = np.zeros(SIZE, dtype=np.float64)


def note(pitch, start, length, gain, voice):
    t = np.arange(round(length * RATE)) / RATE
    phase = 2 * np.pi * 440 * 2 ** ((pitch - 69) / 12) * t
    attack, release = {'pad': (2.2, 3.4), 'flute': (.55, 1.2), 'harp': (.07, 1.5)}[voice]
    # Smooth zero-slope envelopes keep starts and releases free of sharp edges.
    envelope = np.sin(np.minimum(t / attack, 1) * np.pi / 2) ** 2
    envelope *= np.sin(np.clip((length - t) / release, 0, 1) * np.pi / 2) ** 2
    if voice == 'pad':
        sound = np.sin(phase) + .10 * np.sin(2 * phase)
        envelope *= .92 + .08 * np.sin(2 * np.pi * t / 7.7)
    elif voice == 'flute':
        sound = np.sin(phase + .018 * np.sin(2 * np.pi * 3.7 * t)) + .035 * np.sin(2 * phase)
    else:
        sound = (np.sin(phase) + .10 * np.sin(2 * phase) + .018 * np.sin(3 * phase))
        envelope *= np.exp(-t * 1.35)
    # Circular overlap-add: the end's sustained notes already exist at the start.
    index = (round(start * RATE) + np.arange(len(t))) % SIZE
    np.add.at(mix, index, gain * sound * envelope)


# Shared upper notes and gentle inversions; the seam stays inside G-add9 harmony.
chords = [(55, 62, 69, 71), (60, 64, 67, 71), (59, 64, 67, 74), (57, 62, 66, 69),
          (55, 62, 67, 71), (60, 64, 67, 74), (57, 64, 67, 71), (57, 62, 67, 69),
          (59, 64, 67, 74), (59, 62, 66, 69), (60, 64, 67, 71), (55, 62, 69, 71),
          (57, 60, 64, 67), (60, 64, 67, 71), (55, 62, 67, 69), (55, 62, 69, 71)]
for section, chord in enumerate(chords):
    start = section * 8
    for voice, pitch in enumerate(chord):
        note(pitch, start - 1.2, 11.5, .020 if voice else .014, 'pad')
    # Loose, sparse harp figures instead of a persistent arpeggio pattern.
    for offset, pitch in [(1.2, chord[1] + 12), (4.4, chord[2] + 12), (6.7, chord[1] + 12)]:
        note(pitch, start + offset, 3.2, .025 if section % 3 else .020, 'harp')

# Small answering phrases separated by rests, with no emphatic final cadence.
melody = [(2.3, 74, 3.2), (6.0, 71, 2.6), (12.2, 76, 3.4), (17.5, 74, 3.0),
          (23.8, 71, 3.8), (30.5, 69, 3.0), (35.0, 74, 3.1), (40.3, 79, 3.5),
          (46.4, 76, 3.4), (52.0, 74, 3.0), (59.2, 71, 3.8), (66.0, 76, 3.5),
          (70.5, 79, 3.0), (77.0, 74, 3.6), (84.4, 76, 3.8), (90.5, 74, 3.0),
          (98.2, 72, 3.5), (103.4, 76, 3.2), (110.0, 74, 3.6), (116.2, 71, 3.2),
          (125.0, 71, 4.5)]
for index, (start, pitch, length) in enumerate(melody):
    note(pitch, start, length, .020 if index % 3 else .024, 'flute')

# A circular room tail, not a fade-to-silence crossfade at the file boundary.
dry = mix.copy()
for delay, gain in [(.173, .10), (.307, .08), (.487, .06), (.719, .04), (1.113, .025)]:
    mix += np.roll(dry, round(delay * RATE)) * gain
mix -= np.mean(mix)
mix *= min(.22 / np.max(np.abs(mix)), .043 / np.sqrt(np.mean(mix ** 2)))
pcm = np.round(mix * 32767).astype('<i2')


def write(target, samples):
    target.parent.mkdir(parents=True, exist_ok=True)
    with wave.open(str(target), 'wb') as output:
        output.setnchannels(1)
        output.setsampwidth(2)
        output.setframerate(RATE)
        output.writeframes(samples.tobytes())


target = ROOT / 'assets/audio/village-meadow-v1.wav'
write(target, pcm)
artifacts = ROOT / 'artifacts/audio-meadow'
# The actual file seam is at second 12 in this listening excerpt.
write(artifacts / 'loop-transition.wav', np.concatenate([pcm[-12 * RATE:], pcm[:12 * RATE]]))
stats = {'file': str(target), 'seconds': SECONDS, 'bytes': target.stat().st_size,
         'peak': float(np.max(np.abs(mix))), 'rms': float(np.sqrt(np.mean(mix ** 2))),
         'boundary_step': float(abs(int(pcm[-1]) - int(pcm[0])) / 32768),
         'clipped_samples': int(np.sum(np.abs(pcm.astype(np.int32)) >= 32767))}
assert stats['boundary_step'] < .003 and stats['clipped_samples'] == 0
(artifacts / 'waveform-check.json').write_text(json.dumps(stats, indent=2) + '\n', encoding='utf-8')
print(json.dumps(stats, indent=2))
