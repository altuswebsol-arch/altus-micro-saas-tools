#!/usr/bin/env python3
"""Featured images for the Altus Privacy Policy Generator tool + blog post.
Locked Altus style: deep navy gradient, electric blue, bold Poppins."""
from PIL import Image, ImageDraw, ImageFont
import os

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
    # faint concentric glow, composited with alpha
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

def doc_card(d, x, y, w, h):
    d.rounded_rectangle([x, y, x + w, y + h], radius=18, fill=(255, 255, 255))
    d.rounded_rectangle([x + 34, y + 34, x + w - 34, y + 80], radius=10, fill=BLUE)
    for i in range(6):
        ly = y + 108 + i * 32
        lw = w - 68 - (i % 3) * 58
        d.rounded_rectangle([x + 34, ly, x + 34 + lw, ly + 13], radius=6, fill=(226, 234, 248))

def make(title_lines, kicker, sub, out):
    img = bg()
    d = ImageDraw.Draw(img)
    doc_card(d, W - 400, 124, 300, 390)
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

make(["Free Privacy", "Policy Generator"],
     "FREE TOOL",
     "Answer a few questions and get a UK GDPR-ready privacy policy draft for your business. Copy it, download it, or have it emailed to you — no account needed.",
     os.path.join(BASE, "privacy-policy-generator-featured.jpg"))

make(["Does Your Website", "Legally Need a", "Privacy Policy?"],
     "NEW ON THE BLOG",
     "If your site collects names, emails or enquiries, UK law says you must tell visitors what you do with their data. Here is the 10-minute fix.",
     os.path.join(BASE, "privacy-policy-blog-featured.jpg"))
