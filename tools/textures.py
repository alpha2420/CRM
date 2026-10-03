"""Convera's own background textures for the website: light caught in rippling water.

Each texture is a caustic pattern (an iterated sine warp, broken up by smooth
random swells), coloured with the brand's blue / indigo / violet / green
gradients. Nothing is sampled from another image.

    python3 tools/textures.py               # all textures
    python3 tools/textures.py hero-water    # just one

Needs numpy and Pillow (with WebP). Writes public/images/textures/<name>.webp.
"""
import sys
from pathlib import Path
import numpy as np
from PIL import Image, ImageFilter

TAU = 2 * np.pi
OUT = Path(__file__).resolve().parent.parent / 'public' / 'images' / 'textures'

def smooth_noise(w, h, cells, rng):
    """Random values on a coarse grid, smoothly enlarged: a gentle, irregular field."""
    grid = rng.normal(0, 1, (cells, cells)).astype(np.float32)
    im = Image.fromarray(grid, mode='F').resize((w, h), Image.BICUBIC)
    return np.asarray(im, dtype=np.float64)

def caustic(w, h, scale, seed, iters=5, perspective=0.0, t=0.0, warp=0.06):
    rng = np.random.default_rng(seed)
    ox, oy = rng.uniform(0, 100, 2)
    xs = np.linspace(0, 1, w, dtype=np.float64)
    ys = np.linspace(0, 1, h, dtype=np.float64)
    x, y = np.meshgrid(xs, ys)
    # Break the formula's regular tiling with two smooth, random swells.
    x = x + warp * smooth_noise(w, h, 5, rng) + warp * 0.4 * smooth_noise(w, h, 11, rng)
    y = y + warp * smooth_noise(w, h, 5, rng) + warp * 0.4 * smooth_noise(w, h, 11, rng)
    if perspective:  # ripples grow towards the bottom, like looking across water
        depth = 1.0 / (perspective + y)
        y = depth * 0.35
        x = (x - 0.5) * depth * 0.35 + 0.5
    px = (x * scale * w / h + ox) * TAU % TAU - 250.0
    py = (y * scale + oy) * TAU % TAU - 250.0
    ix, iy = px.copy(), py.copy()
    c = np.ones_like(px)
    inten = 0.005
    for n in range(iters):
        tt = t * (1.0 - 3.5 / (n + 1))
        ix, iy = px + np.cos(tt - ix) + np.sin(tt + iy), py + np.sin(tt - iy) + np.cos(tt + ix)
        c += 1.0 / np.hypot(px / (np.sin(ix + tt) / inten), py / (np.cos(iy + tt) / inten))
    c /= iters
    c = 1.17 - np.power(np.abs(c), 1.4)
    return np.clip(np.power(np.abs(c), 8.0), 0, 1)

def gradient(w, h, stops, angle=0.25):
    xs = np.linspace(0, 1, w); ys = np.linspace(0, 1, h)
    x, y = np.meshgrid(xs, ys)
    t = np.clip(y * (1 - angle) + x * angle, 0, 1)
    cols = np.array([[int(s[i:i + 2], 16) for i in (1, 3, 5)] for s in stops], dtype=np.float64) / 255
    pos = np.linspace(0, 1, len(stops))
    out = np.zeros((h, w, 3))
    for ch in range(3):
        out[..., ch] = np.interp(t, pos, cols[:, ch])
    return out

def render(name, w, h, stops, glow, scale, seed, strength=0.85, perspective=0.0, soft=0.0):
    base = gradient(w, h, stops)
    c = caustic(w, h, scale, seed, perspective=perspective)
    c2 = caustic(w, h, scale * 0.6, seed + 7, perspective=perspective, warp=0.09)  # a second, larger swell
    light = np.clip(c * strength + c2 * strength * 0.45, 0, 1)[..., None]
    glow = np.array([int(glow[i:i + 2], 16) for i in (1, 3, 5)], dtype=np.float64) / 255
    img = base * (1 - light * 0.15) + glow * light  # screen-like highlights over the colour
    img = np.clip(img, 0, 1)
    im = Image.fromarray((img * 255).astype(np.uint8))
    if soft:
        im = im.filter(ImageFilter.GaussianBlur(soft))
    OUT.mkdir(parents=True, exist_ok=True)
    im.save(OUT / f'{name}.webp', 'WEBP', quality=68, method=6)
    print('wrote', OUT / f'{name}.webp', im.size)

TEXTURES = {
    'hero-water':    dict(w=1800, h=720, stops=['#cfdfff', '#6d97f8', '#3563ec', '#2347cf', '#1b36a8'], glow='#cfe0ff', scale=8.0, seed=3, perspective=0.6, strength=0.75, soft=0.5),
    'liquid-violet': dict(w=1200, h=900, stops=['#9f7bff', '#7c4dff', '#6236e8', '#4b20c4'], glow='#e4d6ff', scale=6.5, seed=8, strength=0.6, soft=0.4),
    'liquid-blue':   dict(w=1200, h=900, stops=['#86abff', '#4f7ff6', '#2f5fe6', '#1f43bd'], glow='#d8e6ff', scale=6.5, seed=13, strength=0.6, soft=0.4),
    'liquid-indigo': dict(w=1200, h=900, stops=['#8b86fb', '#6a60f3', '#5146e0', '#3c30ba'], glow='#e2deff', scale=6.5, seed=21, strength=0.6, soft=0.4),
}

if __name__ == '__main__':
    names = sys.argv[1:] or list(TEXTURES)
    for n in names:
        render(n, **TEXTURES[n])
