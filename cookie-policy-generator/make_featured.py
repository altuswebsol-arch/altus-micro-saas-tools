#!/usr/bin/env python3
"""Featured images for the Altus Cookie Policy Generator tool + blog post.
Locked Altus style: deep navy gradient, electric blue, bold Poppins."""
from PIL import Image, ImageDraw, ImageFont
import os, random, math

BASE = os.path.dirname(os.path.abspath(__file__))
FDIR = os.path.expanduser("~/workspace/altus-bot/saas-tools/fonts")
W, H = 1200, 628

NAVY_TOP = (13, 32, 64)
NAVY_BOT = (5, 11, 24)
BLUE = (46, 107, 255)
BLUE_LT = (0, 194, 255)
WHITE = (255, 255, 255)
GRAY = (178, 192, 216)
COOKIE = (222, 184, 135)
CHIP = (90, 55, 30)

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

def cookie_card(d, x, y, w, h):
    """Decorative cookie card: white card with a chocolate-chip cookie."""
    rnd = random.Random(7)
    d.rounded_rectangle([x, y, x + w, y + h], radius=18, fill=(255, 255, 255))
    cx, cy, r = x + w / 2, y + h / 2 - 10, 105
    # cookie body with slightly wavy edge
    pts = []
    for k in range(72):
        ang = k * 5
        rad = r + rnd.uniform(-7, 7)
        pts.append((cx + rad * math.cos(math.radians(ang)),
                    cy + rad * math.sin(math.radians(ang))))
    d.polygon(pts, fill=COOKIE)
    # chips
    for _ in range(9):
        ang = rnd.uniform(0, 360)
        dist = rnd.uniform(12, r - 26)
        px_ = cx + dist * math.cos(math.radians(ang))
        py_ = cy + dist * math.sin(math.radians(ang))
        cr = rnd.uniform(9, 14)
        d.ellipse([px_ - cr, py_ - cr, px_ + cr, py_ + cr], fill=CHIP)
    # bite taken out (top-right)
    d.ellipse([cx + r - 70, cy - r - 18, cx + r + 34, cy - r + 86], fill=(13, 32, 64))
    # caption bar
    d.rounded_rectangle([x + 34, y + h - 84, x + w - 34, y + h - 52], radius=8, fill=(226, 234, 248))

def doc_card(d, x, y, w, h):
    """Decorative policy-document card."""
    d.rounded_rectangle([x, y, x + w, y + h], radius=18, fill=(255, 255, 255))
    d.rounded_rectangle([x + 34, y + 34, x + w - 34, y + 66], radius=8, fill=(16, 179, 254))
    for i in range(6):
        ly = y + 100 + i * 40
        lw = w - 68 - (i % 3) * 44
        d.rounded_rectangle([x + 34, ly, x + 34 + lw, ly + 14], radius=7, fill=(226, 234, 248))
    # checkmark seal
    sx, sy, sr = x + w - 76, y + h - 76, 30
    d.ellipse([sx - sr, sy - sr, sx + sr, sy + sr], fill=(16, 179, 254))
    d.line([sx - 14, sy, sx - 4, sy + 10], fill=WHITE, width=6, joint="curve")
    d.line([sx - 4, sy + 10, sx + 16, sy - 12], fill=WHITE, width=6, joint="curve")

def make(title_lines, kicker, sub, out, card="cookie"):
    img = bg()
    d = ImageDraw.Draw(img)
    if card == "cookie":
        cookie_card(d, W - 400, 124, 300, 390)
    else:
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

make(["Free Cookie Policy", "Generator"],
     "FREE TOOL",
     "Draft a clear, UK-style cookie policy for your website in minutes — answer a few questions, then copy, download or email it. No account needed.",
     os.path.join(BASE, "cookie-policy-generator-featured.jpg"), card="cookie")

make(["Do You Need", "a Cookie Policy?", "(UK Guide)"],
     "NEW ON THE BLOG",
     "If your site sets non-essential cookies, UK rules expect consent first — and a clear policy. Here is what the rules require and the quick fix.",
     os.path.join(BASE, "cookie-policy-blog-featured.jpg"), card="doc")
