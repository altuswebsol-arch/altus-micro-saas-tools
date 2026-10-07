#!/usr/bin/env python3
"""Featured images for the Altus Favicon Generator tool + blog post.
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

def browser_card(d, x, y, w, h):
    """Decorative card: browser window with a tab showing a favicon."""
    d.rounded_rectangle([x, y, x + w, y + h], radius=18, fill=WHITE)
    # tab bar
    d.rounded_rectangle([x, y, x + w, y + 88], radius=18, fill=(232, 238, 246))
    d.rectangle([x, y + 44, x + w, y + 88], fill=(232, 238, 246))
    # the tab (white, with favicon + title)
    d.rounded_rectangle([x + 22, y + 14, x + 208, y + 88], radius=12, fill=WHITE)
    d.rounded_rectangle([x + 22, y + 62, x + 208, y + 88], radius=0, fill=WHITE)
    # favicon: rounded blue square with white "A"
    d.rounded_rectangle([x + 40, y + 32, x + 72, y + 64], radius=8, fill=(16, 179, 254))
    d.text((x + 47, y + 33), "A", font=font("ExtraBold", 24), fill=WHITE)
    d.rounded_rectangle([x + 84, y + 40, x + 190, y + 52], radius=6, fill=(226, 234, 248))
    # address bar
    d.rounded_rectangle([x + 22, y + 104, x + w - 22, y + 148], radius=22, fill=(243, 246, 250))
    d.rounded_rectangle([x + 40, y + 118, x + w - 80, y + 134], radius=8, fill=(214, 224, 236))
    # page body: favicon sizes row
    sizes = [64, 48, 32, 24, 16]
    sx = x + 40
    by = y + 196
    for s in sizes:
        d.rounded_rectangle([sx, by + (64 - s), sx + s, by + 64], radius=max(4, s // 6), fill=(16, 179, 254))
        d.text((sx + s // 2 - 8, by + (64 - s) + s // 2 - 13), "A", font=font("ExtraBold", max(12, s // 2)), fill=WHITE)
        sx += s + 18
    d.rounded_rectangle([x + 40, y + h - 92, x + w - 120, y + h - 74], radius=8, fill=(226, 234, 248))
    d.rounded_rectangle([x + 40, y + h - 64, x + w - 40, y + h - 46], radius=8, fill=(16, 179, 254))

def tab_card(d, x, y, w, h):
    """Blog variant: oversized browser tab with favicon, plus magnifier ring."""
    d.rounded_rectangle([x, y, x + w, y + h], radius=18, fill=(241, 244, 248))
    d.rounded_rectangle([x + 26, y + 30, x + w - 60, y + 118], radius=16, fill=WHITE)
    d.rounded_rectangle([x + 50, y + 54, x + 106, y + 110], radius=14, fill=(16, 179, 254))
    d.text((x + 63, y + 58), "A", font=font("ExtraBold", 40), fill=WHITE)
    d.rounded_rectangle([x + 124, y + 66, x + w - 84, y + 84], radius=9, fill=(214, 224, 236))
    d.rounded_rectangle([x + 124, y + 92, x + w - 140, y + 104], radius=6, fill=(232, 238, 246))
    # three mini favicon chips below
    for i, (s, col) in enumerate([(56, (16, 179, 254)), (40, (11, 127, 184)), (28, (46, 107, 255))]):
        cx0 = x + 50 + i * 84
        d.rounded_rectangle([cx0, y + 170, cx0 + s, y + 170 + s], radius=10, fill=col)
        d.text((cx0 + s // 2 - 7, y + 170 + s // 2 - 12), "A", font=font("ExtraBold", max(11, s // 2)), fill=WHITE)
    d.rounded_rectangle([x + 26, y + h - 108, x + w - 26, y + h - 76], radius=8, fill=(16, 179, 254))
    d.rounded_rectangle([x + 26, y + h - 64, x + w - 90, y + h - 46], radius=8, fill=(214, 224, 236))

def make(title_lines, kicker, sub, out, card):
    img = bg()
    d = ImageDraw.Draw(img)
    if card == "browser":
        browser_card(d, W - 420, 119, 330, 390)
    else:
        tab_card(d, W - 420, 119, 330, 390)
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

make(["Free Favicon", "Generator"],
     "FREE TOOL",
     "Turn your logo into every favicon size your website needs — ICO, PNGs, Apple touch icon — right in your browser. No account, no upload.",
     os.path.join(BASE, "favicon-generator-featured.jpg"), card="browser")

make(["What Is a Favicon?", "(Free Maker + Guide)"],
     "NEW ON THE BLOG",
     "That tiny icon in your browser tab matters more than you think. Here is what a favicon does, why yours is missing, and the 5-minute fix.",
     os.path.join(BASE, "favicon-blog-featured.jpg"), card="tab")
