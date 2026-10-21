# Drive Go London · Website

Static site (HTML, CSS, vanilla JS). No build step: upload the folder to any static host (Netlify, Vercel, Cloudflare Pages, GitHub Pages).

## Local preview

```bash
python3 -m http.server 8080
# open http://localhost:8080
```

## Structure (follows the wireframe)

1. Top bar + header (phone, email, socials, nav, "Book now")
2. Hero
3. Trust bar
4. Packages with Stripe checkout
5. Services
6. Tagline reveal (word by word on scroll)
7. About us
8. Student reviews
9. Contact (info, map, form)
10. Footer

Also `privacy.html`, `terms.html`, `404.html`, `robots.txt`, `sitemap.xml`.

## Before launch

- **Stripe:** create one Payment Link per package and paste them in `STRIPE_LINKS` at the top of `assets/js/main.js`. While a link is empty, "Buy now" scrolls to the contact form with that package selected.
- **Contact form:** set `FORM_ENDPOINT` in `assets/js/main.js` (Formspree, Basin...). Empty = opens the visitor's email app.
- **Placeholder data to replace:** prices, hours per package, phone, email, address, stats (students, instructors), reviews, social links, domain in meta tags.
- **Photos:** stock images from Unsplash in `assets/img/`. Replace with real photos of students, instructors and the car keeping the same file names.
- **Legal pages:** drafts, have them reviewed.
