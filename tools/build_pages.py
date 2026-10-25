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
TAIL = '\n\n  <script src="assets/js/main.js" defer></script>\n  <script src="assets/js/booking.js" defer></script>\n</body>\n</html>\n'


def section(start, end):
    return idx[idx.index(start):idx.index(end)]


PACKAGES = section('    <!-- 4 · Packages + Square -->', '    <!-- 5 · Services -->')
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
    h = re.sub(r'<link rel="canonical" href="[^"]*">', f'<link rel="canonical" href="https://drivegodrivingschool.com/{path}">', h)
    h = re.sub(r'<meta property="og:url" content="[^"]*">', f'<meta property="og:url" content="https://drivegodrivingschool.com/{path}">', h)
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
        <h1 class="page-hero__title" id="page-title">{title}</h1>
        <p class="page-hero__sub">{sub}</p>{actions}
      </div>
    </section>
'''


def cta(title, sub, label='View packages', href='packages.html'):
    return f'''    <section class="section section--lime">
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
     'Compare Drive Go London driving packages: BDE course, lessons behind the wheel, home pick up and the car for your road test. Pay online with Square.',
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
         ('Can I pay in instalments or in cash?', 'Pay online with any credit or debit card through Square, or pay by Interac e&#8209;Transfer or cash before your first lesson. Ask us about splitting the payment.'),
         ('Can I upgrade my package later?', 'Yes. Pay the difference and we add the extra hours, home pick up or the car for your test.'),
         ('What happens after I pay?', 'We call or WhatsApp you within one business day to schedule your first lesson and send your BDE course access.'),
     ])
     + cta('Not sure which package fits?', 'Tell us your licence level and we will recommend one.', 'Ask us', 'contact.html'))

# Services ---------------------------------------------------------------
def svc(num, id_, title, text, img, alt, items, extra, actions, reverse=False):
    checks = '\n'.join(
        f'            <li><i class="ph-fill ph-check-circle" aria-hidden="true"></i><span>{i}</span></li>' for i in items)
    grid = 'svc__grid svc__grid--reverse' if reverse else 'svc__grid'
    return f'''    <section class="svc" id="{id_}" aria-labelledby="{id_}-title">
      <div class="container {grid}">
        <figure class="svc__media reveal">
          <img src="{img}" alt="{alt}" loading="lazy">
        </figure>
        <div class="svc__body reveal">
          <h2 class="section__title" id="{id_}-title">{title}</h2>
          <p class="svc__text">{text}</p>
{extra}
          <ul class="checklist">
{checks}
          </ul>
          <div class="hero__actions">
{actions}
          </div>
        </div>
      </div>
    </section>
'''


def book_actions(key, topic, second):
    return (f'            <a class="btn btn--dark btn--lg" href="contact.html" data-book="{key}" data-book-topic="{topic}">'
            f'<i class="ph ph-calendar-plus" aria-hidden="true"></i>Book and pay</a>\n{second}')


CONTACT_BTN = '            <a class="btn btn--outline btn--lg" href="contact.html">Contact us</a>'

LESSON_PRICE = '''          <div class="svc__price">
            <div class="duration" role="radiogroup" aria-label="Lesson length">
              <button type="button" role="radio" aria-checked="true" data-price="$65">1 h</button>
              <button type="button" role="radio" aria-checked="false" data-price="$95">1.5 h</button>
              <button type="button" role="radio" aria-checked="false" data-price="$125">2 h</button>
            </div>
            <p class="service__price"><span data-duration-price>$65</span> <span class="plan__tax">+ HST</span></p>
          </div>'''

TEST_PRICE = '''          <div class="svc__tests">
            <div><p class="svc__tests-name">G2 road test</p><p class="service__price">$189 <span class="plan__tax">+ HST</span></p></div>
            <div><p class="svc__tests-name">Full G road test</p><p class="service__price">$209 <span class="plan__tax">+ HST</span></p></div>
          </div>'''

REFRESH_PRICE = '          <p class="service__price">From $65 <span class="plan__tax">+ HST</span></p>'

page('services.html', 'Driving services | Drive Go London',
     'Book and pay online for single driving lessons, G2 and G road test packages and G-Full and G2 refresher lessons in London, Ontario.',
     hero('Services', 'Our services', 'Lessons and programs for every kind of driver. Pick a day and time on our calendar and pay when you book.', 'Services')
     + svc('01', 'lessons', 'Single lessons',
           'One on one lessons with a certified instructor on real London routes, from parking lots to the 401. Choose the length that fits your schedule.',
           'assets/img/incar.webp', 'View from the driver seat during a lesson on a city street',
           ['One on one with a certified instructor', 'Flexible scheduling, weekdays and weekends', 'Pick up at home or meet us at White Oaks Mall', 'In English or Spanish'],
           LESSON_PRICE, book_actions('lessons', 'Single lessons', CONTACT_BTN))
     + svc('02', 'road-test-service', 'Road test package',
           'A warm up on the real test route and our car on test day, coached by a former DriveTest examiner.',
           'assets/img/car.webp', 'The blue Drive Go London training car with its Student Driver sign',
           ['Vehicle for your road test', 'Pre test practice lesson on the DriveTest route', 'Pick up and drop off'],
           TEST_PRICE, book_actions('roadtest', 'Road test package', '            <a class="btn btn--outline btn--lg" href="road-test.html">Road test details</a>'),
           reverse=True)
     + svc('03', 'refresher', 'G-Full &amp; G2 Refresher',
           'For drivers who already drive and need polish before the G2 or full G test, or more confidence on the road.',
           'assets/img/refresher.webp', 'Silver SUV parked on a quiet road, ready for a refresher lesson',
           ['Assessment drive, then we work on your weak spots', 'Highway merging, parking and test route practice', 'Patient, supportive instructors'],
           REFRESH_PRICE, book_actions('refresher', 'G-Full &amp; G2 Refresher', CONTACT_BTN))
     + '''    <section class="section section--soft" aria-labelledby="how-title">
      <div class="container">
        <header class="section__head reveal">
          <h2 class="section__title" id="how-title">Book in three steps</h2>
          <p class="section__sub">From choosing a lesson to your first drive in about two minutes.</p>
        </header>
        <ol class="flow">
          <li class="flow__step reveal">
            <span class="flow__icon"><i class="ph-fill ph-steering-wheel" aria-hidden="true"></i></span>
            <h3>Pick your service</h3>
            <p>Single lesson, road test package or refresher, in English or Spanish.</p>
          </li>
          <li class="flow__step reveal">
            <span class="flow__icon"><i class="ph-fill ph-calendar-check" aria-hidden="true"></i></span>
            <h3>Choose a day and time</h3>
            <p>See open slots on our calendar and answer a few quick questions about your licence.</p>
          </li>
          <li class="flow__step reveal">
            <span class="flow__icon"><i class="ph-fill ph-credit-card" aria-hidden="true"></i></span>
            <h3>Pay and get on the road</h3>
            <p>Pay securely with Square when you book. We pick you up or meet you at White Oaks Mall.</p>
          </li>
        </ol>
        <div class="flow__note reveal">
          <span class="flow__note-icon"><i class="ph-fill ph-graduation-cap" aria-hidden="true"></i></span>
          <p><strong>New driver?</strong> Start with a package. The BDE theory course comes first, so packages are paid online and scheduled with us after you enrol.</p>
          <a class="btn btn--dark" href="packages.html">See packages <i class="ph ph-arrow-right" aria-hidden="true"></i></a>
        </div>
      </div>
    </section>

'''
     + cta('Ready to get started?', 'See our packages or get in touch.', 'View packages', 'packages.html'))

ROADTEST_BOOKING = open(f'{REPO}/tools/partials/roadtest-booking.html').read()

# Road test --------------------------------------------------------------
page('road-test.html', 'G2 and G road test packages | Drive Go London',
     'G2 and full G road test packages in London, Ontario: warm up lesson on the test route, our car for the test and tips from a former DriveTest examiner.',
     hero('Road test', 'Walk into your road test ready', 'Prepare with a former DriveTest examiner, warm up on the real test route and use our car on test day.', 'Road test')
     + f'''    <section class="section section--soft" aria-labelledby="tests-title">
      <div class="container">
        <header class="section__head reveal">
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
            <a class="btn btn--dark btn--block" href="#book" data-calendar-option="roadtest:0">Book G2 test package</a>
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
            <a class="btn btn--dark btn--block" href="#book" data-calendar-option="roadtest:1">Book G test package</a>
          </article>
        </div>
        <div class="note reveal" style="margin-top: 24px;"><i class="ph ph-info" aria-hidden="true"></i><p>DriveTest fees are paid by you directly to DriveTest and are not included. Book your test date at drivetest.ca, then send us the date and time.</p></div>
      </div>
    </section>

''' + ROADTEST_BOOKING + '''    <section class="section section--soft" aria-labelledby="day-title">
      <div class="container">
        <header class="section__head reveal">
          <h2 class="section__title" id="day-title">How test day works</h2>
          <p class="section__sub">From the moment we pick you up to the debrief at your door.</p>
        </header>
        <ol class="flow flow--4">
          <li class="flow__step reveal">
            <span class="flow__icon"><i class="ph-fill ph-car-profile" aria-hidden="true"></i></span>
            <h3>We pick you up</h3>
            <p>90 minutes before your test, from home, school or work.</p>
          </li>
          <li class="flow__step reveal">
            <span class="flow__icon"><i class="ph-fill ph-steering-wheel" aria-hidden="true"></i></span>
            <h3>Warm up drive</h3>
            <p>We drive the roads around the DriveTest centre so nothing surprises you.</p>
          </li>
          <li class="flow__step reveal">
            <span class="flow__icon"><i class="ph-fill ph-seal-check" aria-hidden="true"></i></span>
            <h3>Your test</h3>
            <p>You take the test in the car you already practised in.</p>
          </li>
          <li class="flow__step reveal">
            <span class="flow__icon"><i class="ph-fill ph-house-line" aria-hidden="true"></i></span>
            <h3>Back home</h3>
            <p>We drop you off and go over the examiner notes together.</p>
          </li>
        </ol>
      </div>
    </section>

    <section class="section" aria-labelledby="tips-title">
      <div class="container split split--top">
        <div class="prose reveal">
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
     + cta('Questions about your test?', 'Call or WhatsApp Steven, he answers in English or Spanish.', 'Contact us', 'contact.html'))

# About ------------------------------------------------------------------
page('about.html', 'About us | Drive Go London',
     'Meet Drive Go London: founded by Steven Bernal, former DriveTest examiner, and the first Hispanic driving school approved by the MTO in London, Ontario.',
     hero('About us', 'About Drive Go London', 'Who we are and how we teach.', 'About us')
     + '''    <section class="section" aria-labelledby="story-title">
      <div class="container split">
        <figure class="split__media reveal">
          <img src="assets/img/gallery/20.webp" alt="The blue Drive Go London training car with a Student Driver sign in downtown London" width="900" height="1350" loading="lazy" class="split__img--tall">
        </figure>
        <div class="prose reveal">
          <h2 class="section__title" id="story-title">A driving school built around you</h2>
          <p>Steven Bernal started Drive Go London after years as a DriveTest examiner. He watched too many nervous students fail for habits nobody had taught them, and decided to teach those habits from the very first lesson.</p>
          <p>With more than five years teaching in London, Steven leads every program and works alongside two more MTO certified instructors. Together they have helped more than 2,000 students get their licence in London and surrounding areas, in English and in Spanish.</p>
          <p>We are proud to be the first Hispanic driving school approved by the MTO in London as a Beginner Driver Education course provider.</p>
          <div class="hero__actions">
            <a class="btn btn--outline btn--lg" href="packages.html">View packages</a>
          </div>
        </div>
      </div>
    </section>

    <section class="section section--soft" aria-labelledby="why-title">
      <div class="container">
        <header class="section__head reveal">
          <h2 class="section__title" id="why-title">Why students choose us</h2>
        </header>
        <div class="cards">
          <article class="card reveal"><div class="card__icon"><i class="ph ph-identification-badge" aria-hidden="true"></i></div><h3>Certified instructors</h3><p>MTO certified instructors led by a former DriveTest examiner who knows exactly what examiners look for.</p></article>
          <article class="card reveal"><div class="card__icon"><i class="ph ph-seal-check" aria-hidden="true"></i></div><h3>MTO approved courses</h3><p>Our Beginner Driver Education course can get you to your G2 test four months sooner and lower your insurance.</p></article>
          <article class="card reveal"><div class="card__icon"><i class="ph ph-hand-heart" aria-hidden="true"></i></div><h3>Personal attention</h3><p>One on one lessons, home pick up and calm teaching for nervous first timers, in English or Spanish.</p></article>
        </div>
      </div>
    </section>

    <section class="section" aria-labelledby="mission-title">
      <div class="container mv">
        <article class="mv__item reveal">
          <div class="card__icon"><i class="ph ph-target" aria-hidden="true"></i></div>
          <h2 class="section__title" id="mission-title">Safe drivers for life</h2>
          <p>Help every student in London pass their test with confidence and keep the safe habits that protect them on the road for years.</p>
        </article>
        <article class="mv__item reveal">
          <div class="card__icon"><i class="ph ph-binoculars" aria-hidden="true"></i></div>
          <h2 class="section__title">The school families recommend</h2>
          <p>Be the driving school London families, and especially the Hispanic community, trust and recommend first.</p>
        </article>
      </div>
    </section>

    <section class="section section--soft" aria-labelledby="team-title">
      <div class="container">
        <header class="section__head reveal">
          <h2 class="section__title" id="team-title">Meet our instructors</h2>
        </header>
        <div class="team">
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
          <div class="team__copy reveal">
            <h3 class="team__title">Steven and two more certified instructors</h3>
            <p>Every Drive Go London lesson follows Steven's program. He trains our two MTO certified instructors in the same test focused method, so you get the same standard no matter who is in the passenger seat.</p>
            <ul class="checklist">
              <li><i class="ph-fill ph-check-circle" aria-hidden="true"></i><span>MTO certified and background checked</span></li>
              <li><i class="ph-fill ph-check-circle" aria-hidden="true"></i><span>Lessons in English and Spanish</span></li>
              <li><i class="ph-fill ph-check-circle" aria-hidden="true"></i><span>Trained to teach nervous first time drivers</span></li>
            </ul>
          </div>
        </div>
      </div>
    </section>

    <section class="figures" aria-label="Drive Go London in numbers">
      <dl class="container figures__grid">
        <div><dt>Years of experience</dt><dd data-count="5" data-suffix="+">5+</dd></div>
        <div><dt>Students trained</dt><dd data-count="2000" data-suffix="+">2,000+</dd></div>
        <div><dt>Certified instructors</dt><dd data-count="3">3</dd></div>
        <div><dt>Average rating</dt><dd data-count="5" data-decimals="1">5.0</dd></div>
      </dl>
    </section>

'''
     + cta('Ready to start driving?', 'Pick the package that fits you.'))

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
        <a class="contact-card" href="mailto:info@drivegodrivingschool.com"><span class="contact__icon"><i class="ph ph-envelope-simple" aria-hidden="true"></i></span><strong>Email</strong><span>info@drivegodrivingschool.com</span></a>
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


# Legal pages and 404 -------------------------------------------------------
def simple_page(path, title, desc, partial, robots=False):
    body = open(f'{REPO}/tools/partials/{partial}').read()
    h = head(title, desc, path)
    if robots:
        h = h.replace('</head>', '  <meta name="robots" content="noindex">\n</head>')
    html = h + shell(path) + body + '\n' + FOOTER + '\n\n  <script src="assets/js/main.js" defer></script>\n</body>\n</html>\n'
    open(f'{REPO}/{path}', 'w').write(html)
    print('wrote', path)


simple_page('privacy.html', 'Privacy policy | Drive Go London', 'How Drive Go London collects, uses and protects your personal information.', 'privacy-main.html')
simple_page('terms.html', 'Terms and conditions | Drive Go London', 'Booking, payment, cancellation and refund terms for Drive Go London driving lessons and packages.', 'terms-main.html')
simple_page('404.html', 'Page not found | Drive Go London', 'This page does not exist.', '404-main.html', robots=True)
