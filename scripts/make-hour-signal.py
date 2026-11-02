#!/usr/bin/env python3
"""Deterministic locally synthesized two-tone time signal, without speech or music."""
import argparse
import math
import struct
import wave
from pathlib import Path

def create(path):
    rate = 44100
    # Two 160 ms 880 Hz beeps, 140 ms apart; 10 ms fades avoid clicks.
    samples = []
    for i in range(int(1.2 * rate)):
        t = i / rate
        value = 0.0
        for start in (0.15, 0.45):
            p = t - start
            if 0 <= p < 0.16:
                envelope = min(1.0, p / 0.01, (0.16 - p) / 0.01)
                value = 10 ** (-18 / 20) * envelope * math.sin(2 * math.pi * 880 * p)
        samples.append(struct.pack('<hh', round(value * 32767), round(value * 32767)))
    with wave.open(str(path), 'wb') as out:
        out.setnchannels(2)
        out.setsampwidth(2)
        out.setframerate(rate)
        out.writeframes(b''.join(samples))

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('output', type=Path)
    args = parser.parse_args()
    create(args.output)
