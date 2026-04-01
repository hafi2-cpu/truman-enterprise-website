// =============================================
// Truman Enterprise Narrowboat Trust - JS
// =============================================

// ---- Navigation hamburger ----
document.addEventListener('DOMContentLoaded', function () {
  const hamburger = document.querySelector('.nav-hamburger');
  const navLinks = document.querySelector('.nav-links');
  if (hamburger && navLinks) {
    hamburger.addEventListener('click', function () {
      navLinks.classList.toggle('open');
    });
    document.addEventListener('click', function (e) {
      if (!e.target.closest('nav')) navLinks.classList.remove('open');
    });
  }

  // Set active nav link
  const currentPage = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nav-links a').forEach(a => {
    if (a.getAttribute('href') === currentPage) a.classList.add('active');
  });

  // Init all components
  initHeroSlider();
  initAccordions();
  initCalendar();
  initBookingForm();
  animateStats();
});

// ---- Hero Slider ----
function initHeroSlider() {
  const slides = document.querySelectorAll('.hero-slide');
  const dots = document.querySelectorAll('.hero-dot');
  if (!slides.length) return;

  let current = 0;
  let timer = null;

  function goTo(idx) {
    slides[current].classList.remove('active');
    if (dots[current]) dots[current].classList.remove('active');
    current = (idx + slides.length) % slides.length;
    slides[current].classList.add('active');
    if (dots[current]) dots[current].classList.add('active');
  }

  function start() { timer = setInterval(() => goTo(current + 1), 5000); }
  function reset() { clearInterval(timer); start(); }

  dots.forEach((dot, i) => dot.addEventListener('click', () => { goTo(i); reset(); }));

  slides[0].classList.add('active');
  if (dots[0]) dots[0].classList.add('active');
  if (slides.length > 1) start();
}

// ---- Accordions ----
function initAccordions() {
  document.querySelectorAll('.accordion-header').forEach(header => {
    header.addEventListener('click', function () {
      const body = this.nextElementSibling;
      const isOpen = this.classList.contains('open');

      // Close all in this accordion group
      const accordion = this.closest('.accordion');
      if (accordion) {
        accordion.querySelectorAll('.accordion-header').forEach(h => {
          h.classList.remove('open');
          h.nextElementSibling.classList.remove('open');
        });
      }

      if (!isOpen) {
        this.classList.add('open');
        body.classList.add('open');
      }
    });
  });
}

// ---- Calendar ----
const TRIP_DATA = {
  // Format: 'YYYY-MM-DD': 'available' | 'provisional' | 'booked'
  // These are example dates - in production these would come from a backend
};

// Generate sample data for April-October of current year
(function seedCalendar() {
  const year = new Date().getFullYear();
  const months = [3, 4, 5, 6, 7, 8, 9]; // Apr-Oct (0-indexed)
  const statuses = ['available', 'available', 'available', 'provisional', 'booked'];
  months.forEach(m => {
    const daysInMonth = new Date(year, m + 1, 0).getDate();
    for (let d = 1; d <= daysInMonth; d++) {
      if ([0, 6].includes(new Date(year, m, d).getDay())) continue; // skip some
      if (Math.random() > 0.45) {
        const key = `${year}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        TRIP_DATA[key] = statuses[Math.floor(Math.random() * statuses.length)];
      }
    }
  });
})();

function initCalendar() {
  const wrapper = document.getElementById('booking-calendar');
  if (!wrapper) return;

  const today = new Date();
  let viewYear = today.getFullYear();
  let viewMonth = today.getMonth(); // Start at current month

  // Start at April if current month is before April
  if (viewMonth < 3) viewMonth = 3;

  renderCalendar(wrapper, viewYear, viewMonth);

  wrapper.querySelector('.cal-prev').addEventListener('click', () => {
    viewMonth--;
    if (viewMonth < 0) { viewMonth = 11; viewYear--; }
    renderCalendar(wrapper, viewYear, viewMonth);
  });

  wrapper.querySelector('.cal-next').addEventListener('click', () => {
    viewMonth++;
    if (viewMonth > 11) { viewMonth = 0; viewYear++; }
    renderCalendar(wrapper, viewYear, viewMonth);
  });
}

function renderCalendar(wrapper, year, month) {
  const monthNames = ['January','February','March','April','May','June',
                      'July','August','September','October','November','December'];
  const today = new Date();

  wrapper.querySelector('.cal-month-label').textContent = `${monthNames[month]} ${year}`;

  const daysContainer = wrapper.querySelector('.calendar-days');
  daysContainer.innerHTML = '';

  const firstDay = new Date(year, month, 1).getDay();
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const prevDays = new Date(year, month, 0).getDate();

  // Monday-first offset
  const offset = (firstDay + 6) % 7;

  // Prev month padding
  for (let i = offset - 1; i >= 0; i--) {
    const btn = document.createElement('button');
    btn.className = 'cal-day other-month';
    btn.textContent = prevDays - i;
    daysContainer.appendChild(btn);
  }

  // Current month days
  for (let d = 1; d <= daysInMonth; d++) {
    const dateKey = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    const status = TRIP_DATA[dateKey];
    const btn = document.createElement('button');
    btn.className = 'cal-day';
    btn.textContent = d;

    if (status) btn.classList.add(status);

    const isToday = d === today.getDate() && month === today.getMonth() && year === today.getFullYear();
    if (isToday) btn.classList.add('today');

    if (status === 'available') {
      btn.title = 'Available - click to book';
      btn.addEventListener('click', () => {
        const bookingSection = document.getElementById('booking-section');
        if (bookingSection) {
          bookingSection.scrollIntoView({ behavior: 'smooth' });
          // Pre-fill date if form exists
          const dateInput = document.querySelector('input[name="trip1_date"]');
          if (dateInput) {
            dateInput.value = dateKey;
          }
        }
      });
    } else if (status === 'booked') {
      btn.title = 'Fully booked';
    } else if (status === 'provisional') {
      btn.title = 'Provisional booking - contact us to enquire';
    }

    daysContainer.appendChild(btn);
  }

  // Next month padding
  const total = offset + daysInMonth;
  const remaining = total % 7 === 0 ? 0 : 7 - (total % 7);
  for (let d = 1; d <= remaining; d++) {
    const btn = document.createElement('button');
    btn.className = 'cal-day other-month';
    btn.textContent = d;
    daysContainer.appendChild(btn);
  }
}

// ---- Booking Form ----
function initBookingForm() {
  const form = document.getElementById('booking-form');
  if (!form) return;

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    const terms = form.querySelector('input[name="terms"]');
    if (!terms || !terms.checked) {
      alert('Please read and agree to the Terms and Conditions before submitting.');
      return;
    }

    // Collect form data
    const data = new FormData(form);
    const subject = `Boat Trip Booking - ${data.get('group_name') || 'New Booking'}`;
    const body = buildEmailBody(data);

    // Open mailto link
    const mailto = `mailto:enterprise@truman-enterprise.org.uk?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
    window.location.href = mailto;

    const success = form.querySelector('.success-message');
    if (success) success.style.display = 'block';
  });
}

function buildEmailBody(data) {
  let body = 'TRIP BOOKING REQUEST\n';
  body += '====================\n\n';
  body += `Group Name: ${data.get('group_name') || ''}\n`;
  body += `Contact Name: ${data.get('contact_name') || ''}\n`;
  body += `Address: ${data.get('address') || ''}\n`;
  body += `Phone: ${data.get('phone') || ''}\n`;
  body += `Email: ${data.get('email') || ''}\n`;
  body += `Day-of Contact: ${data.get('day_contact') || ''}\n`;
  body += `Day-of Mobile: ${data.get('day_mobile') || ''}\n\n`;
  body += 'TRIPS REQUESTED:\n';
  for (let i = 1; i <= 3; i++) {
    const date = data.get(`trip${i}_date`);
    const dest = data.get(`trip${i}_dest`);
    const lunch = data.get(`trip${i}_lunch`);
    if (date || dest) {
      body += `  Trip ${i}: Date: ${date || '—'} | Destination: ${dest || '—'} | Lunch: ${lunch || '—'}\n`;
    }
  }
  body += `\nPhotos consent: ${data.get('photos') ? 'Yes' : 'No'}\n`;
  return body;
}

// ---- Stats counter animation ----
function animateStats() {
  const stats = document.querySelectorAll('.stat-number[data-target]');
  if (!stats.length) return;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const el = entry.target;
        const target = parseInt(el.dataset.target);
        const suffix = el.dataset.suffix || '';
        let current = 0;
        const step = Math.ceil(target / 50);
        const interval = setInterval(() => {
          current = Math.min(current + step, target);
          el.textContent = current.toLocaleString() + suffix;
          if (current >= target) clearInterval(interval);
        }, 30);
        observer.unobserve(el);
      }
    });
  }, { threshold: 0.5 });

  stats.forEach(stat => observer.observe(stat));
}
