# Truman Enterprise — WordPress + Elementor (free) version

`truman-enterprise-wordpress.xml` recreates the static site as six Elementor pages
(Home, Our Trips, Get Involved, Support Us, Contact, Book a Trip) for the
**Hello Elementor** theme, plus the photos and the site stylesheet.

## Importing on the staging site

1. **Plugins:** install and activate **Elementor** (free). Install the **Hello Elementor** theme and activate it.
2. **Permalinks:** Settings → Permalinks → **Post name** → Save (the menus link to `/our-trips/` etc.).
3. **Import:** Tools → Import → WordPress → *Install Now* → *Run Importer* → upload
   `truman-enterprise-wordpress.xml` → assign posts to your admin user → **tick “Download and import file attachments”** → Submit.
4. **Homepage:** Settings → Reading → *A static page* → Homepage: **Home** → Save.
5. **Check styling:** Appearance → Customize → Additional CSS should now contain the Truman stylesheet.
   If it is empty (e.g. the site already had custom CSS), paste in `truman-enterprise-styles.css`.

## How the pages are built

- Each page uses the **Elementor Canvas** template, so the header and footer are part of the page.
- Every block is a full-width Elementor container. Text sections are **Text Editor** widgets, so wording
  can be edited directly in Elementor. The header, hero slider, stats bar, booking form and footer
  are **HTML** widgets, because they rely on scripts.
- All styling lives in **Appearance → Customize → Additional CSS** (every block has the class `te-block`).
- Photos are imported into the Media Library at `/wp-content/uploads/2026/10/`.

## Rebuilding

Edit the static HTML/CSS, then run `python3 wordpress/build.py` from the repo root.
