const $ = s => document.querySelector(s); const $$ = s => [...document.querySelectorAll(s)];

$('#menu')?.addEventListener('click', () => $('#side').classList.toggle('open'));
$$('.pw button').forEach(b => { b.onclick = () => { const i = b.previousElementSibling; i.type = i.type == 'password' ? 'text' : 'password'; }; });

$$('[data-f]').forEach(b => {
    b.onclick = () => {
        let n = 0;
        $$('[data-f]').forEach(x => x.classList.toggle('on', x == b));
        $$('[data-c]').forEach(c => {
            const s = b.dataset.f == 'all' || c.dataset.c == b.dataset.f;
            c.hidden = !s;
            n += s;
        });
        $('#count').textContent = n + ' services · Something for everyone';
    };
});

const cal = $('#cal');

if (cal) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    // The time slots come from PHP (getTimeSlots), so they match what the database accepts
    const slots = JSON.parse(cal.dataset.slots),
          dur = +cal.dataset.dur;

    let y = today.getFullYear(),
        m = today.getMonth(),
        sel = new Date(today),
        t = slots[0];

    if (!sel.getDay()) sel.setDate(sel.getDate() + 1);

    // Times already booked for the chosen barber + date (loaded from booked_times.php)
    let taken = [], loadId = 0;

    async function loadTaken() {
        const id = ++loadId;
        const barber = $('input[name=barber_id]:checked').value;
        const date = sel.toLocaleDateString('en-CA');
        let list = [];
        try {
            const r = await fetch(`booked_times.php?barber_id=${barber}&date=${date}`, { credentials: 'same-origin' });
            list = (await r.json()).times || [];
        } catch (e) { /* if it fails the server still blocks a taken time */ }
        if (id !== loadId) return;          // an older answer arrived late - ignore it
        taken = list;
        draw();
    }

    const mins = s => {
        const [a, b, p] = s.split(/[: ]/);
        return (+a % 12 + (p.toLowerCase() == 'pm' ? 12 : 0)) * 60 + +b;
    };

    const out = n => {
        const h = Math.floor(n / 60) % 24;
        return (h % 12 || 12) + ':' + String(n % 60).padStart(2, '0') + ' ' + (h < 12 ? 'am' : 'pm');
    };

    const L = (d, o) => d.toLocaleDateString('en-US', o);

    // A time is "passed" if the chosen day is today and that time is already over
    const passed = s => {
        if (+sel != +today) return false;
        const n = new Date();
        return mins(s) <= n.getHours() * 60 + n.getMinutes();
    };

    function draw() {
        if (passed(t) || taken.includes(t)) t = slots.find(x => !passed(x) && !taken.includes(x)) || t;
        const first = (new Date(y, m, 1).getDay() + 6) % 7,
              n = new Date(y, m + 1, 0).getDate();

        let h = '<span></span>'.repeat(first);

        for (let d = 1; d <= n; d++) {
            const dt = new Date(y, m, d),
                  off = dt < today || !dt.getDay();
            h += `<button type="button" class="day${+dt == +sel ? ' on' : ''}" data-d="${d}" ${off ? 'disabled' : ''}>${d}</button>`;
        }

        cal.innerHTML = h;
        $('#ttl').textContent = L(new Date(y, m), { month: 'long', year: 'numeric' });

        $('#slots').innerHTML = slots.map(s =>
            `<button type="button" class="slot${s == t ? ' on' : ''}" ${taken.includes(s) || passed(s) ? 'disabled' : ''}>${s}</button>`
        ).join('');

        $('#dl').textContent = L(sel, { weekday: 'long', month: 'long', day: 'numeric' });
        $('#sd').textContent = L(sel, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
        $('#st').textContent = `${t} – ${out(mins(t) + dur)} · ${dur} minutes`;
        $('#fd').value = sel.toLocaleDateString('en-CA');
        $('#ft').value = t;
        $('#sb').textContent = $('input[name=barber_id]:checked').dataset.n;

        // No free time left on this day: don't let the form be sent
        const none = passed(t) || taken.includes(t);
        const btn = $('#confirmbtn');
        if (btn) btn.disabled = none;
        $('#fnote') && ($('#fnote').hidden = !none);
    }

    cal.onclick = e => {
        const d = e.target.closest('.day');
        if (d) {
            sel = new Date(y, m, +d.dataset.d);
            draw();
            loadTaken();
        }
    };

    $('#slots').onclick = e => {
        const s = e.target.closest('.slot');
        if (s) {
            t = s.textContent;
            draw();
        }
    };

    $('#prev').onclick = () => {
        if (y > today.getFullYear() || m > today.getMonth()) {
            m--;
            if (m < 0) {
                m = 11;
                y--;
            }
            draw();
        }
    };

    $('#next').onclick = () => { m++; if (m > 11) { m = 0; y++; } draw(); };
    $$('input[name=barber_id]').forEach(r => r.onchange = () => { draw(); loadTaken(); });
    draw();
    loadTaken();
}