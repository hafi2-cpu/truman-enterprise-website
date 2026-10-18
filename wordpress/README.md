# Truman Enterprise — WordPress theme

`dist/truman-enterprise.zip` is a complete WordPress theme for the Truman Enterprise
Narrowboat Trust site, built from the static HTML in this repo. Pages are built
with **Elementor (free)** so they can be edited visually.

## Install (one click after upload)

1. **Appearance → Themes → Add New → Upload Theme**, choose `truman-enterprise.zip`, **Install**, **Activate**.
2. You land on **Appearance → Truman Setup** — press **Set up the site**. It:
   - installs and activates Elementor (if needed)
   - adds the photos to the Media Library
   - creates Home, Our Trips, Get Involved, Support Us, Contact and Book a Trip
   - creates the header and footer menus
   - sets Home as the homepage and switches on tidy links (/our-trips/)

Running setup again is safe — existing pages are left alone unless you tick
“Restore the original content”. If a page already uses one of the addresses,
it's kept as a draft named `<address>-old`.

## Editing

- **Page content:** Pages → Edit with Elementor. Text sections are Text Editor widgets; the
  hero slider, stats bar and booking form are HTML widgets (they use scripts).
- **Menus:** Appearance → Menus (“Main menu” and “Footer menu”). Give an item the CSS class
  `nav-cta` to show it as the teal button.
- **Header/footer, styling:** in the theme (`header.php`, `footer.php`, `style.css`).

## Rebuilding

Change the static HTML/CSS/JS, then from the repo root run `python3 wordpress/build.py`.
Theme PHP lives in `wordpress/theme-src/`.

## Booking calendar (Truman Boat Bookings plugin)

`dist/truman-bookings.zip` (also bundled in the theme and installed by Truman Setup).

- **Visitors**: Book a Trip page (`[truman_booking]`) — pick a date, choose a trip that fits that day, send a request.
  The date is held as *provisional*; the bookings email gets the request and the group gets an acknowledgement.
  Our Trips sidebar (`[truman_availability]`) shows a compact calendar that links to the booking page.
- **Volunteers**: *Boat Bookings* in the WordPress menu — month calendar, requests list, confirm/decline (emails the group),
  add phone bookings, close days the boat isn't running, internal notes.
- **Settings**: notification email, season, notice period, gap between trips, capacity, and the trips themselves
  (name, price, times, lunch options). Two trips share a day only if their times don't overlap.
- Source: `wordpress/plugin-src/truman-bookings/`.
