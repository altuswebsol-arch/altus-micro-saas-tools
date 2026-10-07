#!/usr/bin/env python3
"""Featured images for the Altus Google Review Link Generator tool + blog post.
Locked Altus style: deep navy gradient, electric blue, bold Poppins."""
from PIL import Image, ImageDraw, ImageFont
import os, random

BASE = os.path.dirname(os.path.abspath(__file__))
FDIR = os.path.expanduser("~/workspace/altus-bot/saas-tools/fonts")
W, H = 1200, 628

NAVY_TOP = (13, 32, 64)
NAVY_BOT = (5, 11, 24)
BLUE = (46, 107, 255)
BLUE_LT = (0, 194, 255)
WHITE = (255, 255, 255)
GRAY = (178, 192, 216)

def font(name, size):
    return ImageFont.truetype(os.path.join(FDIR, f"Poppins-{name}.ttf"), size)

def bg():
    img = Image.new("RGB", (W, H))
    px = img.load()
    for y in range(H):
        t = y / H
        c = tuple(int(NAVY_TOP[i] + (NAVY_BOT[i] - NAVY_TOP[i]) * t) for i in range(3))
        for x in range(W):
            px[x, y] = c
    glow = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    gd = ImageDraw.Draw(glow)
    cx, cy = W - 330, H // 2
    for r in range(320, 60, -16):
        gd.ellipse([cx - r, cy - r, cx + r, cy + r], outline=(70, 130, 255, 26), width=3)
    return Image.alpha_composite(img.convert("RGBA"), glow).convert("RGB")

def wrapped(d, xy, text, fnt, fill, max_w, line_h):
    x, y = xy
    words, line = text.split(), ""
    for w in words:
        test = (line + " " + w).strip()
        if d.textlength(test, font=fnt) <= max_w:
            line = test
        else:
            d.text((x, y), line, font=fnt, fill=fill)
            y += line_h
            line = w
    if line:
        d.text((x, y), line, font=fnt, fill=fill)
        y += line_h
    return y

def badge(d, xy, text):
    f = font("SemiBold", 28)
    tw = d.textlength(text, font=f)
    pad_x, pad_y = 30, 12
    x, y = xy
    d.rounded_rectangle([x, y, x + tw + pad_x * 2, y + 54], radius=27,
                        outline=BLUE, width=3)
    d.text((x + pad_x, y + pad_y - 1), text, font=f, fill=BLUE_LT)

def qr_card(d, x, y, w, h):
    """Decorative QR-style card (not a real scannable code)."""
    rnd = random.Random(42)
    d.rounded_rectangle([x, y, x + w, y + h], radius=18, fill=(255, 255, 255))
    m = 26
    n = 21
    cell = (w - 2 * m) / n
    for i in range(n):
        for j in range(n):
            if rnd.random() < 0.46:
                d.rectangle([x + m + i * cell, y + m + j * cell,
                             x + m + (i + 1) * cell - 1, y + m + (j + 1) * cell - 1],
                            fill=(13, 32, 64))
    def finder(cx, cy):
        s = cell * 7
        d.rectangle([cx, cy, cx + s, cy + s], fill=(13, 32, 64))
        d.rectangle([cx + cell, cy + cell, cx + s - cell, cy + s - cell], fill=(255, 255, 255))
        d.rectangle([cx + 2 * cell, cy + 2 * cell, cx + s - 2 * cell, cy + s - 2 * cell],
                    fill=(13, 32, 64))
    finder(x + m - 4, y + m - 4)
    finder(x + w - m - 7 * cell + 4, y + m - 4)
    finder(x + m - 4, y + h - m - 7 * cell + 4)

def star(d, cx, cy, r, fill):
    pts = []
    for k in range(10):
        ang = -90 + k * 36
        rad = r if k % 2 == 0 else r * 0.45
        import math
        pts.append((cx + rad * math.cos(math.radians(ang)),
                    cy + rad * math.sin(math.radians(ang))))
    d.polygon(pts, fill=fill)

def review_card(d, x, y, w, h):
    d.rounded_rectangle([x, y, x + w, y + h], radius=18, fill=(255, 255, 255))
    for i in range(5):
        star(d, x + 44 + i * 52, y + 52, 20, (255, 176, 32))
    for i in range(3):
        ly = y + 108 + i * 32
        lw = w - 68 - (i % 3) * 58
        d.rounded_rectangle([x + 34, ly, x + 34 + lw, ly + 13], radius=6, fill=(226, 234, 248))

def make(title_lines, kicker, sub, out, card="qr"):
    img = bg()
    d = ImageDraw.Draw(img)
    if card == "qr":
        qr_card(d, W - 400, 124, 300, 390)
    else:
        review_card(d, W - 400, 124, 300, 390)
    d.text((64, 36), "ALTUS WEB SOLUTIONS", font=font("SemiBold", 26),
           fill=(110, 128, 160))
    badge(d, (64, 84), kicker)
    y = 168
    for ln in title_lines:
        d.text((64, y), ln, font=font("ExtraBold", 68), fill=WHITE)
        y += 82
    wrapped(d, (64, y + 14), sub, font("Regular", 31), GRAY, 636, 46)
    img.save(out, quality=92)
    print("wrote", out)

make(["Free Google Review", "Link Generator"],
     "FREE TOOL",
     "Turn your Google Business Profile into a one-click review link and a scannable QR code — ready for receipts, counters and packaging. No account needed.",
     os.path.join(BASE, "review-link-generator-featured.jpg"), card="qr")

make(["How to Get More", "Google Reviews", "(the Easy Way)"],
     "NEW ON THE BLOG",
     "Customers won't leave reviews if you make it hard. Here is how to turn your Google listing into a one-click review link and QR code — in five minutes.",
     os.path.join(BASE, "review-link-blog-featured.jpg"), card="review")
