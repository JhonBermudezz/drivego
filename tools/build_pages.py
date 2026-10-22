"""Generate Drive Go London sub pages from the shared shell in index.html.

Usage: python3 tools/build_pages.py .
The header, menus and footer are copied from index.html so every page stays in sync.
"""
import re
import sys

REPO = sys.argv[1]
idx = open(f'{REPO}/index.html').read()

HEAD = idx[:idx.index('<body>')]
SHELL_TOP = idx[idx.index('<body>'):idx.index('  <main id="main">')]
FOOTER = idx[idx.index('  <!-- 9 · Footer -->'):idx.index('</footer>') + len('</footer>')]
TAIL = '\n\n  <script src="assets/js/main.js" defer></script>\n</body>\n</html>\n'


def section(start, end):
    return idx[idx.index(start):idx.index(end)]


PACKAGES = section('    <!-- 4 · Packages + Stripe -->', '    <!-- 5 · Services -->')
SERVICES = section('    <!-- 5 · Services -->', '    <!-- Tagline reveal -->')
CONTACT = section('    <!-- 8 · Contact -->', '    <!-- FAQ -->')
STARS = '<span class="stars" aria-hidden="true">' + '<i class="ph-fill ph-star"></i>' * 5 + '</span>'
WA_ICON = '<svg class="brand brand--whatsapp" viewBox="0 0 24 24" aria-hidden="true"><use href="#i-whatsapp"></use></svg>'


def inner(block, start_tag, end_marker):
    """Return the markup from start_tag up to (not including) end_marker inside block."""
    a = block.index(start_tag)
    return block[a:block.index(end_marker, a)]


PLANS = inner(PACKAGES, '<div class="plans">', '        <div class="pay-methods')
PAY = inner(PACKAGES, '<div class="pay-methods', '      </div>\n    </section>')
SERVICE_CARDS = inner(SERVICES, '<div class="services">', '      </div>\n    </section>')
MORE = re.compile(r'\s*<p class="section__more[^\n]*</p>')
PAY = MORE.sub('', PAY)
SERVICE_CARDS = MORE.sub('', SERVICE_CARDS)
CONTACT_INNER = inner(CONTACT, '<div class="container contact">', '    </section>')


def head(title, desc, path):
    h = HEAD
    h = re.sub(r'<title>.*?</title>', f'<title>{title}</title>', h, flags=re.S)
    h = re.sub(r'<meta name="description" content="[^"]*">', f'<meta name="description" content="{desc}">', h)
    h = re.sub(r'<link rel="canonical" href="[^"]*">', f'<link rel="canonical" href="https://drivegolondon.ca/{path}">', h)
    h = re.sub(r'<meta property="og:url" content="[^"]*">', f'<meta property="og:url" content="https://drivegolondon.ca/{path}">', h)
    h = re.sub(r'<meta property="og:title" content="[^"]*">', f'<meta property="og:title" content="{title}">', h)
    h = re.sub(r'<meta property="og:description" content="[^"]*">', f'<meta property="og:description" content="{desc}">', h)
    h = re.sub(r'\s*<script type="application/ld\+json">.*?</script>', '', h, flags=re.S)
    return h


def shell(path):
    top = SHELL_TOP.replace(' aria-current="page"', '')
    top = top.replace(f'<a class="nav__link" href="{path}">', f'<a class="nav__link" href="{path}" aria-current="page">')
    return top


def hero(eyebrow, title, sub, crumb, actions=''):
    return f'''    <section class="page-hero" aria-labelledby="page-title">
      <div class="container">
        <p class="crumbs"><a href="index.html">Home</a><span aria-hidden="true">/</span><span>{crumb}</span></p>
        <p class="eyebrow">{eyebrow}</p>
        <h1 class="page-hero__title" id="page-title">{title}</h1>
        <p class="page-hero__sub">{sub}</p>{actions}
      </div>
    </section>
'''


def cta(title, sub, label='View packages', href='packages.html'):
    return f'''    <section class="section section--soft">
      <div class="container cta-band">
        <div>
          <h2 class="section__title">{title}</h2>
          <p class="section__sub">{sub}</p>
        </div>
        <div class="hero__actions">
          <a class="btn btn--dark btn--lg" href="{href}">{label} <i class="ph ph-arrow-right" aria-hidden="true"></i></a>
          <a class="btn btn--outline btn--lg" href="https://wa.me/15196943762" target="_blank" rel="noopener">{WA_ICON}WhatsApp</a>
        </div>
      </div>
    </section>
'''


def faq(items):
    rows = '\n'.join(f'''          <details>
            <summary>{q}</summary>
            <p>{a}</p>
          </details>''' for q, a in items)
    return f'''    <section class="section" aria-labelledby="faq-title">
      <div class="container faq">
        <header class="faq__head reveal">
          <p class="eyebrow">FAQ</p>
          <h2 class="section__title" id="faq-title">Good to know</h2>
          <p class="section__sub">Still not sure? Call or WhatsApp us at (519) 694 3762.</p>
        </header>
        <div class="faq__list reveal">
{rows}
        </div>
      </div>
    </section>
'''


def page(path, title, desc, main):
    html = head(title, desc, path) + shell(path) + '  <main id="main">\n' + main + '  </main>\n\n' + FOOTER + TAIL
    open(f'{REPO}/{path}', 'w').write(html)
    print('wrote', path)


YES = '<i class="ph-fill ph-check-circle yes" aria-label="Included"></i>'
NO = '<i class="ph ph-minus-circle no" aria-label="Not included"></i>'


def row(label, a, b, c):
    return f'            <tr><td>{label}</td><td>{a}</td><td class="col-featured">{b}</td><td>{c}</td></tr>'


# Packages ---------------------------------------------------------------
page('packages.html', 'Driving packages | Drive Go London',
     'Compare Drive Go London driving packages: BDE course, lessons behind the wheel, home pick up and the car for your road test. Pay online with Stripe.',
     hero('Packages', 'Choose your driving package', 'Three packages built around the MTO approved BDE course. Pick the level of support you need and pay securely online in two minutes.', 'Packages')
     + f'''    <section class="section section--soft" id="packages" aria-label="Packages">
      <div class="container">
        {PLANS}
        {PAY}
      </div>
    </section>

    <section class="section" aria-labelledby="compare-title">
      <div class="container">
        <header class="section__head reveal">
          <p class="eyebrow">Side by side</p>
          <h2 class="section__title" id="compare-title">Compare packages</h2>
        </header>
        <div class="compare reveal">
          <table>
            <thead>
              <tr><th scope="col">What you get</th><th scope="col">Basic<small>$649 + HST</small></th><th scope="col" class="col-featured">Standard<small>$899 + HST</small></th><th scope="col">Complete<small>$1,149 + HST</small></th></tr>
            </thead>
            <tbody>
{row('MTO approved BDE course, online or in class', YES, YES, YES)}
{row('Hours behind the wheel', '10 h', '14 h', '18 h')}
{row('G2 road test four months sooner', YES, YES, YES)}
{row('BDE certificate for insurance savings', YES, YES, YES)}
{row('Meeting point at White Oaks Mall', YES, YES, YES)}
{row('Pick up and drop off at home', NO, YES, YES)}
{row('Flexible lesson times, weekdays and weekends', NO, YES, YES)}
{row('Warm up lesson on the test route', NO, NO, YES)}
{row('Our car for your road test', NO, NO, YES)}
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <section class="section section--soft" aria-labelledby="bde-title">
      <div class="container split">
        <div class="prose reveal">
          <p class="eyebrow">Included in every package</p>
          <h2 class="section__title" id="bde-title">The BDE course, explained</h2>
          <p>Beginner Driver Education is the Ministry of Transportation program that turns a G1 into a confident, test ready driver. Drive Go London is an MTO approved BDE course provider.</p>
          <ul class="checklist">
            <li><i class="ph-fill ph-check-circle" aria-hidden="true"></i><span><strong>30 hours of online learning</strong> at your own pace, anytime, anywhere.</span></li>
            <li><i class="ph-fill ph-check-circle" aria-hidden="true"></i><span><strong>10 hours of in car training</strong> with a certified instructor.</span></li>
            <li><i class="ph-fill ph-check-circle" aria-hidden="true"></i><span><strong>Official MTO BDE certificate</strong> when you finish.</span></li>
            <li><i class="ph-fill ph-check-circle" aria-hidden="true"></i><span><strong>G2 road test after 8 months</strong> with your G1 instead of 12.</span></li>
            <li><i class="ph-fill ph-check-circle" aria-hidden="true"></i><span><strong>Lower insurance rates</strong> with many Ontario insurers.</span></li>
          </ul>
        </div>
        <figure class="split__media reveal">
          <img src="assets/img/gallery/61.webp" alt="BDE certified complete course flyer: 30 hours online learning, 10 hours in vehicle training and an MTO BDE certificate" width="900" height="1350" loading="lazy">
        </figure>
      </div>
    </section>

'''
     + faq([
         ('Do I need my G1 before I start?', 'You need a valid G1 for lessons behind the wheel. You can start the online BDE course before you have it.'),
         ('Can I pay in instalments or in cash?', 'Pay online with any credit or debit card through Stripe, or pay by Interac e&#8209;Transfer or cash before your first lesson. Ask us about splitting the payment.'),
         ('Can I upgrade my package later?', 'Yes. Pay the difference and we add the extra hours, home pick up or the car for your test.'),
         ('What happens after I pay?', 'We call or WhatsApp you within one business day to schedule your first lesson and send your BDE course access.'),
     ])
     + cta('Not sure which package fits?', 'Tell us your licence level and we will recommend one.', 'Ask us', 'contact.html'))

# Services ---------------------------------------------------------------
services_cards = SERVICE_CARDS.replace('<article class="service reveal">', '<article class="service reveal" id="lessons">', 1)
services_cards = services_cards.replace('<article class="service reveal">', '<article class="service reveal" id="refresher">', 1)
page('services.html', 'Driving services | Drive Go London',
     'Single driving lessons of 1, 1.5 or 2 hours, G2 and G road test packages and G-Full and G2 refresher lessons in London, Ontario.',
     hero('Services', 'Lessons and services on their own', 'Already have a package, or only need a few hours before your test? Book single services, priced by the lesson.', 'Services')
     + f'''    <section class="section section--soft" aria-label="Services">
      <div class="container">
        {services_cards}
      </div>
    </section>

    <section class="section" aria-labelledby="how-title">
      <div class="container">
        <header class="section__head reveal">
          <p class="eyebrow">How it works</p>
          <h2 class="section__title" id="how-title">Book in three steps</h2>
        </header>
        <ol class="steps">
          <li class="step reveal"><h3>Pick your service</h3><p>Choose a single lesson length, a road test package or a refresher.</p></li>
          <li class="step reveal"><h3>Send us your details</h3><p>Use the form or WhatsApp with your licence level and the days that work for you.</p></li>
          <li class="step reveal"><h3>Get on the road</h3><p>We confirm the time, pick you up or meet you at White Oaks Mall and start driving.</p></li>
        </ol>
      </div>
    </section>

    <section class="section section--soft" aria-labelledby="who-title">
      <div class="container split">
        <figure class="split__media reveal">
          <img src="assets/img/car.webp" alt="The blue Drive Go London training car with its Student Driver sign" width="900" height="562" loading="lazy">
        </figure>
        <div class="prose reveal">
          <p class="eyebrow">Who it is for</p>
          <h2 class="section__title" id="who-title">Lessons for every level</h2>
          <ul class="checklist">
            <li><i class="ph-fill ph-check-circle" aria-hidden="true"></i><span>New G1 drivers who want extra practice between package lessons.</span></li>
            <li><i class="ph-fill ph-check-circle" aria-hidden="true"></i><span>G2 drivers getting ready for the full G and highway driving.</span></li>
            <li><i class="ph-fill ph-check-circle" aria-hidden="true"></i><span>Drivers new to Canada learning Ontario rules of the road.</span></li>
            <li><i class="ph-fill ph-check-circle" aria-hidden="true"></i><span>Anyone getting back behind the wheel after time away.</span></li>
          </ul>
          <p>All lessons are available in English or Spanish.</p>
        </div>
      </div>
    </section>

'''
     + cta('Want the full program?', 'Our packages include the BDE course and save you money on lessons.'))

# Road test --------------------------------------------------------------
page('road-test.html', 'G2 and G road test packages | Drive Go London',
     'G2 and full G road test packages in London, Ontario: warm up lesson on the test route, our car for the test and tips from a former DriveTest examiner.',
     hero('Road test', 'Walk into your road test ready', 'Prepare with a former DriveTest examiner, warm up on the real test route and use our car on test day.', 'Road test')
     + f'''    <section class="section section--soft" aria-labelledby="tests-title">
      <div class="container">
        <header class="section__head reveal">
          <p class="eyebrow">Road test packages</p>
          <h2 class="section__title" id="tests-title">Choose your test</h2>
        </header>
        <div class="tests">
          <article class="plan reveal">
            <h3 class="plan__name">G2 road test</h3>
            <p class="plan__desc">Your first road test. City driving, turns, parking and right of way.</p>
            <p class="plan__price"><span class="plan__amount">$189</span><span class="plan__tax">+ HST</span></p>
            <ul class="plan__list">
              <li class="is-on"><i class="ph-fill ph-check-circle" aria-hidden="true"></i>Pick up 90 minutes before your test</li>
              <li class="is-on"><i class="ph-fill ph-check-circle" aria-hidden="true"></i>Warm up lesson on the DriveTest route</li>
              <li class="is-on"><i class="ph-fill ph-check-circle" aria-hidden="true"></i>Our car for the test</li>
              <li class="is-on"><i class="ph-fill ph-check-circle" aria-hidden="true"></i>Drop off after your test</li>
            </ul>
            <a class="btn btn--dark btn--block" href="contact.html" data-topic="Road test package">Book G2 test package</a>
          </article>
          <article class="plan reveal">
            <h3 class="plan__name">Full G road test</h3>
            <p class="plan__desc">Your final test. Highway driving, merging and advanced manoeuvres.</p>
            <p class="plan__price"><span class="plan__amount">$209</span><span class="plan__tax">+ HST</span></p>
            <ul class="plan__list">
              <li class="is-on"><i class="ph-fill ph-check-circle" aria-hidden="true"></i>Pick up 90 minutes before your test</li>
              <li class="is-on"><i class="ph-fill ph-check-circle" aria-hidden="true"></i>Highway warm up on the test route</li>
              <li class="is-on"><i class="ph-fill ph-check-circle" aria-hidden="true"></i>Our car for the test</li>
              <li class="is-on"><i class="ph-fill ph-check-circle" aria-hidden="true"></i>Drop off after your test</li>
            </ul>
            <a class="btn btn--dark btn--block" href="contact.html" data-topic="Road test package">Book G test package</a>
          </article>
        </div>
        <div class="note reveal" style="margin-top: 24px;"><i class="ph ph-info" aria-hidden="true"></i><p>DriveTest fees are paid by you directly to DriveTest and are not included. Book your test date at drivetest.ca, then send us the date and time.</p></div>
      </div>
    </section>

    <section class="section" aria-labelledby="day-title">
      <div class="container">
        <header class="section__head reveal">
          <p class="eyebrow">Test day</p>
          <h2 class="section__title" id="day-title">How test day works</h2>
        </header>
        <ol class="steps steps--4">
          <li class="step reveal"><h3>We pick you up</h3><p>90 minutes before your test, from home, school or work.</p></li>
          <li class="step reveal"><h3>Warm up drive</h3><p>We drive the roads around the DriveTest centre so nothing surprises you.</p></li>
          <li class="step reveal"><h3>Your test</h3><p>You take the test in the car you already practised in.</p></li>
          <li class="step reveal"><h3>Back home</h3><p>We drop you off and go over the examiner notes together.</p></li>
        </ol>
      </div>
    </section>

    <section class="section section--soft" aria-labelledby="tips-title">
      <div class="container split split--top">
        <div class="prose reveal">
          <p class="eyebrow">From a former examiner</p>
          <h2 class="section__title" id="tips-title">What examiners look for</h2>
          <p>Steven spent years on the other side of the clipboard as a DriveTest examiner. These are the habits he trains first.</p>
          <ul class="checklist">
            <li><i class="ph-fill ph-check-circle" aria-hidden="true"></i><span><strong>Full stops</strong> behind the line, every time.</span></li>
            <li><i class="ph-fill ph-check-circle" aria-hidden="true"></i><span><strong>Shoulder checks</strong> before every lane change and turn.</span></li>
            <li><i class="ph-fill ph-check-circle" aria-hidden="true"></i><span><strong>Safe following distance</strong> of at least two seconds.</span></li>
            <li><i class="ph-fill ph-check-circle" aria-hidden="true"></i><span><strong>Smooth speed control</strong> in school and construction zones.</span></li>
          </ul>
        </div>
        <div class="card reveal">
          <div class="card__icon"><i class="ph ph-backpack" aria-hidden="true"></i></div>
          <h3>What to bring</h3>
          <ul class="checklist">
            <li><i class="ph ph-identification-card" aria-hidden="true"></i><span>Your valid G1 or G2 licence</span></li>
            <li><i class="ph ph-eyeglasses" aria-hidden="true"></i><span>Glasses or contacts if your licence requires them</span></li>
            <li><i class="ph ph-receipt" aria-hidden="true"></i><span>Your DriveTest booking confirmation</span></li>
            <li><i class="ph ph-credit-card" aria-hidden="true"></i><span>A payment method for DriveTest fees</span></li>
          </ul>
        </div>
      </div>
    </section>

'''
     + cta('Have your test date?', 'Send it to us and we will plan your warm up around it.', 'Book your test package', 'contact.html'))

# About ------------------------------------------------------------------
page('about.html', 'About us | Drive Go London',
     'Meet Drive Go London: founded by Steven Bernal, former DriveTest examiner, and the first Hispanic driving school approved by the MTO in London, Ontario.',
     hero('About us', 'A driving school built around you', 'Patient teaching, real test experience and lessons in English and Spanish, right here in London, Ontario.', 'About us')
     + f'''    <section class="section" aria-labelledby="story-title">
      <div class="container about">
        <figure class="instructor reveal">
          <div class="instructor__photo">
            <img src="assets/img/steven.webp" alt="Steven Bernal, founder and lead instructor of Drive Go London" width="560" height="700" loading="lazy">
            <img class="instructor__badge" src="assets/img/badge.webp" alt="Certified instructor, London" width="320" height="320" loading="lazy">
          </div>
          <figcaption>
            <p class="instructor__name">Steven Bernal</p>
            <p class="instructor__role">Founder, lead instructor and former DriveTest examiner</p>
          </figcaption>
        </figure>
        <div class="about__copy reveal">
          <p class="eyebrow">Our story</p>
          <h2 class="section__title" id="story-title">From examiner to instructor</h2>
          <p>Steven Bernal started Drive Go London after years as a DriveTest examiner. He watched too many nervous students fail for habits nobody had taught them, and decided to teach those habits from the very first lesson.</p>
          <p>Today Steven leads every program and works alongside two more MTO certified instructors. Together they have helped more than 2,000 students get their licence in London and surrounding areas.</p>
          <dl class="stats">
            <div><dt>Students trained</dt><dd data-count="2000" data-suffix="+">2,000+</dd></div>
            <div><dt>Certified instructors</dt><dd data-count="3">3</dd></div>
            <div><dt>Google rating</dt><dd data-count="5" data-decimals="1">5.0</dd></div>
          </dl>
        </div>
      </div>
    </section>

    <section class="section section--soft" aria-labelledby="first-title">
      <div class="container split">
        <div class="prose reveal">
          <p class="eyebrow">A first for London</p>
          <h2 class="section__title" id="first-title">The first Hispanic driving school approved by the MTO in London</h2>
          <p lang="es"><strong>La primera escuela de conducción hispana avalada por el MTO en London.</strong></p>
          <p>The Ministry of Transportation approved Drive Go London as a Beginner Driver Education course provider. Spanish speaking families can now learn to drive and earn their BDE certificate in the language they are most comfortable with.</p>
        </div>
        <figure class="split__media reveal">
          <img src="assets/img/gallery/73.webp" alt="Announcement: Drive Go London is the first Hispanic driving school approved by the MTO in London" width="900" height="1352" loading="lazy">
        </figure>
      </div>
    </section>

    <section class="section" aria-labelledby="values-title">
      <div class="container">
        <header class="section__head reveal">
          <p class="eyebrow">What we stand for</p>
          <h2 class="section__title" id="values-title">Professional. Patient. Results driven.</h2>
        </header>
        <div class="cards">
          <article class="card reveal"><div class="card__icon"><i class="ph ph-briefcase" aria-hidden="true"></i></div><h3>Professional</h3><p>MTO certified instructors, a modern and comfortable training car and a clear plan for every lesson.</p></article>
          <article class="card reveal"><div class="card__icon"><i class="ph ph-hand-heart" aria-hidden="true"></i></div><h3>Patient</h3><p>Small classes and calm instruction for nervous first timers, in English or Spanish.</p></article>
          <article class="card reveal"><div class="card__icon"><i class="ph ph-trophy" aria-hidden="true"></i></div><h3>Results driven</h3><p>Every lesson builds toward the test routes you will drive, with insight from a former examiner.</p></article>
        </div>
      </div>
    </section>

    <section class="section section--soft" aria-labelledby="mission-title">
      <div class="container split split--top">
        <div class="prose reveal">
          <p class="eyebrow">Mission</p>
          <h2 class="section__title" id="mission-title">Safe drivers for life</h2>
          <p>Help every student in London pass their test with confidence and keep the safe habits that protect them on the road for years.</p>
        </div>
        <div class="prose reveal">
          <p class="eyebrow">Vision</p>
          <h2 class="section__title">The school families recommend</h2>
          <p>Be the driving school London families, and especially the Hispanic community, trust and recommend first.</p>
        </div>
      </div>
    </section>

'''
     + cta('Learn with Steven and the team', 'Pick a package and book your first lesson this week.'))

# Contact ----------------------------------------------------------------
contact_inner = re.sub(r'\s*<ul class="contact__list">.*?</ul>', '', CONTACT_INNER, flags=re.S)
contact_inner = contact_inner.replace('<p class="eyebrow">Contact</p>', '<p class="eyebrow">Where we meet</p>')
contact_inner = contact_inner.replace('<h2 class="section__title" id="contact-title">Let\'s get you on the road</h2>', '<h2 class="section__title" id="contact-title">White Oaks Mall and home pick up</h2>')
page('contact.html', 'Contact | Drive Go London',
     'Call, WhatsApp or message Drive Go London, an MTO approved driving school in London, Ontario. Lessons weekdays and weekends.',
     hero('Contact', "Let's get you on the road", 'Call, WhatsApp or send a message. We reply within one business day, in English or Spanish.', 'Contact')
     + f'''    <section class="section section--tight" id="contact" aria-labelledby="contact-title">
      <div class="contact-cards container">
        <a class="contact-card" href="tel:+15196943762"><span class="contact__icon"><i class="ph ph-phone" aria-hidden="true"></i></span><strong>Call</strong><span>(519) 694 3762</span></a>
        <a class="contact-card" href="https://wa.me/15196943762" target="_blank" rel="noopener"><span class="contact__icon">{WA_ICON}</span><strong>WhatsApp</strong><span>Message us now</span></a>
        <a class="contact-card" href="mailto:info@drivegolondon.ca"><span class="contact__icon"><i class="ph ph-envelope-simple" aria-hidden="true"></i></span><strong>Email</strong><span>info@drivegolondon.ca</span></a>
        <div class="contact-card"><span class="contact__icon"><i class="ph ph-clock" aria-hidden="true"></i></span><strong>Hours</strong><span>Weekdays and weekends, by appointment</span></div>
      </div>
      {contact_inner}
    </section>

''')

# Gallery: refresh its shell so the menu matches -----------------------------
g = open(f'{REPO}/gallery.html').read()
g_main = g[g.index('  <main id="main">'):g.index('  <!-- 9 · Footer -->')]
g_tail = g[g.index('</footer>') + len('</footer>'):]
g_head = g[:g.index('<body>')]
open(f'{REPO}/gallery.html', 'w').write(g_head + shell('gallery.html') + g_main + FOOTER + g_tail)
print('refreshed gallery.html')
