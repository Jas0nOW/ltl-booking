(function () {
  function qs(id) {
    return document.getElementById(id);
  }

  function setDetails(html) {
    var el = qs('ltlb-admin-calendar-details');
    if (!el) return;
    var empty = qs('ltlb-admin-calendar-details-empty');
    if (empty) empty.style.display = 'none';
    el.innerHTML = html;
    el.hidden = false;
  }

  function clearDetails() {
    var el = qs('ltlb-admin-calendar-details');
    if (!el) return;
    el.hidden = true;
    el.innerHTML = '';
    var empty = qs('ltlb-admin-calendar-details-empty');
    if (empty) empty.style.display = '';
  }

  function fmt(v) {
    return (v === null || v === undefined) ? '' : String(v);
  }

  function esc(s) {
    return fmt(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function ensureApiFetchNonce() {
    if (!window.wp || !wp.apiFetch || !window.ltlbAdminCalendar) return;
    if (!ltlbAdminCalendar.nonce) return;
    wp.apiFetch.use(wp.apiFetch.createNonceMiddleware(ltlbAdminCalendar.nonce));
  }

  function restPath(path) {
    return path;
  }

  function t(key, fallback) {
    if (window.ltlbAdminCalendar && ltlbAdminCalendar.i18n && ltlbAdminCalendar.i18n[key]) {
      return String(ltlbAdminCalendar.i18n[key]);
    }
    return fallback || key;
  }

  function cssEscape(v) {
    var s = String(v);
    if (window.CSS && CSS.escape) return CSS.escape(s);
    return s.replace(/[^a-zA-Z0-9_-]/g, '\\$&');
  }

  document.addEventListener('DOMContentLoaded', function () {
    var container = qs('ltlb-admin-calendar');
    if (!container || !window.FullCalendar) return;

    ensureApiFetchNonce();

    function statusOptions(selected) {
      var opts = [
        { v: 'pending', l: t('pending', 'Pending') },
        { v: 'confirmed', l: t('confirmed', 'Confirmed') },
        { v: 'cancelled', l: t('cancelled', 'Cancelled') }
      ];
      return opts.map(function (o) {
        return '<option value="' + esc(o.v) + '"' + (o.v === selected ? ' selected' : '') + '>' + esc(o.l) + '</option>';
      }).join('');
    }

    function renderDetails(ev, payload) {
      var a = payload && payload.appointment ? payload.appointment : null;
      var s = payload && payload.service ? payload.service : null;
      var c = payload && payload.customer ? payload.customer : null;

      var customerName = '';
      if (c) {
        customerName = (fmt(c.first_name) + ' ' + fmt(c.last_name)).trim();
      }

      var status = a ? a.status : (ev.extendedProps && ev.extendedProps.status) || '';

      var html = '';
      html += '<div class="ltlb-calendar-details__header">';
      html += '<strong>' + esc(ev.title) + '</strong>';
      html += '</div>';

      html += '<div class="ltlb-calendar-details__grid">';
      html += '<div><span class="ltlb-muted">' + esc(t('id', 'ID')) + '</span><div>#' + esc(ev.id) + '</div></div>';
      html += '<div><span class="ltlb-muted">' + esc(t('service', 'Service')) + '</span><div>' + esc(s ? s.name : '') + '</div></div>';
      html += '<div><span class="ltlb-muted">' + esc(t('start', 'Start')) + '</span><div>' + esc(a ? a.start_at : '') + '</div></div>';
      html += '<div><span class="ltlb-muted">' + esc(t('end', 'End')) + '</span><div>' + esc(a ? a.end_at : '') + '</div></div>';
      html += '</div>';

      // Status editor
      html += '<div class="ltlb-calendar-details__section">';
      html += '<div class="ltlb-calendar-details__section-title">' + esc(t('status', 'Status')) + '</div>';
      html += '<div class="ltlb-inline">';
      html += '<select data-ltlb-field="status" data-ltlb-id="' + esc(ev.id) + '">' + statusOptions(status) + '</select>';
      html += '<button type="button" class="button" data-ltlb-action="save-status" data-ltlb-id="' + esc(ev.id) + '">' + esc(t('save', 'Save')) + '</button>';
      html += '</div>';
      html += '</div>';

      // Customer editor
      html += '<div class="ltlb-calendar-details__section">';
      html += '<div class="ltlb-calendar-details__section-title">' + esc(t('customer', 'Customer')) + '</div>';
      if (!c) {
        html += '<div class="ltlb-muted">' + esc(t('no_customer_data', 'No customer data.')) + '</div>';
      } else {
        html += '<div class="ltlb-form-grid">';
        html += '<div><label class="ltlb-muted">' + esc(t('first_name', 'First name')) + '</label><input type="text" data-ltlb-field="first_name" data-ltlb-customer-id="' + esc(c.id) + '" value="' + esc(c.first_name || '') + '" /></div>';
        html += '<div><label class="ltlb-muted">' + esc(t('last_name', 'Last name')) + '</label><input type="text" data-ltlb-field="last_name" data-ltlb-customer-id="' + esc(c.id) + '" value="' + esc(c.last_name || '') + '" /></div>';
        html += '<div><label class="ltlb-muted">' + esc(t('email', 'Email')) + '</label><input type="email" data-ltlb-field="email" data-ltlb-customer-id="' + esc(c.id) + '" value="' + esc(c.email || '') + '" /></div>';
        html += '<div><label class="ltlb-muted">' + esc(t('phone', 'Phone')) + '</label><input type="text" data-ltlb-field="phone" data-ltlb-customer-id="' + esc(c.id) + '" value="' + esc(c.phone || '') + '" /></div>';
        html += '<div style="grid-column: 1 / -1;"><label class="ltlb-muted">' + esc(t('notes', 'Notes')) + '</label><textarea rows="3" data-ltlb-field="notes" data-ltlb-customer-id="' + esc(c.id) + '">' + esc(c.notes || '') + '</textarea></div>';
        html += '</div>';
        html += '<div class="ltlb-calendar-details__actions" style="margin-top:10px;">';
        html += '<button type="button" class="button" data-ltlb-action="save-customer" data-ltlb-customer-id="' + esc(c.id) + '">' + esc(t('save_customer', 'Save Customer')) + '</button>';
        html += '</div>';
      }
      html += '<div class="ltlb-calendar-details__actions" style="margin-top:12px;">';
      html += '<button type="button" class="button" data-ltlb-action="delete" data-ltlb-id="' + esc(ev.id) + '">' + esc(t('delete_appointment', 'Delete Appointment')) + '</button>';
      html += '<a class="button" href="admin.php?page=ltlb_appointments">' + esc(t('open_appointments', 'Open Appointments List')) + '</a>';
      html += '</div>';
      html += '</div>';

      setDetails(html);
    }

    var selectedEl = null;

    function splitTitle(title) {
      var s = fmt(title);
      var parts = s.split('–');
      if (parts.length >= 2) {
        return {
          service: parts[0].trim(),
          customer: parts.slice(1).join('–').trim()
        };
      }
      return { service: s, customer: '' };
    }

    function hourStr(h) {
      var n = parseInt(h || 0, 10);
      if (isNaN(n) || n < 0) n = 0;
      if (n > 23) n = 23;
      return String(n).padStart(2, '0') + ':00:00';
    }

    function startOfDay(d) {
      var x = new Date(d);
      x.setHours(0, 0, 0, 0);
      return x;
    }

    var whStart = (window.ltlbAdminCalendar && ltlbAdminCalendar.workingHoursStart !== undefined) ? ltlbAdminCalendar.workingHoursStart : null;
    var whEnd = (window.ltlbAdminCalendar && ltlbAdminCalendar.workingHoursEnd !== undefined) ? ltlbAdminCalendar.workingHoursEnd : null;

    var calendarLocale = (window.ltlbAdminCalendar && ltlbAdminCalendar.locale) ? String(ltlbAdminCalendar.locale) : 'en';

    var calendar = new FullCalendar.Calendar(container, {
      initialView: 'timeGridWeek',
      height: 'auto',
      nowIndicator: true,
      locale: calendarLocale,
      editable: true,
      selectable: false,
      eventResizableFromStart: true,
      views: {
        timeGridWeek: {
          duration: { days: 7 },
          dateAlignment: 'day',
          // With a rolling visibleRange, FullCalendar may otherwise increment by 1 day.
          // We want the arrows to jump week-by-week.
          dateIncrement: { days: 7 },
          visibleRange: function (currentDate) {
            var start = startOfDay(currentDate);
            var end = new Date(start);
            end.setDate(end.getDate() + 7);
            return { start: start, end: end };
          }
        }
      },
      slotMinTime: (whStart === null) ? undefined : hourStr(whStart),
      slotMaxTime: (whEnd === null) ? undefined : hourStr(whEnd),
      scrollTime: (whStart === null) ? undefined : hourStr(Math.max(0, parseInt(whStart, 10) - 1)),
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay'
      },
      buttonText: {
        // Use translated labels and map both generic and view-specific keys.
        today: t('today', 'Today'),
        month: t('month', 'Month'),
        week: t('week', 'Week'),
        day: t('day', 'Day'),
        dayGridMonth: t('month', 'Month'),
        timeGridWeek: t('week', 'Week'),
        timeGridDay: t('day', 'Day')
      },

      // Enforce 24h time formatting (common for German) and keep it consistent.
      slotLabelFormat: {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
      },
      eventTimeFormat: {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
      },
      events: function (info, success, failure) {
        wp.apiFetch({
          path: restPath('/ltlb/v1/admin/calendar/events?start=' + encodeURIComponent(info.startStr) + '&end=' + encodeURIComponent(info.endStr))
        }).then(function (events) {
          success(events);
        }).catch(function (err) {
          failure(err);
        });
      },
      eventClassNames: function (arg) {
        var s = (arg && arg.event && arg.event.extendedProps) ? arg.event.extendedProps.status : '';
        var classes = [];
        if (s) classes.push('ltlb-fc-status-' + String(s));
        return classes;
      },

      eventContent: function (arg) {
        var parts = splitTitle(arg.event && arg.event.title ? arg.event.title : '');
        var time = fmt(arg.timeText);
        var html = '';
        html += '<div class="ltlb-fc-event">';
        if (time) {
          html += '<div class="ltlb-fc-time">' + esc(time) + '</div>';
        }
        html += '<div class="ltlb-fc-lines">';
        html += '<div class="ltlb-fc-service">' + esc(parts.service) + '</div>';
        if (parts.customer) {
          html += '<div class="ltlb-fc-customer">' + esc(parts.customer) + '</div>';
        }
        html += '</div>';
        html += '</div>';
        return { html: html };
      },

      eventDidMount: function (info) {
        // Tooltip for quick scanning
        try {
          var st = info.event && info.event.extendedProps ? fmt(info.event.extendedProps.status) : '';
          var title = fmt(info.event.title);
          var start = info.event.start ? info.event.start.toLocaleString() : '';
          var end = info.event.end ? info.event.end.toLocaleString() : '';
          info.el.setAttribute('title', [title, st ? ('Status: ' + st) : '', start ? ('Start: ' + start) : '', end ? ('End: ' + end) : ''].filter(Boolean).join('\n'));
        } catch (e) {
          // ignore
        }
      },
      eventDrop: function (info) {
        var ev = info.event;
        wp.apiFetch({
          path: restPath('/ltlb/v1/admin/appointments/' + encodeURIComponent(ev.id) + '/move'),
          method: 'POST',
          data: {
            start: ev.start ? ev.start.toISOString() : null,
            end: ev.end ? ev.end.toISOString() : null
          }
        }).then(function (res) {
          if (!res || !res.ok) {
            info.revert();
			alert(t('could_not_update_appointment', 'Could not update appointment.'));
          }
        }).catch(function (err) {
          info.revert();
		  if (err && err.data && err.data.error === 'conflict') {
      alert(t('conflict_message', 'This time slot conflicts with an existing booking.'));
			return;
		  }
      alert(t('could_not_update_appointment', 'Could not update appointment.'));
        });
      },
      eventResize: function (info) {
        var ev = info.event;
        wp.apiFetch({
          path: restPath('/ltlb/v1/admin/appointments/' + encodeURIComponent(ev.id) + '/move'),
          method: 'POST',
          data: {
            start: ev.start ? ev.start.toISOString() : null,
            end: ev.end ? ev.end.toISOString() : null
          }
        }).then(function (res) {
          if (!res || !res.ok) {
            info.revert();
			alert(t('could_not_update_appointment', 'Could not update appointment.'));
          }
        }).catch(function (err) {
          info.revert();
		  if (err && err.data && err.data.error === 'conflict') {
      alert(t('conflict_message', 'This time slot conflicts with an existing booking.'));
			return;
		  }
      alert(t('could_not_update_appointment', 'Could not update appointment.'));
        });
      },
      eventClick: function (info) {
        var ev = info.event;
        clearDetails();

        if (selectedEl) {
          selectedEl.classList.remove('ltlb-fc-selected');
        }
        selectedEl = info.el || null;
        if (selectedEl) {
          selectedEl.classList.add('ltlb-fc-selected');
        }

        wp.apiFetch({
          path: restPath('/ltlb/v1/admin/appointments/' + encodeURIComponent(ev.id))
        }).then(function (payload) {
          renderDetails(ev, payload);
        }).catch(function () {
          setDetails('<div class="notice notice-error"><p>' + esc(t('could_not_load_details', 'Could not load appointment details.')) + '</p></div>');
        });
      }
    });

    calendar.render();

    document.addEventListener('click', function (e) {
      var target = e.target;
      if (!target || !target.getAttribute) return;
      var action = target.getAttribute('data-ltlb-action');

      if (action === 'delete') {
        var id = target.getAttribute('data-ltlb-id');
        if (!id) return;
        if (!confirm(t('confirm_delete', 'Delete this appointment?'))) return;

        wp.apiFetch({
          path: restPath('/ltlb/v1/admin/appointments/' + encodeURIComponent(id)),
          method: 'DELETE'
        }).then(function (res) {
          if (res && res.ok) {
            clearDetails();
            calendar.refetchEvents();
          } else {
            alert(t('could_not_delete_appointment', 'Could not delete appointment.'));
          }
        }).catch(function () {
          alert(t('could_not_delete_appointment', 'Could not delete appointment.'));
        });
        return;
      }

      if (action === 'save-status') {
        var apptId = target.getAttribute('data-ltlb-id');
        if (!apptId) return;
        var sel = document.querySelector('select[data-ltlb-field="status"][data-ltlb-id="' + cssEscape(apptId) + '"]');
        var status = sel ? sel.value : '';
        if (!status) return;

        wp.apiFetch({
          path: restPath('/ltlb/v1/admin/appointments/' + encodeURIComponent(apptId) + '/status'),
          method: 'POST',
          data: { status: status }
        }).then(function (res) {
          if (res && res.ok) {
            calendar.refetchEvents();
          } else {
            alert(t('could_not_update_status', 'Could not update status.'));
          }
        }).catch(function () {
          alert(t('could_not_update_status', 'Could not update status.'));
        });
        return;
      }

      if (action === 'save-customer') {
        var customerId = target.getAttribute('data-ltlb-customer-id');
        if (!customerId) return;

        function getVal(field) {
          var el = document.querySelector('[data-ltlb-customer-id="' + cssEscape(customerId) + '"][data-ltlb-field="' + cssEscape(field) + '"]');
          return el ? el.value : '';
        }

        var data = {
          first_name: getVal('first_name'),
          last_name: getVal('last_name'),
          email: getVal('email'),
          phone: getVal('phone'),
          notes: getVal('notes')
        };

        wp.apiFetch({
          path: restPath('/ltlb/v1/admin/customers/' + encodeURIComponent(customerId)),
          method: 'POST',
          data: data
        }).then(function (res) {
          if (res && res.ok) {
            calendar.refetchEvents();
            alert(t('customer_saved', 'Customer saved.'));
          } else {
            alert(t('could_not_save_customer', 'Could not save customer.'));
          }
        }).catch(function () {
          alert(t('could_not_save_customer', 'Could not save customer.'));
        });
        return;
      }
    });
  });
})();
