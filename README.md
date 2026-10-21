# Drive Go London · Website

Static site (HTML, CSS, vanilla JS). No build step: upload the folder to any static host (Netlify, Vercel, Cloudflare Pages, GitHub Pages).

## Local preview

```bash
python3 -m http.server 8080
# open http://localhost:8080
```

## Structure (follows the wireframe)

1. Top bar + header (phone, email, socials, nav with Road test shortcut, "Book now")
2. Hero: full width photo with white card (client approved reference)
3. Trust bar
4. Packages with Stripe checkout: Basic (meet at White Oaks Mall), Standard (home pick up), Complete (car for the test). Payment methods: card, e-Transfer, cash
5. Services: single lessons with 1 h / 1.5 h / 2 h picker, G2 and G road test, seniors and refresher
6. Video (YouTube)
7. Tagline reveal (word by word on scroll)
8. About us: only Steven's photo as lead instructor, mentions the other two instructors
9. Student reviews
10. FAQ (draft, waiting for Steven's list)
11. Contact (info, map, form)
12. Footer

Also `privacy.html`, `terms.html`, `404.html`, `robots.txt`, `sitemap.xml`.

## Before launch

- **Stripe:** create one Payment Link per package and paste them in `STRIPE_LINKS` at the top of `assets/js/main.js`. While a link is empty, "Buy now" scrolls to the contact form with that package selected.
- **Contact form:** set `FORM_ENDPOINT` in `assets/js/main.js` (Formspree, Basin...). Empty = opens the visitor's email app.
- **Placeholder data to replace:** prices, hours per package, lesson and road test prices, phone, reviews, FAQ answers, social links, domain in meta tags.
- **YouTube:** put the video id in `data-youtube-id` on `.video__frame` in `index.html`.
- **Steven's photo:** replace the `SB` placeholder in the About section.
- **Photos:** stock images from Unsplash in `assets/img/`. Replace with real photos of students, instructors and the car keeping the same file names.
- **Legal pages:** drafts, have them reviewed.
