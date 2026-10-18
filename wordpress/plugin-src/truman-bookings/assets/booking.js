/* Truman Boat Bookings – public calendar and request form. No dependencies. */
(function () {
  'use strict';

  var C = window.TB_CONFIG || {};
  var MAXP = parseInt(C.maxPassengers, 10) || 12;
  var MAXW = parseInt(C.maxWheelchair, 10);
  if (isNaN(MAXW)) MAXW = 2;
  var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  var DOW = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
  var LABELS = {
    available: 'Available',
    limited: 'Some trips available',
    provisional: 'Provisionally booked',
    booked: 'Booked',
    closed: 'Not running'
  };
  var cache = {};

  /* ---------- helpers ---------- */
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function ym(y, m) { return y + '-' + pad(m + 1); }
  function parseYmd(s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
  function longDate(s) {
    return parseYmd(s).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
  }
  function isSunday(s) { return parseYmd(s).getDay() === 0; }
  function money(n) { return '£' + (Math.round(n) === n ? n : n.toFixed(2)); }

  function h(tag, attrs, kids) {
    var e = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (k) {
        var v = attrs[k];
        if (v === null || v === undefined || v === false) return;
        if (k === 'class') e.className = v;
        else if (k === 'text') e.textContent = v;
        else if (k.slice(0, 2) === 'on') e.addEventListener(k.slice(2), v);
        else e.setAttribute(k, v === true ? '' : v);
      });
    }
    (kids || []).forEach(function (c) {
      if (c === null || c === undefined || c === false) return;
      e.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
    });
    return e;
  }

  function api(path, opts) {
    return fetch(C.api + path, opts || {}).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) {
        if (!r.ok) { j.httpStatus = r.status; throw j; }
        return j;
      });
    });
  }

  function loadMonth(key, fresh) {
    if (!fresh && cache[key]) return Promise.resolve(cache[key]);
    return api('availability?month=' + key + '&_=' + Date.now()).then(function (j) { cache[key] = j; return j; });
  }

  function hasOpenDays(data) {
    return Object.keys(data.days).some(function (d) { var s = data.days[d].s; return s === 'available' || s === 'limited'; });
  }

  /* ---------- calendar ---------- */
  function Calendar(root, opts) {
    this.root = root;
    this.opts = opts;
    this.selected = opts.selected || null;
    var now = new Date();
    this.y = now.getFullYear();
    this.m = now.getMonth();
    if (this.selected) { var d = parseYmd(this.selected); this.y = d.getFullYear(); this.m = d.getMonth(); }
    this.minKey = ym(now.getFullYear(), now.getMonth());
    this.el = h('div', { class: 'tb-cal' });
    root.appendChild(this.el);
  }

  Calendar.prototype.start = function () {
    var self = this;
    if (self.selected) return self.show();
    // Jump forward to the first month with free dates (e.g. out of season).
    var tries = 0;
    function step() {
      return loadMonth(ym(self.y, self.m)).then(function (data) {
        if (hasOpenDays(data) || tries >= 8 || ym(self.y, self.m) >= data.maxMonth) return self.render(data);
        tries++;
        self.m++; if (self.m > 11) { self.m = 0; self.y++; }
        return step();
      });
    }
    return step().catch(function () { self.error(); });
  };

  Calendar.prototype.show = function (fresh) {
    var self = this;
    self.el.classList.add('is-loading');
    return loadMonth(ym(self.y, self.m), fresh).then(function (d) { self.render(d); }).catch(function () { self.error(); });
  };

  Calendar.prototype.error = function () {
    this.el.innerHTML = '';
    this.el.appendChild(h('p', { class: 'tb-error', text: 'Sorry, the calendar could not be loaded. Please refresh the page or call us on ' + (C.phone || '') + '.' }));
  };

  Calendar.prototype.move = function (delta) {
    this.m += delta;
    if (this.m > 11) { this.m = 0; this.y++; }
    if (this.m < 0) { this.m = 11; this.y--; }
    this.show();
  };

  Calendar.prototype.render = function (data) {
    var self = this;
    self.data = data;
    var key = ym(self.y, self.m);
    var first = new Date(self.y, self.m, 1);
    var offset = (first.getDay() + 6) % 7; // Monday first
    var days = new Date(self.y, self.m + 1, 0).getDate();

    var prev = h('button', { type: 'button', class: 'tb-cal-nav', 'aria-label': 'Previous month', disabled: key <= self.minKey, onclick: function () { self.move(-1); } }, ['‹']);
    var next = h('button', { type: 'button', class: 'tb-cal-nav', 'aria-label': 'Next month', disabled: key >= data.maxMonth, onclick: function () { self.move(1); } }, ['›']);
    var head = h('div', { class: 'tb-cal-head' }, [prev, h('h3', { class: 'tb-cal-title', 'aria-live': 'polite', text: MONTHS[self.m] + ' ' + self.y }), next]);

    var grid = h('div', { class: 'tb-cal-grid', role: 'grid' });
    DOW.forEach(function (d) { grid.appendChild(h('div', { class: 'tb-cal-dow', 'aria-hidden': 'true', text: self.opts.mini ? d.charAt(0) : d })); });
    for (var i = 0; i < offset; i++) grid.appendChild(h('div', { class: 'tb-day tb-day--blank', 'aria-hidden': 'true' }));

    for (var n = 1; n <= days; n++) {
      var date = key + '-' + pad(n);
      var info = data.days[date] || { s: 'closed', trips: [] };
      var open = info.s === 'available' || info.s === 'limited';
      var cls = 'tb-day tb-day--' + info.s + (date === data.today ? ' tb-day--today' : '') + (date === self.selected ? ' is-selected' : '');
      var label = longDate(date) + ': ' + (info.s === 'closed' ? (info.r || LABELS.closed) : LABELS[info.s]);
      var btn = h('button', {
        type: 'button', class: cls, 'data-date': date, 'aria-label': label, title: label,
        'aria-pressed': date === self.selected ? 'true' : 'false',
        disabled: !open,
        onclick: (function (d) { return function () { self.pick(d); }; })(date)
      }, [h('span', { class: 'tb-day-num', text: String(n) })]);
      grid.appendChild(btn);
    }

    var legend = h('div', { class: 'tb-legend' }, ['available', 'limited', 'provisional', 'booked'].map(function (s) {
      return h('span', { class: 'tb-legend-item' }, [h('i', { class: 'tb-dot tb-dot--' + s }), LABELS[s]]);
    }));

    self.el.innerHTML = '';
    self.el.classList.remove('is-loading');
    self.el.appendChild(head);
    self.el.appendChild(grid);
    self.el.appendChild(legend);
    if (!hasOpenDays(data)) {
      self.el.appendChild(h('p', { class: 'tb-cal-empty', text: 'No dates available this month — use › to look ahead.' }));
    }
  };

  Calendar.prototype.pick = function (date) {
    this.selected = date;
    if (this.data) this.render(this.data);
    if (this.opts.onPick) this.opts.onPick(date, this.data.days[date], this.data);
  };

  /* ---------- compact calendar ---------- */
  function initMini(root) {
    root.innerHTML = '';
    var cal = new Calendar(root, {
      mini: true,
      onPick: function (date) {
        var url = C.bookingUrl + (C.bookingUrl.indexOf('?') > -1 ? '&' : '?') + 'date=' + date + '#tb-booking';
        window.location.href = url;
      }
    });
    root.appendChild(h('p', { class: 'tb-mini-note', text: 'Tap a green date to request it.' }));
    cal.start();
  }

  /* ---------- booking app ---------- */
  function initBooking(root) {
    var params = new URLSearchParams(window.location.search);
    var preDate = /^\d{4}-\d{2}-\d{2}$/.test(params.get('date') || '') ? params.get('date') : null;
    var preTrip = params.get('trip');
    var state = { date: null, trip: null, info: null, trips: [], started: Date.now(), details: {} };

    root.innerHTML = '';
    var steps = h('ol', { class: 'tb-steps', 'aria-hidden': 'true' }, [
      h('li', { class: 'is-active', text: 'Choose a date' }),
      h('li', { text: 'Choose a trip' }),
      h('li', { text: 'Your details' })
    ]);
    var intro = h('div', { class: 'tb-intro' }, [
      h('h2', { text: 'Request a Boat Trip' }),
      h('p', { text: 'Pick a date on the calendar to see which trips are free. Your date is held as provisional as soon as you send your request, and a volunteer will email you to confirm.' })
    ]);
    var calWrap = h('div', { class: 'tb-section tb-section--cal' });
    var tripWrap = h('div', { class: 'tb-section tb-section--trips', hidden: true });
    var formWrap = h('div', { class: 'tb-section tb-section--form', hidden: true });
    var doneWrap = h('div', { class: 'tb-section tb-section--done', hidden: true, tabindex: '-1' });
    [intro, steps, calWrap, tripWrap, formWrap, doneWrap].forEach(function (x) { root.appendChild(x); });

    function setStep(n) {
      Array.prototype.forEach.call(steps.children, function (li, i) {
        li.className = i < n ? 'is-done' : (i === n ? 'is-active' : '');
      });
    }
    function scrollTo(el) {
      var top = el.getBoundingClientRect().top + window.pageYOffset - 110;
      window.scrollTo({ top: top, behavior: 'smooth' });
    }

    var cal = new Calendar(calWrap, {
      selected: preDate,
      onPick: function (date, info, data) {
        state.date = date; state.info = info; state.trips = data.trips; state.trip = null;
        showTrips(true);
      }
    });
    cal.start().then(function () {
      if (preDate && cal.data && cal.data.days[preDate]) {
        var info = cal.data.days[preDate];
        if (info.s === 'available' || info.s === 'limited') {
          state.date = preDate; state.info = info; state.trips = cal.data.trips;
          if (preTrip && info.trips.indexOf(preTrip) > -1) { state.trip = preTrip; showTrips(false); showForm(false); }
          else showTrips(false);
        }
      }
    });

    function showTrips(scroll) {
      formWrap.hidden = true;
      tripWrap.hidden = false;
      tripWrap.innerHTML = '';
      setStep(1);
      tripWrap.appendChild(h('h3', { class: 'tb-h', text: longDate(state.date) }));
      tripWrap.appendChild(h('p', { class: 'tb-sub', text: state.info.s === 'limited' ? 'Part of this day is already booked — these trips are still free:' : 'Choose your trip:' }));
      var list = h('div', { class: 'tb-trips', role: 'radiogroup', 'aria-label': 'Trips' });
      state.trips.forEach(function (t) {
        var free = state.info.trips.indexOf(t.id) > -1;
        var card = h('button', {
          type: 'button', role: 'radio', class: 'tb-trip' + (state.trip === t.id ? ' is-selected' : '') + (free ? '' : ' is-unavailable'),
          'aria-checked': state.trip === t.id ? 'true' : 'false', disabled: !free,
          onclick: function () { state.trip = t.id; showTrips(false); showForm(true); }
        }, [
          h('span', { class: 'tb-trip-top' }, [h('strong', { text: t.name }), h('span', { class: 'tb-trip-price', text: money(t.price) })]),
          h('span', { class: 'tb-trip-time', text: t.time + (free ? '' : ' · not available on this date') }),
          h('span', { class: 'tb-trip-desc', text: t.description })
        ]);
        list.appendChild(card);
      });
      tripWrap.appendChild(list);
      tripWrap.appendChild(h('p', { class: 'tb-small', text: 'Prices are for the whole boat (up to ' + MAXP + ' passengers). Food is not included.' }));
      if (scroll) scrollTo(tripWrap);
    }

    function field(name, label, input, opts) {
      opts = opts || {};
      input.id = 'tb-' + name;
      input.name = name;
      if (opts.required) { input.required = true; input.setAttribute('aria-required', 'true'); }
      if (state.details[name] !== undefined && input.type !== 'checkbox') input.value = state.details[name];
      return h('div', { class: 'tb-field' + (opts.wide ? ' tb-field--wide' : ''), 'data-field': name }, [
        h('label', { for: input.id }, [label, opts.required ? h('span', { class: 'tb-req', 'aria-hidden': 'true', text: ' *' }) : null]),
        input,
        opts.hint ? h('span', { class: 'tb-hint', text: opts.hint }) : null,
        h('span', { class: 'tb-field-error', role: 'alert' })
      ]);
    }

    function showForm(scroll) {
      var trip = state.trips.filter(function (t) { return t.id === state.trip; })[0];
      if (!trip) return;
      setStep(2);
      formWrap.hidden = false;
      formWrap.innerHTML = '';

      var lunch = h('select', null, [h('option', { value: '', text: '— Choose later —' })].concat(
        trip.lunch.filter(function (o) { return !(isSunday(state.date) && /not sunday/i.test(o)); })
          .map(function (o) { return h('option', { value: o, text: o }); })));
      var wheel = h('select', null, Array.apply(null, Array(MAXW + 1)).map(function (_, i) { return h('option', { value: String(i), text: String(i) }); }));
      var terms = h('input', { type: 'checkbox', id: 'tb-terms', name: 'terms' });
      var photos = h('input', { type: 'checkbox', id: 'tb-photos', name: 'photos' });
      if (state.details.photos) photos.checked = true;

      var termsText = ['I understand that the price is for the whole boat, payment is due 28 days before the trip, and food is not included.'];
      if (C.termsUrl) termsText = ['I have read the ', h('a', { href: C.termsUrl, target: '_blank', rel: 'noopener', text: 'booking terms' }), ' and understand that payment is due 28 days before the trip and food is not included.'];

      var summary = h('div', { class: 'tb-summary' }, [
        h('div', null, [h('span', { class: 'tb-summary-label', text: 'Your trip' }), h('strong', { text: trip.name })]),
        h('div', null, [h('span', { class: 'tb-summary-label', text: 'Date' }), h('strong', { text: longDate(state.date) })]),
        h('div', null, [h('span', { class: 'tb-summary-label', text: 'Times' }), h('strong', { text: trip.time })]),
        h('div', null, [h('span', { class: 'tb-summary-label', text: 'Price' }), h('strong', { text: money(trip.price) + ' per boat' })]),
        h('button', { type: 'button', class: 'tb-link', text: 'Change', onclick: function () { scrollTo(calWrap); } })
      ]);

      var status = h('div', { class: 'tb-form-status', role: 'alert' });
      var submit = h('button', { type: 'submit', class: 'tb-submit btn btn-primary', text: 'Send booking request' });

      var form = h('form', { class: 'tb-form', novalidate: true }, [
        summary,
        h('h3', { class: 'tb-h', text: 'Your details' }),
        h('div', { class: 'tb-grid' }, [
          field('group_name', 'Group / organisation name', h('input', { type: 'text', autocomplete: 'organization', placeholder: 'e.g. Sunflower Day Centre' }), { required: true, wide: true }),
          field('contact_name', 'Contact name', h('input', { type: 'text', autocomplete: 'name' }), { required: true }),
          field('phone', 'Phone number', h('input', { type: 'tel', autocomplete: 'tel' }), { required: true }),
          field('email', 'Email address', h('input', { type: 'email', autocomplete: 'email' }), { required: true, wide: true, hint: 'We’ll send your confirmation here.' }),
          field('address', 'Postal address', h('textarea', { rows: '3', autocomplete: 'street-address' }), { required: true, wide: true, hint: 'For the invoice.' }),
          field('day_contact', 'Contact on the day', h('input', { type: 'text' }), { hint: 'If different from above' }),
          field('day_mobile', 'Mobile on the day', h('input', { type: 'tel' }))
        ]),
        h('h3', { class: 'tb-h', text: 'About your trip' }),
        h('div', { class: 'tb-grid' }, [
          field('passengers', 'Number of passengers', h('input', { type: 'number', min: '1', max: String(MAXP), inputmode: 'numeric' }), { required: true, hint: 'Up to ' + MAXP + ', including at least 2 group leaders or staff' }),
          field('wheelchairs', 'Wheelchair users', wheel, { hint: 'Ramp and hydraulic lift on board' }),
          field('lunch', 'Lunch', lunch, { wide: true, hint: isSunday(state.date) ? 'Fish & chips are not available on Sundays.' : null }),
          field('needs', 'Anything else we should know?', h('textarea', { rows: '3', placeholder: 'Access needs, mobility equipment, questions…' }), { wide: true })
        ]),
        h('div', { class: 'tb-checks' }, [
          h('label', { class: 'tb-check', for: 'tb-photos' }, [photos, h('span', { text: 'I’m happy for photos of our group to be used in the Trust’s publicity.' })]),
          h('div', { class: 'tb-field', 'data-field': 'terms' }, [
            h('label', { class: 'tb-check', for: 'tb-terms' }, [terms, h('span', null, termsText.concat([h('span', { class: 'tb-req', text: ' *' })]))]),
            h('span', { class: 'tb-field-error', role: 'alert' })
          ])
        ]),
        h('input', { type: 'text', name: 'website', tabindex: '-1', autocomplete: 'off', class: 'tb-hp', 'aria-hidden': 'true' }),
        status,
        submit,
        h('p', { class: 'tb-small', text: 'Questions? Call ' + (C.phone || '') + '.' })
      ]);
      if (state.details.wheelchairs) wheel.value = state.details.wheelchairs;

      form.addEventListener('submit', function (e) {
        e.preventDefault();
        send(form, submit, status, trip);
      });
      formWrap.appendChild(form);
      if (scroll) scrollTo(formWrap);
    }

    function clearErrors(form) {
      Array.prototype.forEach.call(form.querySelectorAll('.tb-field'), function (f) {
        f.classList.remove('has-error');
        var e = f.querySelector('.tb-field-error'); if (e) e.textContent = '';
      });
    }
    function showErrors(form, errs) {
      var firstEl = null;
      Object.keys(errs).forEach(function (k) {
        var f = form.querySelector('[data-field="' + k + '"]');
        if (!f) return;
        f.classList.add('has-error');
        f.querySelector('.tb-field-error').textContent = errs[k];
        if (!firstEl) firstEl = f.querySelector('input,select,textarea');
      });
      if (firstEl) firstEl.focus();
    }

    function send(form, submit, status, trip) {
      clearErrors(form);
      status.textContent = '';
      var fd = {};
      ['group_name', 'contact_name', 'phone', 'email', 'address', 'day_contact', 'day_mobile', 'passengers', 'wheelchairs', 'lunch', 'needs', 'website'].forEach(function (k) {
        var el = form.elements[k]; fd[k] = el ? el.value.trim() : '';
      });
      fd.photos = form.elements.photos.checked;
      fd.terms = form.elements.terms.checked;

      var errs = {};
      if (!fd.group_name) errs.group_name = 'Please enter your group or organisation name.';
      if (!fd.contact_name) errs.contact_name = 'Please enter a contact name.';
      if (!fd.phone) errs.phone = 'Please enter a phone number.';
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(fd.email)) errs.email = 'Please enter a valid email address.';
      if (!fd.address) errs.address = 'Please enter your postal address.';
      var p = parseInt(fd.passengers, 10);
      if (!(p >= 1 && p <= MAXP)) errs.passengers = 'Please enter between 1 and ' + MAXP + ' passengers.';
      if (!fd.terms) errs.terms = 'Please tick to confirm.';
      if (Object.keys(errs).length) { showErrors(form, errs); status.textContent = 'Please check the highlighted details.'; return; }

      // Remember details for a second request.
      state.details = { group_name: fd.group_name, contact_name: fd.contact_name, phone: fd.phone, email: fd.email, address: fd.address, day_contact: fd.day_contact, day_mobile: fd.day_mobile, wheelchairs: fd.wheelchairs, photos: fd.photos };

      var body = Object.assign({}, fd, { date: state.date, trip: state.trip, started: state.started });
      submit.disabled = true;
      submit.textContent = 'Sending…';
      api('requests', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
        .then(function (res) { done(res, trip); })
        .catch(function (err) {
          submit.disabled = false;
          submit.textContent = 'Send booking request';
          if (err && err.data && err.data.fields) showErrors(form, err.data.fields);
          status.textContent = (err && err.message) ? err.message : 'Sorry, something went wrong. Please try again or call ' + (C.phone || '') + '.';
          if (err && err.code === 'tb_clash') { delete cache[state.date.slice(0, 7)]; cal.show(true); }
        });
    }

    function done(res, trip) {
      delete cache[state.date.slice(0, 7)];
      tripWrap.hidden = true; formWrap.hidden = true;
      setStep(3);
      doneWrap.hidden = false;
      doneWrap.innerHTML = '';
      doneWrap.appendChild(h('div', { class: 'tb-done' }, [
        h('div', { class: 'tb-done-icon', 'aria-hidden': 'true', text: '✓' }),
        h('h3', { text: 'Thank you — your request has been sent' }),
        h('p', null, ['Your reference is ', h('strong', { text: res.reference }), '.']),
        h('p', { text: trip.name + ' on ' + longDate(state.date) + ' is now held as provisional. We’ve emailed a copy to ' + res.email + ', and a volunteer will be in touch to confirm, with an invoice.' }),
        h('div', { class: 'tb-done-actions' }, [
          h('button', {
            type: 'button', class: 'btn btn-primary', text: 'Request another date', onclick: function () {
              doneWrap.hidden = true; state.date = null; state.trip = null; state.started = Date.now();
              cal.selected = null; setStep(0); cal.show(true); scrollTo(calWrap);
            }
          })
        ])
      ]));
      cal.selected = null;
      cal.show(true);
      doneWrap.focus();
      scrollTo(doneWrap);
    }
  }

  function boot() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-tb]'), function (root) {
      if (root.getAttribute('data-tb-ready')) return;
      root.setAttribute('data-tb-ready', '1');
      if (root.getAttribute('data-tb') === 'booking') initBooking(root);
      else initMini(root);
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
