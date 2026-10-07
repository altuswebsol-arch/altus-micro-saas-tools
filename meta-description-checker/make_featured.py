#!/usr/bin/env python3
"""Featured images for the Altus Meta Description Checker tool + blog post.
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
GREEN = (34, 163, 85)
SERPT = (26, 13, 171)
SERPD = (77, 81, 86)

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

def wrap_lines(d, text, fnt, max_w):
    words, lines, line = text.split(), [], ""
    for w in words:
        test = (line + " " + w).strip()
        if d.textlength(test, font=fnt) <= max_w:
            line = test
        else:
            lines.append(line)
            line = w
    if line:
        lines.append(line)
    return lines

def serp_card(d, x, y, w):
    """A Google-style search result card; returns bottom y."""
    pad = 24
    maxw = w - pad * 2
    lines = []  # (text, font, fill, step)
    f_url, f_t, f_d = font("Regular", 20), font("Regular", 26), font("Regular", 22)
    lines.append(("yourwebsite.com › your-page", f_url, (60, 64, 67), 34))
    for ln in wrap_lines(d, "Affordable Web Design for Small Businesses | Altus", f_t, maxw):
        lines.append((ln, f_t, SERPT, 34))
    for ln in wrap_lines(d, "We build fast, beautiful websites for UK small businesses.", f_d, maxw):
        lines.append((ln, f_d, SERPD, 30))
    height = 20 + sum(step for _, _, _, step in lines) + 22
    d.rounded_rectangle([x, y, x + w, y + height], radius=16, fill=WHITE)
    yy = y + 20
    for text, fnt, fill, step in lines:
        d.text((x + pad, yy), text, font=fnt, fill=fill)
        yy += step
    return y + height

def shrink_motif(d):
    bottom = serp_card(d, 770, 110, 350)
    # score pill under the card
    f = font("Bold", 34)
    t = "92 / 100"
    tw = d.textlength(t, font=f)
    d.rounded_rectangle([770, bottom + 24, 770 + tw + 64, bottom + 96], radius=36, fill=GREEN)
    d.text((770 + 32, bottom + 36), t, font=f, fill=WHITE)
    # magnifier accent
    d.ellipse([1052, 452, 1116, 516], outline=ACC, width=10)
    d.line([(1102, 502), (1140, 540)], fill=ACC, width=12)

def blog_motif(d):
    bottom = serp_card(d, 770, 80, 350)
    # checklist lines under the card
    f = font("SemiBold", 26)
    items = ["Length: good", "Keyword: near start", "Call to action: yes"]
    yy = bottom + 30
    for it in items:
        d.ellipse([786, yy, 814, yy + 28], fill=GREEN)
        # drawn check (Poppins has no check glyph)
        d.line([(793, yy + 14), (799, yy + 20)], fill=WHITE, width=3)
        d.line([(799, yy + 20), (808, yy + 8)], fill=WHITE, width=3)
        d.text((830, yy - 4), it, font=f, fill=WHITE)
        yy += 48

def make(title_lines, kicker, sub, out, motif, title_size=64, title_step=78):
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
        d.text((64, y), ln, font=font("ExtraBold", title_size), fill=WHITE)
        y += title_step
    wrapped(d, (64, y + 14), sub, font("Regular", 31), GRAY, 636, 46)
    img.save(out, quality=92)
    print("wrote", out)

make(["Free Meta", "Description", "Checker"],
     "FREE TOOL",
     "See exactly how Google shows your page — length, keyword use and call to action scored, with a live search preview. Free, no account.",
     os.path.join(BASE, "meta-description-checker-featured.jpg"), motif="shrink")

make(["What Is a Meta", "Description?"],
     "NEW ON THE BLOG",
     "The 155 characters that decide whether anyone clicks your Google listing — how to write one Google won't rewrite, plus a free checker.",
     os.path.join(BASE, "meta-blog-featured.jpg"), motif="blog")
