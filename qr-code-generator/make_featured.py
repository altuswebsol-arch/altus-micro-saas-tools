#!/usr/bin/env python3
"""Featured images for the Altus QR Code Generator tool + blog post.
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
QR_FG = (16, 179, 254)

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

def draw_qr(d, x, y, size, modules=25, seed=7):
    """Decorative QR-style motif: three finder squares + random modules."""
    rnd = random.Random(seed)
    cell = size / modules
    # white card behind
    pad = 26
    d.rounded_rectangle([x - pad, y - pad, x + size + pad, y + size + pad],
                        radius=18, fill=WHITE)
    def finder(fx, fy):
        s = 7 * cell
        d.rectangle([x + fx * cell, y + fy * cell,
                     x + fx * cell + s, y + fy * cell + s], fill=QR_FG)
        d.rectangle([x + (fx + 1) * cell, y + (fy + 1) * cell,
                     x + (fx + 6) * cell, y + (fy + 6) * cell], fill=WHITE)
        d.rectangle([x + (fx + 2) * cell, y + (fy + 2) * cell,
                     x + (fx + 5) * cell, y + (fy + 5) * cell], fill=QR_FG)
    for r in range(modules):
        for c in range(modules):
            in_finder = (r < 8 and c < 8) or (r < 8 and c >= modules - 8) or (r >= modules - 8 and c < 8)
            if not in_finder and rnd.random() < 0.42:
                d.rectangle([x + c * cell, y + r * cell,
                             x + (c + 1) * cell, y + (r + 1) * cell], fill=QR_FG)
    finder(0, 0)
    finder(modules - 7, 0)
    finder(0, modules - 7)

def qr_card(d, x, y, w, h):
    draw_qr(d, x + 45, y + 45, 240, seed=7)
    # scan line
    sy = y + 45 + 150
    d.rectangle([x + 45, sy, x + 45 + 240, sy + 6], fill=BLUE_LT)
    # caption bars
    d.rounded_rectangle([x + 40, y + h - 92, x + w - 120, y + h - 74], radius=8, fill=(226, 234, 248))
    d.rounded_rectangle([x + 40, y + h - 64, x + w - 40, y + h - 46], radius=8, fill=QR_FG)

def qr_card_blog(d, x, y, w, h):
    # phone-ish card with QR + link bars
    d.rounded_rectangle([x, y, x + w, y + h], radius=18, fill=(241, 244, 248))
    d.rounded_rectangle([x + 26, y + 30, x + w - 26, y + 130], radius=16, fill=WHITE)
    draw_qr(d, x + 56, y + 52, 120, modules=21, seed=21)
    d.rounded_rectangle([x + 200, y + 62, x + w - 48, y + 80], radius=9, fill=(214, 224, 236))
    d.rounded_rectangle([x + 200, y + 90, x + w - 90, y + 104], radius=7, fill=(232, 238, 246))
    for i in range(3):
        yy = y + 180 + i * 62
        d.rounded_rectangle([x + 40, yy, x + 40 + 200, yy + 22], radius=11, fill=QR_FG if i == 0 else (214, 224, 236))
        d.rounded_rectangle([x + 40, yy + 32, x + w - 120, yy + 46], radius=7, fill=(232, 238, 246))
    d.rounded_rectangle([x + 26, y + h - 88, x + w - 26, y + h - 56], radius=8, fill=QR_FG)

def make(title_lines, kicker, sub, out, card):
    img = bg()
    d = ImageDraw.Draw(img)
    if card == "qr":
        qr_card(d, W - 420, 119, 330, 390)
    else:
        qr_card_blog(d, W - 420, 119, 330, 390)
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

make(["Free QR Code", "Generator"],
     "FREE TOOL",
     "Turn any link, text or Wi-Fi detail into a scannable QR code — your colours, PNG + SVG downloads, right in your browser. Free forever.",
     os.path.join(BASE, "qr-generator-featured.jpg"), card="qr")

make(["QR Codes for Small", "Business (Free Maker)"],
     "NEW ON THE BLOG",
     "Where to put QR codes, what to link them to, and the mistakes that make them unscannable — plus a free tool that makes them properly.",
     os.path.join(BASE, "qr-blog-featured.jpg"), card="blog")
