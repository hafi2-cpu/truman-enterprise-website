#!/usr/bin/env python3
"""
Build a WordPress (WXR) import file that recreates the Truman Enterprise static
site as Elementor (free) pages for the Hello Elementor theme.

Output (in this folder):
  truman-enterprise-wordpress.xml  -> Tools > Import > WordPress
  truman-enterprise-styles.css     -> same CSS the import puts in Customizer > Additional CSS (fallback)

Run from the repo root:  python3 wordpress/build.py
"""
import json, os, re, random, html
from datetime import datetime
from bs4 import BeautifulSoup

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, 'wordpress')

GITHUB_RAW = 'https://raw.githubusercontent.com/hafi2-cpu/truman-enterprise-website/wordpress/wordpress/images/'
UPLOAD_DATE = '2026-10-18 09:00:00'          # attachments land in /wp-content/uploads/2026/10/
UPLOAD_PATH = '/wp-content/uploads/2026/10/'

# original image -> optimised media-library file
IMAGES = {
    'kingfisher.jpg':       ('truman-boat-kingfisher.jpg',    'Narrowboat Enterprise on the Staffs & Worcs Canal'),
    'dsc-0579.jpg':         ('truman-canal-boat.jpg',          'Narrowboat Enterprise cruising the canal'),
    'volunteers-canal.jpg': ('truman-volunteers-canal.jpg',    'Volunteers and passengers on the canal'),
    'passengers-lunch.jpg': ('truman-passengers-lunch.jpg',    'Passengers enjoying lunch on board'),
    'autherley-turn.jpg':   ('truman-autherley-junction.jpg',  'Autherley Junction on the Staffs & Worcs Canal'),
    'volunteers-boat.jpg':  ('truman-volunteers-boat.jpg',     'Volunteers on the narrowboat'),
    'anchor-pub.jpg':       ('truman-anchor-pub-coven.jpg',    'The Anchor pub at Coven'),
}

# page file -> (post id, slug, title, menu label)
PAGES = [
    ('index.html',        7101, 'home',         'Home',         'Home'),
    ('our-trips.html',    7102, 'our-trips',    'Our Trips',    'Our Trips'),
    ('get-involved.html', 7103, 'get-involved', 'Get Involved', 'Get Involved'),
    ('support.html',      7104, 'support-us',   'Support Us',   'Support Us'),
    ('contact.html',      7105, 'contact',      'Contact',      'Contact'),
    ('booking-form.html', 7106, 'book-a-trip',  'Book a Trip',  'Book a Trip'),
]
SLUG_FOR = {p[0]: ('/' if p[2] == 'home' else '/%s/' % p[2]) for p in PAGES}

random.seed(1968)
def eid():
    return '%07x' % random.getrandbits(28)


# ---------------------------------------------------------------- HTML helpers
def rewrite_links(s):
    def href(m):
        target, frag = m.group(2), m.group(3) or ''
        return '%s"%s%s"' % (m.group(1), SLUG_FOR[target], frag)
    s = re.sub(r'(href=)"([\w-]+\.html)(#[\w-]+)?"', href, s)
    for orig, (new, _) in IMAGES.items():
        s = s.replace('images/' + orig, UPLOAD_PATH + new)
    return s

def minify(s):
    # collapse whitespace between tags; keep text spacing. One line avoids any
    # paragraph/line-break filtering WordPress might apply to the content.
    s = re.sub(r'>\s+<', '><', s)
    s = re.sub(r'\s*\n\s*', ' ', s)
    return s.strip()


# ---------------------------------------------------------------- CSS
def split_rules(css):
    """Yield (prelude, body) for top-level blocks; handles nested @media."""
    i, n, out = 0, len(css), []
    while i < n:
        j = css.find('{', i)
        if j < 0:
            break
        prelude = css[i:j].strip()
        depth, k = 1, j + 1
        while depth and k < n:
            if css[k] == '{': depth += 1
            elif css[k] == '}': depth -= 1
            k += 1
        out.append((prelude, css[j + 1:k - 1]))
        i = k
    return out

def prefix_selector(sel, scope='.te-block'):
    parts = []
    for p in sel.split(','):
        p = p.strip()
        if not p:
            continue
        if p in (':root', 'html'):
            parts.append(p)
        elif p == 'body':
            parts.append('body')
            parts.append(scope)            # content inherits the same base styles
        elif p.startswith('*'):
            parts.append('%s %s' % (scope, p))
        else:
            parts.append('%s %s' % (scope, p))
    return ', '.join(parts)

def scope_css(css):
    out = []
    for prelude, body in split_rules(css):
        if prelude.startswith('@media'):
            out.append('%s {\n%s\n}' % (prelude, scope_css(body)))
        elif prelude.startswith('@'):
            out.append('%s {%s}' % (prelude, body))
        else:
            out.append('%s {%s}' % (prefix_selector(prelude), body.strip()))
    return '\n'.join(out)

def build_css():
    base = open(os.path.join(ROOT, 'css/style.css')).read()
    base = re.sub(r'/\*.*?\*/', '', base, flags=re.S)
    m = re.search(r'@import url\([^)]*\);', base)
    font_import = m.group(0)
    base = base.replace(font_import, '')
    page_css = ''
    for f, *_ in PAGES:
        for block in re.findall(r'<style>(.*?)</style>', open(os.path.join(ROOT, f)).read(), re.S):
            page_css += '\n' + block
    page_css = re.sub(r'/\*.*?\*/', '', page_css, flags=re.S)

    fixes = """
:root { --shadow: var(--shadow-md); }
/* styles that were referenced but missing on the static site */
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
"""
    # Every site rule is scoped under .te-block, which lifts it above the Hello
    # theme's bare-element defaults. These cover the defaults the static site
    # never set because it relied on browser defaults.
    theme_defaults = """
/* ===== override WordPress theme defaults the static site relied on ===== */
.te-block h1, .te-block h2, .te-block h3, .te-block h4, .te-block h5, .te-block h6 { font-weight:700; }
.te-block h1 { font-size:2em; } .te-block h2 { font-size:1.5em; } .te-block h3 { font-size:1.17em; } .te-block h4 { font-size:1em; }
.te-block a { color:var(--teal); background:transparent; }
.te-block label { display:inline; line-height:inherit; vertical-align:baseline; }
.te-block .checkbox-group label { line-height:1.55; }
.te-block button { font-family:inherit; line-height:normal; white-space:normal; }
.te-block table td, .te-block table th { border:0; line-height:inherit; vertical-align:middle; }
.te-block table tbody > tr > td, .te-block table tbody tr:hover > td { background-color:transparent; }
.te-block img { height:auto; }
"""
    bridge = """
/* ===== Elementor page structure ===== */
.e-con.te-block { --padding-top:0px; --padding-right:0px; --padding-bottom:0px; --padding-left:0px; --gap:0px 0px; --row-gap:0px; --column-gap:0px; padding:0; gap:0; }
.e-con.te-header { position:sticky; top:0; z-index:1000; }
.admin-bar .e-con.te-header { top:32px; }
@media (max-width: 900px) { .e-con.te-header { position:relative; top:0; } }
body .te-block .elementor-widget-text-editor, body .te-block .elementor-widget-html { color:inherit; font-family:inherit; font-size:inherit; font-weight:inherit; line-height:inherit; }
body.elementor-template-canvas { background: var(--off-white); }
"""
    scoped = scope_css(base + '\n' + page_css + '\n' + fixes)
    # :root vars from fixes stay unscoped thanks to prefix_selector
    header = ('/* Truman Enterprise Narrowboat Trust — site styles\n'
              '   Generated from css/style.css by wordpress/build.py. Applies to the Elementor pages\n'
              '   (every page section has the class "te-block"). */\n')
    return header + font_import + '\n' + scoped + '\n' + theme_defaults + bridge + '\n'


# ---------------------------------------------------------------- page pieces
NAV_LOGO = None
FOOTER = None

def nav_html(active_file):
    # (BeautifulSoup writes attributes alphabetically: class before href)
    s = NAV_LOGO.replace('<a class="active" href=', '<a href=')
    if active_file == 'booking-form.html':
        s = s.replace('<a class="nav-cta" href="booking-form.html"', '<a class="nav-cta active" href="booking-form.html"')
    else:
        s = s.replace('<li><a href="%s">' % active_file, '<li><a class="active" href="%s">' % active_file, 1)
    assert s.count('active') == 1, active_file
    return minify(rewrite_links(s))

JS = None
def footer_html():
    js = JS
    return minify(rewrite_links(FOOTER)) + '\n<script>\n' + js + '\n</script>'

def load_js():
    js = open(os.path.join(ROOT, 'js/main.js')).read()
    # WordPress URLs end in "/" so the filename-based active-link logic no longer
    # applies (the active link is set in each page's header instead).
    js = re.sub(r"  // Set active nav link\n.*?\}\);\n", '', js, flags=re.S)
    # run immediately if the page has already loaded (e.g. inside the Elementor editor)
    js = js.replace("document.addEventListener('DOMContentLoaded', function () {",
                    "(function (init) { if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init(); })(function () {", 1)
    assert "readyState" in js and "Set active nav link" not in js
    return js


def container(title, classes, widget):
    return {
        'id': eid(), 'elType': 'container', 'isInner': False,
        'settings': {
            '_title': title,
            'content_width': 'full',
            'css_classes': 'te-block ' + classes,
            'padding': {'unit': 'px', 'top': '0', 'right': '0', 'bottom': '0', 'left': '0', 'isLinked': True},
            'flex_gap': {'unit': 'px', 'size': 0, 'column': '0', 'row': '0', 'isLinked': True},
        },
        'elements': [widget],
    }

def w_text(title, content):
    return {'id': eid(), 'elType': 'widget', 'widgetType': 'text-editor', 'isInner': False,
            'settings': {'_title': title, 'editor': content}, 'elements': []}

def w_html(title, content):
    return {'id': eid(), 'elType': 'widget', 'widgetType': 'html', 'isInner': False,
            'settings': {'_title': title, 'html': content}, 'elements': []}


def block_title(el, page):
    cls = el.get('class') or []
    if el.name == 'nav': return 'Header'
    if el.name == 'footer': return 'Footer'
    if 'hero' in cls: return 'Hero slider'
    if 'page-hero' in cls: return 'Page banner'
    if 'stats-bar' in cls: return 'Stats bar'
    if 'cta-banner' in cls: return 'Call to action'
    t = el.find(['h2'])
    if t:
        return re.sub(r'\s+', ' ', t.get_text(' ', strip=True))[:50]
    return 'Section'

def build_page(fname):
    soup = BeautifulSoup(open(os.path.join(ROOT, fname)).read(), 'html.parser')
    blocks = []
    for el in soup.body.find_all(recursive=False):
        if el.name == 'script':
            continue
        title = block_title(el, fname)
        cls = el.get('class') or []
        if el.name == 'nav':
            blocks.append(container('Header', 'te-header', w_html('Header — logo & menu', nav_html(fname))))
        elif el.name == 'footer':
            blocks.append(container('Footer', 'te-footer', w_html('Footer + site scripts', footer_html())))
        elif 'hero' in cls or 'stats-bar' in cls or el.find('form'):
            # interactive pieces (slider, counters, booking form) stay as HTML
            blocks.append(container(title, 'te-section', w_html(title, minify(rewrite_links(str(el))))))
        else:
            blocks.append(container(title, 'te-section', w_text(title, minify(rewrite_links(str(el))))))
    return blocks


# ---------------------------------------------------------------- WXR
def cdata(s):
    return '<![CDATA[' + s.replace(']]>', ']]]]><![CDATA[>') + ']]>'

def meta(k, v):
    return '\t\t<wp:postmeta>\n\t\t\t<wp:meta_key>%s</wp:meta_key>\n\t\t\t<wp:meta_value>%s</wp:meta_value>\n\t\t</wp:postmeta>\n' % (cdata(k), cdata(v))

def item(post_id, title, slug, ptype, content='', status='publish', extra='', metas=(), date=UPLOAD_DATE, menu_order=0):
    return (
        '\t<item>\n'
        '\t\t<title>%s</title>\n'
        '\t\t<link>/%s/</link>\n'
        '\t\t<pubDate>%s</pubDate>\n'
        '\t\t<dc:creator>%s</dc:creator>\n'
        '\t\t<guid isPermaLink="false">truman-%s-%d</guid>\n'
        '\t\t<description></description>\n'
        '\t\t<content:encoded>%s</content:encoded>\n'
        '\t\t<excerpt:encoded><![CDATA[]]></excerpt:encoded>\n'
        '\t\t<wp:post_id>%d</wp:post_id>\n'
        '\t\t<wp:post_date>%s</wp:post_date>\n'
        '\t\t<wp:post_date_gmt>%s</wp:post_date_gmt>\n'
        '\t\t<wp:post_modified>%s</wp:post_modified>\n'
        '\t\t<wp:post_modified_gmt>%s</wp:post_modified_gmt>\n'
        '\t\t<wp:comment_status>closed</wp:comment_status>\n'
        '\t\t<wp:ping_status>closed</wp:ping_status>\n'
        '\t\t<wp:post_name>%s</wp:post_name>\n'
        '\t\t<wp:status>%s</wp:status>\n'
        '\t\t<wp:post_parent>0</wp:post_parent>\n'
        '\t\t<wp:menu_order>%d</wp:menu_order>\n'
        '\t\t<wp:post_type>%s</wp:post_type>\n'
        '\t\t<wp:post_password></wp:post_password>\n'
        '\t\t<wp:is_sticky>0</wp:is_sticky>\n'
        '%s%s'
        '\t</item>\n'
    ) % (html.escape(title), slug, datetime.strptime(date, '%Y-%m-%d %H:%M:%S').strftime('%a, %d %b %Y %H:%M:%S +0000'),
         cdata('admin'), ptype, post_id, cdata(content), post_id, date, date, date, date,
         cdata(slug), status, menu_order, ptype, extra, ''.join(meta(k, v) for k, v in metas))

def php_serialize_settings(d):
    parts = ''.join('s:%d:"%s";s:%d:"%s";' % (len(k), k, len(v.encode()), v) for k, v in d.items())
    return 'a:%d:{%s}' % (len(d), parts)

def fallback_html(blocks):
    # post_content mirrors the page for search engines / if Elementor is switched off
    out = []
    def walk(e):
        for x in e.get('elements', []):
            if x['elType'] == 'widget':
                s = x['settings'].get('editor') or x['settings'].get('html') or ''
                out.append(re.sub(r'<script.*?</script>', '', s, flags=re.S))
            walk(x)
    for b in blocks: walk(b)
    return '\n'.join(out)

def main():
    global NAV_LOGO, FOOTER, JS
    idx = BeautifulSoup(open(os.path.join(ROOT, 'index.html')).read(), 'html.parser')
    NAV_LOGO = str(idx.body.find('nav', recursive=False))
    FOOTER = str(idx.body.find('footer', recursive=False))
    JS = load_js()

    css = build_css()
    open(os.path.join(OUT, 'truman-enterprise-styles.css'), 'w').write(css)

    items = []
    att_id = 7001
    for orig, (new, alt) in IMAGES.items():
        url = GITHUB_RAW + new
        items.append(item(att_id, new.rsplit('.', 1)[0].replace('-', ' ').title(), new.rsplit('.', 1)[0], 'attachment',
                          status='inherit', extra='\t\t<wp:attachment_url>%s</wp:attachment_url>\n' % cdata(url),
                          metas=[('_wp_attachment_image_alt', alt)]))
        att_id += 1

    for order, (fname, pid, slug, title, _) in enumerate(PAGES):
        blocks = build_page(fname)
        data = json.dumps(blocks, ensure_ascii=False, separators=(',', ':'))
        json.loads(data)
        items.append(item(pid, title, slug, 'page', content=fallback_html(blocks), menu_order=order, metas=[
            ('_wp_page_template', 'elementor_canvas'),
            ('_elementor_edit_mode', 'builder'),
            ('_elementor_template_type', 'wp-page'),
            ('_elementor_version', '3.25.0'),
            ('_elementor_page_settings', php_serialize_settings({'hide_title': 'yes'})),
            ('_elementor_data', data),
        ]))

    # Customizer > Additional CSS for the Hello Elementor theme
    items.append(item(7201, 'hello-elementor', 'hello-elementor', 'custom_css', content=css))

    wxr = (
        '<?xml version="1.0" encoding="UTF-8" ?>\n'
        '<rss version="2.0"\n'
        '\txmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"\n'
        '\txmlns:content="http://purl.org/rss/1.0/modules/content/"\n'
        '\txmlns:wfw="http://wellformedweb.org/CommentAPI/"\n'
        '\txmlns:dc="http://purl.org/dc/elements/1.1/"\n'
        '\txmlns:wp="http://wordpress.org/export/1.2/"\n>\n'
        '<channel>\n'
        '\t<title>Truman Enterprise Narrowboat Trust</title>\n'
        '\t<link>https://truman-enterprise.org.uk</link>\n'
        '\t<description>Accessible canal boat trips in the West Midlands since 1968</description>\n'
        '\t<language>en-GB</language>\n'
        '\t<wp:wxr_version>1.2</wp:wxr_version>\n'
        '\t<wp:base_site_url>https://truman-enterprise.org.uk</wp:base_site_url>\n'
        '\t<wp:base_blog_url>https://truman-enterprise.org.uk</wp:base_blog_url>\n'
        '\t<wp:author><wp:author_id>1</wp:author_id><wp:author_login>%s</wp:author_login>'
        '<wp:author_email>%s</wp:author_email><wp:author_display_name>%s</wp:author_display_name>'
        '<wp:author_first_name><![CDATA[]]></wp:author_first_name><wp:author_last_name><![CDATA[]]></wp:author_last_name></wp:author>\n'
        '\t<generator>truman-enterprise wordpress/build.py</generator>\n'
        '%s'
        '</channel>\n</rss>\n'
    ) % (cdata('admin'), cdata(''), cdata('admin'), ''.join(items))
    path = os.path.join(OUT, 'truman-enterprise-wordpress.xml')
    open(path, 'w').write(wxr)
    print('wrote', path, len(wxr) // 1024, 'KB;', len(PAGES), 'pages,', len(IMAGES), 'images')

if __name__ == '__main__':
    main()
