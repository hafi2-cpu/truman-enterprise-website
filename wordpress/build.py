#!/usr/bin/env python3
"""
Build the "Truman Enterprise" WordPress theme from the static site.

  python3 wordpress/build.py      (run from the repo root)

Produces wordpress/dist/truman-enterprise.zip — upload it in
Appearance → Themes → Add New → Upload Theme, activate, then press
"Set up the site" (Appearance → Truman Setup).

The theme provides the header, footer, menus and all styling. The page bodies
are stored in inc/site-data.json as Elementor (free) data; the setup screen
creates the pages, imports the photos and installs Elementor.
"""
import json, os, re, random, shutil, zipfile
from bs4 import BeautifulSoup

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
HERE = os.path.join(ROOT, 'wordpress')
SRC = os.path.join(HERE, 'theme-src')
DIST = os.path.join(HERE, 'dist')
THEME = os.path.join(DIST, 'truman-enterprise')

# original image -> optimised file bundled with the theme
IMAGES = {
    'kingfisher.jpg':       ('truman-boat-kingfisher.jpg',    'Kingfisher on the Staffs & Worcs Canal'),
    'dsc-0579.jpg':         ('truman-canal-boat.jpg',          'Narrowboat Enterprise cruising the canal'),
    'volunteers-canal.jpg': ('truman-volunteers-canal.jpg',    'Volunteers and passengers on the canal'),
    'passengers-lunch.jpg': ('truman-passengers-lunch.jpg',    'Passengers enjoying lunch on board'),
    'autherley-turn.jpg':   ('truman-autherley-junction.jpg',  'Autherley Junction on the Staffs & Worcs Canal'),
    'volunteers-boat.jpg':  ('truman-volunteers-boat.jpg',     'Volunteers on the narrowboat'),
    'anchor-pub.jpg':       ('truman-anchor-pub-coven.jpg',    'The Anchor pub at Coven'),
    'skipper-bridge.jpg':        ('truman-skipper-bridge.jpg',        'Volunteer skipper steering Enterprise under a canal bridge'),
    'bow-canal.jpg':             ('truman-bow-canal.jpg',             'The tree-lined canal seen from the bow of Enterprise'),
    'front-deck.jpg':            ('truman-front-deck.jpg',            'Passengers relaxing on the front deck under the trees'),
    'enterprise-anchor-inn.jpg': ('truman-enterprise-anchor-inn.jpg', 'Enterprise moored at the Anchor Inn, Coven'),
    'anchor-inn-approach.jpg':   ('truman-anchor-inn-approach.jpg',   'Enterprise approaching the Anchor Inn'),
    'enterprise-moored.jpg':     ('truman-enterprise-moored.jpg',     'Narrowboat Enterprise moored at Hatherton Marina'),
    'lock-paddle.jpg':           ('truman-lock-paddle.jpg',           'Young passenger winding a lock paddle'),
    'umbrella-accessible.jpg':   ('truman-umbrella-accessible.jpg',   'Passengers on the front deck of Enterprise, one in a wheelchair'),
    'towpath-bridge.jpg':        ('truman-towpath-bridge.jpg',        'The Staffs & Worcs Canal towpath'),
    'hatherton-marina.jpg':      ('truman-hatherton-marina.jpg',      'Hatherton Marina, Calf Heath'),
}

# static file -> (key, slug, title)
PAGES = [
    ('index.html',        'home',         'home',         'Home'),
    ('our-trips.html',    'our-trips',    'our-trips',    'Our Trips'),
    ('get-involved.html', 'get-involved', 'get-involved', 'Get Involved'),
    ('support.html',      'support-us',   'support-us',   'Support Us'),
    ('contact.html',      'contact',      'contact',      'Contact'),
    ('booking-form.html', 'book-a-trip',  'book-a-trip',  'Book a Trip'),
]
MENUS = {
    'primary': [('home', 'Home'), ('our-trips', 'Our Trips'), ('get-involved', 'Get Involved'),
                ('support-us', 'Support Us'), ('contact', 'Contact'), ('book-a-trip', 'Book a Trip', 'nav-cta')],
    'footer':  [('home', 'Home'), ('our-trips', 'Our Trips'), ('book-a-trip', 'Book a Trip'),
                ('get-involved', 'Get Involved'), ('support-us', 'Support Us'), ('contact', 'Contact')],
}
SLUG_FOR = {p[0]: p[2] for p in PAGES}

random.seed(1968)
def eid():
    return '%07x' % random.getrandbits(28)


# ---------------------------------------------------------------- content
def placeholders(s):
    """Links/images become placeholders the setup screen fills with real URLs."""
    def href(m):
        slug, frag = SLUG_FOR[m.group(1)], m.group(2) or ''
        return 'href="%%HOME%%' + ('' if slug == 'home' else slug + '/') + frag + '"'
    s = re.sub(r'href="([\w-]+\.html)(#[\w-]+)?"', href, s)
    for orig, (new, _) in IMAGES.items():
        s = s.replace('images/' + orig, '%%IMG:' + new + '%%')
    assert 'images/' not in s and '.html"' not in s, 'unconverted link/image'
    return s

def minify(s):
    s = re.sub(r'>\s+<', '><', s)
    return re.sub(r'\s*\n\s*', ' ', s).strip()

def block_title(el):
    cls = el.get('class') or []
    for c, t in (('hero', 'Hero slider'), ('page-hero', 'Page banner'), ('stats-bar', 'Stats bar'), ('cta-banner', 'Call to action')):
        if c in cls:
            return t
    h = el.find('h2')
    return re.sub(r'\s+', ' ', h.get_text(' ', strip=True))[:50] if h else 'Section'

def container(title, widget):
    return {
        'id': eid(), 'elType': 'container', 'isInner': False,
        'settings': {
            '_title': title, 'content_width': 'full', 'css_classes': 'te-block e-no-lazyload',
            'padding': {'unit': 'px', 'top': '0', 'right': '0', 'bottom': '0', 'left': '0', 'isLinked': True},
            'flex_gap': {'unit': 'px', 'size': 0, 'column': '0', 'row': '0', 'isLinked': True},
        },
        'elements': [widget],
    }

def widget(kind, title, html):
    key = 'editor' if kind == 'text-editor' else 'html'
    return {'id': eid(), 'elType': 'widget', 'widgetType': kind, 'isInner': False,
            'settings': {'_title': title, key: html}, 'elements': []}

def build_page(fname):
    soup = BeautifulSoup(open(os.path.join(ROOT, fname)).read(), 'html.parser')
    blocks, html_parts = [], []
    for el in soup.body.find_all(recursive=False):
        if el.name in ('nav', 'footer', 'script'):
            continue                       # provided by the theme
        title = block_title(el)
        html = minify(placeholders(str(el)))
        cls = el.get('class') or []
        # Scripted pieces (slider, counters, booking form) stay as HTML widgets;
        # everything else is a Text Editor widget so it can be edited visually.
        kind = 'html' if ('hero' in cls or 'stats-bar' in cls or el.find('form')) else 'text-editor'
        blocks.append(container(title, widget(kind, title, html)))
        html_parts.append(html)
    return blocks, '\n'.join(html_parts)


# ---------------------------------------------------------------- CSS
def split_rules(css):
    i, n, out = 0, len(css), []
    while i < n:
        j = css.find('{', i)
        if j < 0:
            break
        prelude, depth, k = css[i:j].strip(), 1, j + 1
        while depth and k < n:
            depth += {'{': 1, '}': -1}.get(css[k], 0)
            k += 1
        out.append((prelude, css[j + 1:k - 1]))
        i = k
    return out

SCOPE = '.te-site'
def prefix_selector(sel):
    parts = []
    for p in (x.strip() for x in sel.split(',')):
        if not p:
            continue
        if p in (':root', 'html', 'body'):
            parts.append(p)
        elif p.startswith('*'):
            parts.append(':where(%s) %s' % (SCOPE, p))   # keep the reset at zero specificity
        else:
            parts.append('%s %s' % (SCOPE, p))
    return ', '.join(parts)

def scope_css(css):
    out = []
    for prelude, body in split_rules(css):
        if prelude.startswith('@media'):
            out.append('%s {\n%s\n}' % (prelude, scope_css(body)))
        elif prelude.startswith('@'):
            out.append('%s {%s}' % (prelude, body))
        else:
            out.append('%s { %s }' % (prefix_selector(prelude), ' '.join(body.split())))
    return '\n'.join(out)

FIXES = """
:root { --shadow: var(--shadow-md); }
.section-label { display:block; font-family:'Inter',sans-serif; font-size:0.7rem; font-weight:700; letter-spacing:3px; text-transform:uppercase; color:var(--teal); margin-bottom:12px; }
.trip-detail { display:flex; align-items:center; gap:8px; font-size:0.88rem; color:var(--text-mid); margin-bottom:6px; }
.trip-detail-icon { width:26px; height:26px; background:var(--teal-pale); border-radius:6px; display:inline-flex; align-items:center; justify-content:center; font-size:0.8rem; flex-shrink:0; }
.support-hero-box { background:linear-gradient(135deg, var(--navy) 0%, #1a3a5c 60%, #0d3348 100%); border-radius:var(--radius-lg); padding:56px 48px; color:white; text-align:center; }
.support-hero-box h2 { font-size:2.2rem; color:white; margin-bottom:16px; }
.support-hero-box p { color:rgba(255,255,255,0.82); max-width:720px; margin:0 auto 14px; line-height:1.8; }
.support-hero-box > div { max-width:560px; margin-left:auto; margin-right:auto; }
.progress-bar-wrap { height:12px; background:rgba(255,255,255,0.12); border-radius:50px; overflow:hidden; }
.progress-label { font-size:0.82rem; color:rgba(255,255,255,0.6) !important; margin-top:8px !important; }
.title-line { font-size:0; line-height:0; }
@media (max-width: 900px) { .support-hero-box { padding:36px 24px; } }
.footer-menu { list-style:none; }
.te-simple-hero { min-height:260px; background:linear-gradient(135deg, var(--navy) 0%, #2c5f8a 100%); }
.te-prose { max-width:820px; }
.te-prose p, .te-prose ul, .te-prose ol { margin-bottom:1em; color:var(--text-mid); }
.te-prose ul, .te-prose ol { padding-left:1.4em; }
.te-prose h2, .te-prose h3 { color:var(--navy); margin:1.4em 0 .5em; }
.te-prose article + article { margin-top:48px; }
"""
DEFAULTS = """
/* ===== browser defaults the static site relied on ===== */
.te-site h1, .te-site h2, .te-site h3, .te-site h4, .te-site h5, .te-site h6 { font-weight:700; }
.te-site h1 { font-size:2em; } .te-site h2 { font-size:1.5em; } .te-site h3 { font-size:1.17em; } .te-site h4 { font-size:1em; }
.te-site a { color:var(--teal); }
.te-site button { font-family:inherit; }
.te-site img { height:auto; max-width:100%; }
.te-site img.emoji, .te-site img.wp-smiley { display:inline !important; width:1em !important; height:1em !important; max-width:none !important; margin:0 .07em !important; vertical-align:-0.1em !important; border:none !important; box-shadow:none !important; background:none !important; padding:0 !important; }
.te-site .page-hero img, .te-site .img-frame img { max-width:none; }

/* ===== logo ===== */
.te-site .nav-inner { height:84px; }
.te-site .nav-logo-img { display:block; height:64px; width:auto; max-width:none; filter:drop-shadow(0 2px 6px rgba(0,0,0,0.25)); }
@media (max-width: 900px) { .te-site .nav-inner { height:auto; } .te-site .nav-logo-img { height:52px; } }

/* ===== footer (light) ===== */
.te-site footer.site-footer { background:#f7f2e6; color:#2c3a4d; border-top:4px solid var(--gold); }
.te-site .site-footer .footer-inner { max-width:1200px; margin:0 auto; padding:0 40px; }
.te-site .site-footer .footer-grid { display:grid; grid-template-columns:1.4fr 0.8fr 1.7fr 1.1fr; gap:44px; padding:64px 0 56px; border-bottom:0; }
.te-site .site-footer .footer-logo { display:block; height:100px; width:auto; margin-bottom:22px; filter:drop-shadow(0 4px 10px rgba(13,33,55,0.15)); }
.te-site .site-footer .footer-brand p { font-size:0.95rem; line-height:1.75; color:#2c3a4d; margin-bottom:20px; max-width:360px; }
.te-site .site-footer .footer-badges { display:flex; flex-wrap:wrap; gap:8px; }
.te-site .site-footer .footer-badge { display:inline-flex; align-items:center; gap:6px; background:white; border:1px solid #e3dccb; color:var(--navy); font-size:0.78rem; font-weight:600; padding:6px 12px; border-radius:50px; }
.te-site .site-footer .footer-col h4 { color:var(--navy); font-size:0.78rem; font-weight:700; letter-spacing:2.5px; text-transform:uppercase; margin-bottom:22px; padding-bottom:10px; position:relative; }
.te-site .site-footer .footer-col h4::after { content:''; position:absolute; left:0; bottom:0; width:32px; height:3px; border-radius:2px; background:var(--teal); }
.te-site .site-footer .footer-col ul { list-style:none; }
.te-site .site-footer .footer-menu li { margin-bottom:12px; }
.te-site .site-footer .footer-col a, .te-site .site-footer .footer-col ul li a { display:inline; margin:0; color:#2c3a4d; font-size:0.95rem; font-weight:500; text-decoration:none; transition:color .2s; }
.te-site .site-footer .footer-col a:hover, .te-site .site-footer .footer-col ul li a:hover { color:var(--teal); text-decoration:underline; text-underline-offset:3px; }
.te-site .site-footer .footer-contact li { display:flex; gap:12px; align-items:flex-start; margin-bottom:16px; font-size:0.95rem; line-height:1.6; color:#2c3a4d; }
.te-site .site-footer .footer-contact a { overflow-wrap:break-word; }
.te-site .site-footer .footer-icon { flex-shrink:0; width:32px; height:32px; border-radius:8px; background:white; border:1px solid #e3dccb; display:inline-flex; align-items:center; justify-content:center; font-size:0.85rem; margin-top:-3px; }
.te-site .site-footer .footer-col p { font-size:0.95rem; line-height:1.7; color:#2c3a4d; margin-bottom:20px; }
.te-site .site-footer .footer-col p strong { color:var(--navy); }
.te-site .site-footer .footer-col a.footer-book { display:inline-flex; color:white; font-size:0.78rem; font-weight:600; text-decoration:none; }
.te-site .site-footer .footer-col a.footer-book:hover { color:white; text-decoration:none; }
.te-site .site-footer .footer-bottom-bar { background:var(--navy); }
.te-site .site-footer .footer-bottom { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; padding:18px 40px; }
.te-site .site-footer .footer-bottom p { font-size:0.82rem; color:rgba(255,255,255,0.82); margin:0; }
@media (max-width: 1024px) {
  .te-site .site-footer .footer-grid { grid-template-columns:1fr 1fr; gap:40px; padding:48px 0 40px; }
}
@media (max-width: 640px) {
  .te-site .site-footer .footer-inner { padding:0 20px; }
  .te-site .site-footer .footer-grid { grid-template-columns:1fr; gap:32px; padding:40px 0 32px; }
  .te-site .site-footer .footer-logo { height:84px; }
  .te-site .site-footer .footer-menu { display:grid; grid-template-columns:1fr 1fr; column-gap:16px; }
  .te-site .site-footer .footer-bottom { flex-direction:column; text-align:center; padding:16px 20px; }
}

/* ===== keep content inside its boxes on small screens ===== */
.te-site .booking-layout > *, .te-site .contact-grid > *, .te-site .trips-page-layout > *, .te-site .two-col > *, .te-site .form-row > * { min-width:0; }
.te-site .form-group input, .te-site .form-group select, .te-site .form-group textarea { min-width:0; max-width:100%; }
@media (max-width: 640px) {
  .te-site .btn { white-space:normal; text-align:center; justify-content:center; overflow-wrap:anywhere; max-width:100%; }
  .te-site .booking-layout .form-card { padding:24px 18px; }
  .te-site .trip-row .form-row { grid-template-columns:1fr !important; gap:12px; }
}

/* ===== Elementor ===== */
.e-con.te-block { --padding-top:0px; --padding-right:0px; --padding-bottom:0px; --padding-left:0px; --gap:0px 0px; --row-gap:0px; --column-gap:0px; padding:0; gap:0; }
body.te-site .elementor-widget-text-editor, body.te-site .elementor-widget-html { color:inherit; font-family:inherit; font-size:inherit; font-weight:inherit; line-height:inherit; }
@media (min-width: 901px) { .admin-bar.te-site nav { top:32px; } }
"""

def build_css():
    base = re.sub(r'/\*.*?\*/', '', open(os.path.join(ROOT, 'css/style.css')).read(), flags=re.S)
    font_import = re.search(r'@import url\([^)]*\);', base).group(0)
    base = base.replace(font_import, '')
    page_css = ''
    for f, *_ in PAGES:
        page_css += '\n'.join(re.findall(r'<style>(.*?)</style>', open(os.path.join(ROOT, f)).read(), re.S))
    page_css = re.sub(r'/\*.*?\*/', '', page_css, flags=re.S)
    header = """/*
Theme Name: Truman Enterprise
Theme URI: https://truman-enterprise.org.uk
Author: Truman Enterprise Narrowboat Trust
Description: Website theme for the Truman Enterprise Narrowboat Trust — accessible canal boat trips in the West Midlands since 1968. After activating, go to Appearance → Truman Setup and press "Set up the site" to install Elementor and create the pages, menus and photos.
Version: 1.4.0
Requires at least: 6.0
Requires PHP: 7.4
License: GNU General Public License v2 or later
License URI: https://www.gnu.org/licenses/gl-2.0.html
Text Domain: truman
Tags: one-column, custom-menu, featured-images
*/
"""
    return header + font_import + '\n' + scope_css(base + '\n' + page_css + '\n' + FIXES) + '\n' + DEFAULTS


# ---------------------------------------------------------------- JS
def build_js():
    js = open(os.path.join(ROOT, 'js/main.js')).read()
    js = re.sub(r"  // Set active nav link\n.*?\}\);\n", '', js, flags=re.S)   # the menu marks the current page
    js = js.replace("document.addEventListener('DOMContentLoaded', function () {",
                    "(function (init) { if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init(); })(function () {", 1)
    assert 'readyState' in js and 'Set active nav link' not in js
    return js


# ---------------------------------------------------------------- package
def main():
    shutil.rmtree(DIST, ignore_errors=True)
    shutil.copytree(SRC, THEME)
    os.makedirs(os.path.join(THEME, 'assets/js'))
    shutil.copytree(os.path.join(HERE, 'images'), os.path.join(THEME, 'assets/images'))

    open(os.path.join(THEME, 'style.css'), 'w').write(build_css())
    open(os.path.join(THEME, 'assets/js/main.js'), 'w').write(build_js())

    pages = []
    for order, (fname, key, slug, title) in enumerate(PAGES):
        blocks, html = build_page(fname)
        pages.append({'key': key, 'slug': slug, 'title': title, 'order': order,
                      'elementor': json.dumps(blocks, ensure_ascii=False, separators=(',', ':')), 'html': html})
    data = {
        'images': {new: alt for new, alt in IMAGES.values()},
        'pages': pages,
        'menus': {loc: [dict(key=i[0], label=i[1], **({'class': i[2]} if len(i) > 2 else {})) for i in items]
                  for loc, items in MENUS.items()},
    }
    json.dump(data, open(os.path.join(THEME, 'inc/site-data.json'), 'w'), ensure_ascii=False, indent=1)

    shot = os.path.join(HERE, 'screenshot.png')
    if os.path.exists(shot):
        shutil.copy(shot, os.path.join(THEME, 'screenshot.png'))

    zpath = os.path.join(DIST, 'truman-enterprise.zip')
    with zipfile.ZipFile(zpath, 'w', zipfile.ZIP_DEFLATED) as z:
        for d, _, files in os.walk(THEME):
            for f in sorted(files):
                full = os.path.join(d, f)
                z.write(full, os.path.relpath(full, DIST))
    print('built', zpath, os.path.getsize(zpath) // 1024, 'KB')

if __name__ == '__main__':
    main()
