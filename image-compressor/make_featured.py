#!/usr/bin/env python3
"""Featured images for the Altus Image Compressor tool + blog post.
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
ACC = (16, 179, 254)

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

def photo_card(d, x, y, w, h, label, small=False):
    d.rounded_rectangle([x, y, x + w, y + h], radius=16, fill=WHITE)
    # fake "photo": mountain landscape blocks
    d.rectangle([x, y, x + w, y + h - (60 if not small else 46)], fill=(214, 231, 248))
    d.polygon([(x, y + h - (60 if not small else 46)),
               (x + w * 0.45, y + 40), (x + w, y + h - (60 if not small else 46))],
              fill=(120, 170, 220))
    d.ellipse([x + w - 90, y + 40, x + w - 40, y + 90], fill=(255, 214, 120))
    f = font("SemiBold", 22)
    d.text((x + 16, y + h - 42), label, font=f, fill=(100, 116, 140))

def shrink_motif(d):
    # big image card -> arrow -> small image card, stacked on the right side
    photo_card(d, 790, 100, 290, 220, "4.2 MB")
    # vertical arrow from big card to small card
    ax, ay1, ay2 = 935, 332, 396
    d.line([(ax, ay1), (ax, ay2)], fill=ACC, width=10)
    d.polygon([(ax - 26, ay2), (ax + 26, ay2), (ax, ay2 + 40)], fill=ACC)
    photo_card(d, 855, 448, 160, 150, "0.6 MB", small=True)
    # "-86%" pill to the right of the arrow
    f = font("Bold", 30)
    t = "-86%"
    tw = d.textlength(t, font=f)
    d.rounded_rectangle([990, 340, 990 + tw + 56, 396], radius=28, fill=ACC)
    d.text((990 + 28, 348), t, font=f, fill=WHITE)

def blog_motif(d):
    # stacked compressed-image cards like the QR blog card
    d.rounded_rectangle([780, 119, 1110, 509], radius=18, fill=(241, 244, 248))
    photo_card(d, 806, 145, 278, 190, "4.2 MB")
    d.rounded_rectangle([806, 360, 1084, 384], radius=12, fill=(214, 224, 236))
    d.rounded_rectangle([806, 392, 1010, 412], radius=10, fill=(232, 238, 246))
    d.rounded_rectangle([806, 440, 920, 476], radius=18, outline=ACC, width=3)
    f = font("SemiBold", 24)
    d.text((938, 442), "COMPRESSED", font=f, fill=ACC)

def make(title_lines, kicker, sub, out, motif):
    img = bg()
    d = ImageDraw.Draw(img)
    if motif == "shrink":
        shrink_motif(d)
    else:
        blog_motif(d)
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

make(["Free Image", "Compressor"],
     "FREE TOOL",
     "Shrink JPEG, PNG and WebP images right in your browser — pick the quality, resize in one click, download the smaller file. Nothing uploaded, ever.",
     os.path.join(BASE, "image-compressor-featured.jpg"), motif="shrink")

make(["Big Images,", "Slow Website?"],
     "NEW ON THE BLOG",
     "Heavy images are the number-one reason small business websites load slowly. Here's how to shrink them properly — plus a free fix tool that does it in seconds.",
     os.path.join(BASE, "image-blog-featured.jpg"), motif="blog")
