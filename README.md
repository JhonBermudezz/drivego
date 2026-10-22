# Drive Go London · Website

Static site (HTML, CSS, vanilla JS). No build step: upload the folder to any static host (Netlify, Vercel, Cloudflare Pages, GitHub Pages).

## Live preview (GitHub Pages)

Settings > Pages > Build and deployment > Source: **Deploy from a branch**, branch `claude/elegant-planck-osohs1`, folder `/ (root)`. The site is then published at https://jhonbermudezz.github.io/drivego/ and updates on every push.

## Local preview

```bash
python3 -m http.server 8080
# open http://localhost:8080
```

## Structure (follows the wireframe)

1. Top bar + header (phone, email, socials, nav with Road test shortcut, "Book now")
2. Hero: full width photo with white card (client approved reference)
3. Trust bar
4. Packages with Square checkout: Basic (meet at White Oaks Mall), Standard (home pick up), Complete (car for the test). Payment methods: card, e-Transfer, cash
5. Services: single lessons with 1 h / 1.5 h / 2 h picker, G2 and G road test, G-Full & G2 Refresher
6. Tagline reveal (word by word on scroll)
7. About us: only Steven's photo as lead instructor, mentions the other two instructors
8. Student reviews
9. FAQ (draft, waiting for Steven's list)
10. Contact (info, map, form)
11. Footer

Sub pages: `packages.html`, `services.html`, `road-test.html`, `about.html`, `gallery.html` and `contact.html`, plus `privacy.html`, `terms.html`, `404.html`, `robots.txt`, `sitemap.xml`.

### Header, menu and footer

They are edited in `index.html` only. Then run:

```bash
python3 tools/build_pages.py .
```

It rebuilds the sub pages (packages, services, road test, about, contact) and refreshes the gallery menu so every page stays in sync. Page specific content lives in `tools/build_pages.py`.

## Before launch

- **Square checkout (packages):** packages start with the theory course, so they only take a payment. Create one Square checkout link per package and paste it in `SQUARE_CHECKOUT_LINKS` at the top of `assets/js/main.js`.
- **Square Appointments (services):** single lessons, road test packages and the G-Full and G2 refresher are booked on a calendar and paid at booking. Set up the services in Square Appointments (with intake questions such as licence level and pick up address) and paste each online booking link in `SQUARE_BOOKING_LINKS`. "Book and pay" buttons open it, and the road test page shows the calendar inline in place of the request form. Bookings, payments and customers appear in the Square dashboard.
- **Booking calendar:** until Square Appointments links are set, "Book and pay" opens the built in calendar (`assets/js/booking.js`): option and price, day, time, quick questions and a summary. Edit `BOOKING` at the top of that file for working hours, slot length, closed dates, prices and a Square checkout link per option (`pay`), which opens after the request is sent. Requests go to `FORM_ENDPOINT` (or the email app if it is empty) and Steven confirms each one. The road test page shows the same calendar inline.
- Package buttons without a checkout link fall back to the contact page with the topic preselected.
- **Contact form:** set `FORM_ENDPOINT` in `assets/js/main.js` (Formspree, Basin...). Empty = opens the visitor's email app.
- **Placeholder data to replace:** prices, hours per package, lesson and road test prices, FAQ answers, social links (Instagram, Facebook, TikTok) and stats to confirm (2,000+ students vs 350+ on the flyers).
- **Gallery:** items live in `ITEMS` at the top of `assets/js/gallery.js` (photos in `assets/img/gallery/`). For a video add `type: 'video'` with `src` (file in `assets/videos/`) or `youtube`, plus a cover `image`.
- **Photos:** stock images from Unsplash in `assets/img/`. Replace with real photos of students, instructors and the car keeping the same file names.
- **Legal pages:** drafts, have them reviewed.
