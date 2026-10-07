# Altus Micro-SaaS Tools

A collection of free micro-SaaS WordPress plugins built as lead magnets for agencies and freelancers. Each tool is a self-contained plugin: install, activate, drop the shortcode on a page.

## Tools included

| Tool | Directory | What it does |
|---|---|---|
| School Website Compliance Checker | `school-checker/` | Free compliance check for UK school websites (statutory requirements) — shows issues, agency fixes them |
| Website Grader | `website-grader/` | Grades any SME/trade business website (speed, SEO, mobile, trust signals) with a visual report |
| Cookie Policy Generator | `cookie-policy-generator/` | Generates a UK-GDPR cookie policy page from a short questionnaire |
| Privacy Policy Generator | `privacy-policy-generator/` | Generates a tailored privacy policy for UK small businesses |
| QR Code Generator | `qr-code-generator/` | Free QR codes (URL, WiFi, vCard, text) with styling options |
| Review Link Generator | `review-link-generator/` | Creates one-click Google review links for local businesses |
| Meta Description Checker | `meta-description-checker/` | Live SERP-preview checker for page titles and meta descriptions |
| Favicon Generator | `favicon-generator/` | Generates full favicon packs from any uploaded image |
| Image Compressor | `image-compressor/` | Client-side image compression before upload |

## Strategy

Free tools do the selling: a visitor runs a free check → sees real problems → the agency fixes them. Zero ad spend, compounding SEO pages, and a permanent portfolio.

## Tech stack

- PHP (WordPress plugins, shortcodes, admin pages)
- Vanilla JS + canvas (client-side generators)
- No build step, no dependencies

## Install (any tool)

1. Copy the tool's folder to `wp-content/plugins/`
2. Activate in WordPress admin
3. Add the tool's shortcode to any page (see each tool's header comment)

Built by [Altus Web Solutions](https://altuswebsolutions.com).
