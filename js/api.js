/* W5D — Form wiring helpers (RESTful Table API) */
(function () {
  const api = {
    async post(table, data) {
      const res = await fetch(`tables/${table}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });
      if (!res.ok) throw new Error(`API ${res.status}`);
      return res.json();
    }
  };

  function showStatus(form, type, msg) {
    let box = form.querySelector('[data-form-status]');
    if (!box) {
      box = document.createElement('div');
      box.setAttribute('data-form-status', '');
      box.className = 'mt-3 text-sm rounded-lg px-3 py-2';
      form.appendChild(box);
    }
    box.textContent = msg;
    box.className = 'mt-3 text-sm rounded-lg px-3 py-2 ' + (
      type === 'ok'  ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' :
      type === 'err' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400' :
                       'bg-slate-500/10 text-slate-600 dark:text-slate-400'
    );
  }

  function setBusy(btn, busy, busyLabel) {
    if (!btn) return;
    if (busy) {
      btn.dataset._label = btn.textContent;
      btn.disabled = true;
      btn.style.opacity = .7;
      btn.textContent = busyLabel || 'Sending…';
    } else {
      btn.disabled = false;
      btn.style.opacity = 1;
      if (btn.dataset._label) btn.textContent = btn.dataset._label;
    }
  }

  document.addEventListener('DOMContentLoaded', () => {

    // ---------- Newsletter (any form with [data-newsletter]) ----------
    document.querySelectorAll('form[data-newsletter]').forEach(form => {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = form.querySelector('input[type=email]')?.value?.trim();
        if (!email) return showStatus(form, 'err', 'Please enter your email.');
        const btn = form.querySelector('button[type=submit], button:not([type])');
        setBusy(btn, true, 'Subscribing…');
        try {
          await api.post('subscribers', {
            email,
            source_page: location.pathname.split('/').pop() || 'index.html',
            status: 'active'
          });
          form.reset();
          showStatus(form, 'ok', 'Subscribed — check your inbox for confirmation.');
        } catch (err) {
          showStatus(form, 'err', 'Something went wrong. Please try again.');
        } finally {
          setBusy(btn, false);
        }
      });
    });

    // ---------- Contact form ----------
    const contactForm = document.querySelector('form[data-contact]');
    if (contactForm) {
      contactForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(contactForm);
        const payload = {
          name: fd.get('name')?.toString().trim(),
          email: fd.get('email')?.toString().trim(),
          company: fd.get('company')?.toString().trim() || '',
          topic: fd.get('topic')?.toString() || 'other',
          message: fd.get('message')?.toString().trim(),
          status: 'new'
        };
        if (!payload.name || !payload.email || !payload.message) {
          return showStatus(contactForm, 'err', 'Name, email, and message are required.');
        }
        const btn = contactForm.querySelector('button[type=submit]');
        setBusy(btn, true);
        try {
          await api.post('contact_messages', payload);
          contactForm.reset();
          showStatus(contactForm, 'ok', 'Message received. We\'ll reply within one business day.');
        } catch (err) {
          showStatus(contactForm, 'err', 'Could not send. Try again or email us at hello@w5d.ai');
        } finally {
          setBusy(btn, false);
        }
      });
    }

    // ---------- Demo request ----------
    const demoForm = document.querySelector('form[data-demo]');
    if (demoForm) {
      demoForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(demoForm);
        const payload = {
          name: fd.get('name')?.toString().trim(),
          work_email: fd.get('work_email')?.toString().trim(),
          company: fd.get('company')?.toString().trim(),
          team_size: fd.get('team_size')?.toString() || '1-10',
          use_case: fd.get('use_case')?.toString().trim() || '',
          status: 'new'
        };
        if (!payload.name || !payload.work_email || !payload.company) {
          return showStatus(demoForm, 'err', 'Name, work email, and company are required.');
        }
        const btn = demoForm.querySelector('button[type=submit]');
        setBusy(btn, true, 'Booking…');
        try {
          await api.post('demo_requests', payload);
          demoForm.reset();
          showStatus(demoForm, 'ok', 'Got it — a solutions engineer will reach out within 24 hours.');
        } catch (err) {
          showStatus(demoForm, 'err', 'Could not submit. Try again later.');
        } finally {
          setBusy(btn, false);
        }
      });
    }

    // ---------- Careers application ----------
    const careersForm = document.querySelector('form[data-careers]');
    if (careersForm) {
      careersForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(careersForm);
        const payload = {
          name: fd.get('name')?.toString().trim(),
          email: fd.get('email')?.toString().trim(),
          role: fd.get('role')?.toString().trim(),
          portfolio_url: fd.get('portfolio_url')?.toString().trim() || '',
          cover_letter: fd.get('cover_letter')?.toString().trim() || '',
          status: 'new'
        };
        if (!payload.name || !payload.email || !payload.role) {
          return showStatus(careersForm, 'err', 'Name, email, and role are required.');
        }
        const btn = careersForm.querySelector('button[type=submit]');
        setBusy(btn, true, 'Submitting…');
        try {
          await api.post('careers_applications', payload);
          careersForm.reset();
          showStatus(careersForm, 'ok', 'Application received. We review every one within 7 days.');
        } catch (err) {
          showStatus(careersForm, 'err', 'Submission failed. Please try again.');
        } finally {
          setBusy(btn, false);
        }
      });
    }
  });
})();
