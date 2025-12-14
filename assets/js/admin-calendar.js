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

  function announce(message, opts) {
    var msg = fmt(message).trim();
    if (!msg) return;

    var isAssertive = opts && opts.assertive;
    var el = qs(isAssertive ? 'ltlb-admin-calendar-live-assertive' : 'ltlb-admin-calendar-live');
    if (!el) return;

    // Clear then set text to ensure screen readers announce repeated messages.
    try {
      el.textContent = '';
      window.setTimeout(function () {
        el.textContent = msg;
      }, 25);
    } catch (e) {
      // ignore
    }
  }

  function cssEscape(v) {
    var s = String(v);
    if (window.CSS && CSS.escape) return CSS.escape(s);
    return s.replace(/[^a-zA-Z0-9_-]/g, '\\$&');
  }

  function normalizeHexColor(v) {
    var s = fmt(v).trim();
    if (!s) return '';
    if (/^[0-9a-fA-F]{6}$/.test(s)) return '#' + s.toLowerCase();
    if (/^#[0-9a-fA-F]{6}$/.test(s)) return s.toLowerCase();
    return '';
  }

  function applyStatusColorsToRoot(colors) {
    var root = document.querySelector('.ltlb-admin');
    if (!root || !colors) return;
    if (colors.confirmed) root.style.setProperty('--ltlb-status-confirmed', normalizeHexColor(colors.confirmed) || colors.confirmed);
    if (colors.pending) root.style.setProperty('--ltlb-status-pending', normalizeHexColor(colors.pending) || colors.pending);
    if (colors.cancelled) root.style.setProperty('--ltlb-status-cancelled', normalizeHexColor(colors.cancelled) || colors.cancelled);
  }

  function renderRoomsList(rooms) {
    var el = qs('ltlb-admin-calendar-rooms');
    if (!el) return;
    var list = Array.isArray(rooms) ? rooms : [];
    if (!list.length) {
      el.innerHTML = '<div class="ltlb-muted">' + esc(t('no_rooms', 'No rooms found.')) + '</div>';
      return;
    }

    var html = '';
    for (var i = 0; i < list.length; i++) {
      var r = list[i] || {};
      var name = fmt(r.name);
      var typeCode = fmt(r.typeCode);
      var typeNames = (r.typeNames && Array.isArray(r.typeNames)) ? r.typeNames : [];
      var typeLabel = typeNames.length ? typeNames.join(', ') : '';
      var metaParts = [];
      if (typeCode) metaParts.push(typeCode);
      if (typeLabel) metaParts.push(typeLabel);
      html += '<div class="ltlb-calendar-room" role="listitem">';
      html += '<div class="ltlb-calendar-room__name">' + esc(name) + '</div>';
      html += '<div class="ltlb-calendar-room__meta">' + esc(metaParts.join(' • ')) + '</div>';
      html += '</div>';
    }
    el.innerHTML = html;
  }

  document.addEventListener('DOMContentLoaded', function () {
    ensureApiFetchNonce();
    
    // Setup legend toggle
    var legendToggle = document.querySelector('.ltlb-calendar-legend-toggle');
    var legendItems = qs('ltlb-calendar-legend-items');
    if (legendToggle && legendItems) {
      legendToggle.addEventListener('click', function() {
        var isExpanded = legendToggle.getAttribute('aria-expanded') === 'true';
        legendToggle.setAttribute('aria-expanded', !isExpanded);
        legendItems.setAttribute('aria-hidden', isExpanded);
      });
    }

    // Apply persisted calendar status colors (CSS variables)
    if (window.ltlbAdminCalendar && ltlbAdminCalendar.statusColors) {
      applyStatusColorsToRoot(ltlbAdminCalendar.statusColors);
    }

    // Hotel mode: rooms list was previously shown in a left panel.
    // The new hotel "room-rack" renders rooms directly as calendar rows.

    // Persist status colors from native <input type="color"> controls in the legend.
    (function setupLegendColorInputs() {
      var inputs = document.querySelectorAll('.ltlb-calendar-legend__color[data-ltlb-status]');
      if (!inputs || !inputs.length) return;

      function getCurrentColorFor(status) {
        if (window.ltlbAdminCalendar && ltlbAdminCalendar.statusColors && ltlbAdminCalendar.statusColors[status]) {
          return normalizeHexColor(ltlbAdminCalendar.statusColors[status]);
        }
        var root = document.querySelector('.ltlb-admin');
        if (!root) return '';
        var cssVal = window.getComputedStyle(root).getPropertyValue('--ltlb-status-' + status).trim();
        return normalizeHexColor(cssVal);
      }

      function saveColors(colors) {
        if (!window.wp || !wp.apiFetch) return;
        wp.apiFetch({
          path: restPath('/ltlb/v1/admin/calendar/colors'),
          method: 'POST',
          data: { colors: colors }
        }).then(function (res) {
          if (res && res.colors && window.ltlbAdminCalendar) {
            ltlbAdminCalendar.statusColors = res.colors;
          }
          announce(t('color_saved', 'Color saved.'), { assertive: false });
        }).catch(function () {
          announce(t('could_not_save_color', 'Could not save color.'), { assertive: true });
          alert(t('could_not_save_color', 'Could not save color.'));
        });
      }

      for (var i = 0; i < inputs.length; i++) {
        (function (input) {
          var status = fmt(input.getAttribute('data-ltlb-status')).trim();
          if (!status) return;

          var current = getCurrentColorFor(status);
          if (current) {
            try { input.value = current; } catch (e) { /* ignore */ }
          }

          input.addEventListener('change', function () {
            var next = normalizeHexColor(input.value);
            if (!next) return;

            var colors = (window.ltlbAdminCalendar && ltlbAdminCalendar.statusColors) ? ltlbAdminCalendar.statusColors : {};
            colors = Object.assign({}, colors);
            colors[status] = next;

            if (window.ltlbAdminCalendar) {
              ltlbAdminCalendar.statusColors = colors;
            }
            applyStatusColorsToRoot(colors);
            saveColors(colors);
          });
        })(inputs[i]);
      }
    })();

    var container = qs('ltlb-admin-calendar');
    if (!container || !window.FullCalendar) return;

    function clamp01(x) {
      x = parseFloat(x);
      if (isNaN(x)) return 0;
      if (x < 0) return 0;
      if (x > 1) return 1;
      return x;
    }

    function parseCssColor(str) {
      str = (str || '').trim();
      if (!str) return null;
      if (str === 'transparent') return { r: 255, g: 255, b: 255, a: 0 };

      // rgb()/rgba()
      var m = str.match(/^rgba?\(([^)]+)\)$/i);
      if (m) {
        var parts = m[1].split(',').map(function (p) { return p.trim(); });
        if (parts.length >= 3) {
          var r = parseFloat(parts[0]);
          var g = parseFloat(parts[1]);
          var b = parseFloat(parts[2]);
          var a = (parts.length >= 4) ? clamp01(parts[3]) : 1;
          if ([r, g, b].some(function (v) { return isNaN(v); })) return null;
          return { r: r, g: g, b: b, a: a };
        }
      }

      // #rrggbb / #rgb
      if (str[0] === '#') {
        var hex = str.slice(1);
        if (hex.length === 3) {
          hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
        }
        if (/^[0-9a-f]{6}$/i.test(hex)) {
          return {
            r: parseInt(hex.slice(0, 2), 16),
            g: parseInt(hex.slice(2, 4), 16),
            b: parseInt(hex.slice(4, 6), 16),
            a: 1
          };
        }
      }

      return null;
    }

    function srgbToLin(c) {
      c = c / 255;
      return (c <= 0.04045) ? (c / 12.92) : Math.pow((c + 0.055) / 1.055, 2.4);
    }

    function relLuminance(rgb) {
      return 0.2126 * srgbToLin(rgb.r) + 0.7152 * srgbToLin(rgb.g) + 0.0722 * srgbToLin(rgb.b);
    }

    function mixRgb(a, b, wa) {
      wa = clamp01(wa);
      var wb = 1 - wa;
      return {
        r: Math.round(a.r * wa + b.r * wb),
        g: Math.round(a.g * wa + b.g * wb),
        b: Math.round(a.b * wa + b.b * wb),
        a: 1
      };
    }

    function rgbaCss(c, alphaOverride) {
      var a = (alphaOverride === undefined || alphaOverride === null) ? (c.a === undefined ? 1 : c.a) : alphaOverride;
      a = clamp01(a);
      return 'rgba(' + Math.round(c.r) + ',' + Math.round(c.g) + ',' + Math.round(c.b) + ',' + a + ')';
    }

    function rgbToHsl(rgb) {
      var r = rgb.r / 255;
      var g = rgb.g / 255;
      var b = rgb.b / 255;
      var max = Math.max(r, g, b);
      var min = Math.min(r, g, b);
      var h = 0;
      var s = 0;
      var l = (max + min) / 2;

      if (max !== min) {
        var d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        switch (max) {
          case r: h = (g - b) / d + (g < b ? 6 : 0); break;
          case g: h = (b - r) / d + 2; break;
          case b: h = (r - g) / d + 4; break;
        }
        h /= 6;
      }
      return { h: h, s: s, l: l };
    }

    function hslToRgb(hsl) {
      var h = hsl.h;
      var s = clamp01(hsl.s);
      var l = clamp01(hsl.l);

      function hue2rgb(p, q, t) {
        if (t < 0) t += 1;
        if (t > 1) t -= 1;
        if (t < 1 / 6) return p + (q - p) * 6 * t;
        if (t < 1 / 2) return q;
        if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
        return p;
      }

      var r, g, b;
      if (s === 0) {
        r = g = b = l;
      } else {
        var q = l < 0.5 ? l * (1 + s) : l + s - l * s;
        var p = 2 * l - q;
        r = hue2rgb(p, q, h + 1 / 3);
        g = hue2rgb(p, q, h);
        b = hue2rgb(p, q, h - 1 / 3);
      }
      return { r: Math.round(r * 255), g: Math.round(g * 255), b: Math.round(b * 255), a: 1 };
    }

    function applyDynamicCalendarColors() {
      // Apply to wrapper so both FullCalendar (inside #ltlb-admin-calendar) and hotel rack (sibling) inherit.
      var adminRoot = container.closest ? container.closest('.ltlb-admin') : document.querySelector('.ltlb-admin');
      if (!adminRoot) return;

      var cs = window.getComputedStyle(adminRoot);

      // Prefer the actual computed background of the calendar container.
      // This reliably reflects the backend "Panel-Hintergrund" after CSS vars are applied.
      var calCs = window.getComputedStyle(container);
      var calBg = parseCssColor(calCs.backgroundColor);

      // Resolve surface (panel background). If transparent/invalid, fall back to a white surface.
      var panelStr = cs.getPropertyValue('--lazy-panel-bg');
      var surface = (calBg && calBg.a !== undefined && calBg.a > 0.01) ? calBg : (parseCssColor(panelStr) || parseCssColor(cs.backgroundColor) || { r: 255, g: 255, b: 255, a: 1 });
      if (surface.a !== undefined && surface.a < 0.01) {
        surface = { r: 255, g: 255, b: 255, a: 1 };
      }

      // Today: derive from the actual surface (NOT from accent/primary), so it stays
      // readable and "nice" even if the panel is green/red/etc.
      var lum = relLuminance(surface);
      var hsl = rgbToHsl(surface);
      // Make it slightly lighter on dark surfaces, slightly darker on light surfaces.
      var lDelta = (lum > 0.6) ? -0.07 : 0.10;
      var todayHsl = {
        h: hsl.h,
        // Cap saturation to keep the highlight pastel/clean.
        s: Math.min(hsl.s, 0.22),
        l: clamp01(hsl.l + lDelta)
      };
      var today = hslToRgb(todayHsl);

      // Border: a bit stronger than fill for fast "Heute" scanning.
      var borderHsl = {
        h: todayHsl.h,
        s: Math.min(Math.max(todayHsl.s, 0.10), 0.28),
        l: clamp01(todayHsl.l + ((lum > 0.6) ? -0.10 : 0.14))
      };
      var todayBorder = hslToRgb(borderHsl);

      // Header surface: slightly contrasting to surface.
      var target = (lum > 0.5) ? { r: 0, g: 0, b: 0, a: 1 } : { r: 255, g: 255, b: 255, a: 1 };
      var headerMix = (lum > 0.5) ? 0.08 : 0.12;
      var header = mixRgb(surface, target, 1 - headerMix); // surface*(1-headerMix) + target*headerMix

      adminRoot.style.setProperty('--ltlb-calendar-surface', rgbaCss(surface, 1));
      adminRoot.style.setProperty('--ltlb-today-bg', rgbaCss(today, 1));
      adminRoot.style.setProperty('--ltlb-today-border', rgbaCss(todayBorder, 1));
      adminRoot.style.setProperty('--ltlb-calendar-header-surface', rgbaCss(header, 1));
    }

    // Ensure variables are set even if CSS color-mix isn't supported.
    // Important: admin design vars are injected via a <style> tag; depending on load order
    // we may need to re-run after styles settle.
    applyDynamicCalendarColors();
    setTimeout(applyDynamicCalendarColors, 0);
    setTimeout(applyDynamicCalendarColors, 120);
    setTimeout(applyDynamicCalendarColors, 600);
    try {
      window.addEventListener('load', applyDynamicCalendarColors, { once: true });
    } catch (e) {}
    try {
      var mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
      if (mq && mq.addEventListener) mq.addEventListener('change', applyDynamicCalendarColors);
      else if (mq && mq.addListener) mq.addListener(applyDynamicCalendarColors);
    } catch (e) {}

    var isHotelMode = (window.ltlbAdminCalendar && String(ltlbAdminCalendar.templateMode || '') === 'hotel');
    var occupancyByDate = {};
    var lastOccupancyKey = '';
    var hotelRackEl = qs('ltlb-admin-hotel-rack');
    var hotelRoomsCache = null;
    var hotelRackDataCache = null;
    var hotelRackCollapsed = {};
    var lastHotelRackKey = '';

    function ymdFromIso(iso) {
      var s = fmt(iso);
      if (s.length >= 10) return s.slice(0, 10);
      return '';
    }

    function parseYmd(ymd) {
      // Parse as UTC midnight to keep date math stable.
      if (!/^\d{4}-\d{2}-\d{2}$/.test(ymd)) return null;
      var parts = ymd.split('-');
      return new Date(Date.UTC(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10)));
    }

    function addDaysUTC(d, days) {
      var x = new Date(d.getTime());
      x.setUTCDate(x.getUTCDate() + days);
      return x;
    }

    function ymdUTC(d) {
      var y = d.getUTCFullYear();
      var m = String(d.getUTCMonth() + 1).padStart(2, '0');
      var dd = String(d.getUTCDate()).padStart(2, '0');
      return y + '-' + m + '-' + dd;
    }

    function ymdLocal(d) {
      var y = d.getFullYear();
      var m = String(d.getMonth() + 1).padStart(2, '0');
      var dd = String(d.getDate()).padStart(2, '0');
      return y + '-' + m + '-' + dd;
    }

    function listDatesExclusive(startStr, endStr) {
      var startYmd = ymdFromIso(startStr);
      var endYmd = ymdFromIso(endStr);
      var start = parseYmd(startYmd);
      var end = parseYmd(endYmd);
      if (!start || !end) return [];

      var out = [];
      var cur = start;
      while (cur < end) {
        out.push(ymdUTC(cur));
        cur = addDaysUTC(cur, 1);
      }
      return out;
    }

    function groupKeyForRoom(room) {
      var code = fmt(room && room.typeCode).trim();
      if (code === 'EZ' || code === 'DZ' || code === 'Fam') return code;
      // Backstop: infer from capacity so we never show a "Sonstiges" bucket.
      var cap = room && room.capacity ? parseInt(room.capacity, 10) : 2;
      if (!isNaN(cap)) {
        if (cap <= 1) return 'EZ';
        if (cap === 2) return 'DZ';
        return 'Fam';
      }
      return 'DZ';
    }

    function groupLabel(code) {
      if (code === 'Fam') return 'FamZimmer';
      return code;
    }

    function groupSort(a, b) {
      var order = { 'EZ': 1, 'DZ': 2, 'Fam': 3 };
      var aa = order[String(a)] || 99;
      var bb = order[String(b)] || 99;
      return aa - bb;
    }

    function formatRackHeader(ymd) {
      try {
        var loc = (window.ltlbAdminCalendar && ltlbAdminCalendar.locale) ? String(ltlbAdminCalendar.locale) : 'en';
        var locale = (loc === 'de') ? 'de-DE' : loc;
        var d = parseYmd(ymd);
        if (!d) return ymd;
        // Use UTC date but format as local calendar date.
        var local = new Date(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate());
        var fmtter = new Intl.DateTimeFormat(locale, { weekday: 'short', day: '2-digit', month: '2-digit' });
        return fmtter.format(local);
      } catch (e) {
        return ymd;
      }
    }

    function updateHotelTitleOccupancy(startStr, endStr) {
      if (!isHotelMode) return;
      var titleEl = container.querySelector('.fc-toolbar-title');
      if (!titleEl) return;

      // Capture base title once per range.
      var key = String(startStr || '') + '|' + String(endStr || '');
      if (titleEl.dataset.ltlbTitleKey !== key) {
        titleEl.dataset.ltlbTitleKey = key;
        titleEl.dataset.ltlbBaseTitle = titleEl.textContent || '';
      }

      var base = titleEl.dataset.ltlbBaseTitle || (titleEl.textContent || '');
      var dates = listDatesExclusive(startStr, endStr);
      var sum = 0;
      var n = 0;
      for (var i = 0; i < dates.length; i++) {
        var d = dates[i];
        var occ = occupancyByDate && occupancyByDate[d] ? occupancyByDate[d] : null;
        if (occ && occ.rate !== null && occ.rate !== undefined) {
          sum += parseInt(occ.rate, 10);
          n++;
        }
      }
      if (!n) {
        titleEl.textContent = base;
        return;
      }
      var avg = Math.round(sum / n);
      // Show exactly what was requested: occupancy % next to the date.
      titleEl.textContent = base + ' (' + String(avg) + '%)';
    }

    function fetchHotelRooms() {
      if (hotelRoomsCache) return Promise.resolve(hotelRoomsCache);
      return wp.apiFetch({ path: restPath('/ltlb/v1/admin/calendar/rooms') }).then(function (res) {
        hotelRoomsCache = (res && res.rooms && Array.isArray(res.rooms)) ? res.rooms : [];
        return hotelRoomsCache;
      });
    }

    function fetchHotelEvents(startStr, endStr) {
      return wp.apiFetch({
        path: restPath('/ltlb/v1/admin/calendar/events?start=' + encodeURIComponent(startStr) + '&end=' + encodeURIComponent(endStr))
      }).then(function (events) {
        return Array.isArray(events) ? events : [];
      });
    }

    function buildHotelRackModel(rooms, events) {
      var roomsById = {};
      (rooms || []).forEach(function (r) {
        var rid = fmt(r && r.id);
        if (rid) roomsById[rid] = r;
      });

      var bookingsByRoom = {};
      var unassigned = [];

      (events || []).forEach(function (ev) {
        if (!ev) return;
        var props = ev.extendedProps || {};
        var rids = props.resource_ids;
        var seats = props.seats ? parseInt(props.seats, 10) : 1;
        var startYmd = ymdFromIso(ev.start);
        var endYmd = ymdFromIso(ev.end);
        var booking = {
          id: fmt(ev.id),
          title: fmt(ev.title),
          status: fmt(props.status),
          startYmd: startYmd,
          endYmd: endYmd,
          seats: isNaN(seats) ? 1 : seats
        };

        if (Array.isArray(rids) && rids.length) {
          rids.forEach(function (rid) {
            var key = fmt(rid);
            if (!key) return;
            if (!bookingsByRoom[key]) bookingsByRoom[key] = [];
            bookingsByRoom[key].push(booking);
          });
          return;
        }

        unassigned.push(booking);
      });

      // Group rooms by type key
      var groups = {};
      (rooms || []).forEach(function (r) {
        var gk = groupKeyForRoom(r);
        if (!groups[gk]) groups[gk] = [];
        groups[gk].push(r);
      });

      // Only keep expected hotel groups and keep stable order EZ -> DZ -> Fam
      var groupOrder = Object.keys(groups).filter(function (k) {
        return k === 'EZ' || k === 'DZ' || k === 'Fam';
      });
      groupOrder.sort(groupSort);

      // Natural sort rooms by name
      groupOrder.forEach(function (gk) {
        groups[gk].sort(function (a, b) {
          return String(a.name || '').localeCompare(String(b.name || ''), undefined, { numeric: true, sensitivity: 'base' });
        });
      });

      return {
        roomsById: roomsById,
        bookingsByRoom: bookingsByRoom,
        unassigned: unassigned,
        groups: groups,
        groupOrder: groupOrder
      };
    }

    function clampRangeToView(startYmd, endYmd, viewDates) {
      if (!viewDates || !viewDates.length) return null;
      if (!startYmd || !endYmd) return null;
      var viewStart = viewDates[0];
      var viewEndExclusive = viewDates[viewDates.length - 1];
      // viewEndExclusive should be +1 day of last date, but we only have dates; handle with index lookups.
      var startIdx = viewDates.indexOf(startYmd);
      var endIdx = viewDates.indexOf(endYmd);
      // If endYmd not in view (common), find first date >= endYmd by stepping until match.
      if (startIdx === -1) {
        // If booking ends before view or starts after view, skip
        if (endYmd <= viewStart) return null;
        if (startYmd > viewDates[viewDates.length - 1]) return null;
        // Clamp start to viewStart
        startIdx = 0;
      }

      if (endIdx === -1) {
        // booking end may be beyond view; clamp to after last column
        if (startYmd > viewDates[viewDates.length - 1]) return null;
        endIdx = viewDates.length; // exclusive
      }

      // Ensure end is after start
      if (endIdx <= startIdx) return null;
      return { startIdx: startIdx, endIdx: endIdx };
    }

    function renderHotelRack(viewDates, model) {
      if (!hotelRackEl) return;
      if (!viewDates || !viewDates.length) {
        hotelRackEl.innerHTML = '';
        hotelRackEl.hidden = true;
        return;
      }

      var todayYmd = ymdLocal(new Date());
      var todayIdx = viewDates.indexOf(todayYmd);
      // Expose today column for CSS full-height highlight.
      if (todayIdx >= 0) {
        hotelRackEl.classList.add('ltlb-hotel-rack--has-today');
        hotelRackEl.style.setProperty('--ltlb-rack-cols', String(viewDates.length));
        hotelRackEl.style.setProperty('--ltlb-rack-today-idx', String(todayIdx));
      } else {
        hotelRackEl.classList.remove('ltlb-hotel-rack--has-today');
        hotelRackEl.style.removeProperty('--ltlb-rack-cols');
        hotelRackEl.style.removeProperty('--ltlb-rack-today-idx');
      }

      var cols = viewDates.length;
      var gridTpl = '220px repeat(' + cols + ', minmax(36px, 1fr))';
      var html = '';

      // Header row
      html += '<div class="ltlb-hotel-rack__row ltlb-hotel-rack__row--header" style="grid-template-columns:' + esc(gridTpl) + '">';
      html += '<div class="ltlb-hotel-rack__cell"><span class="ltlb-hotel-rack__room-label">' + esc(t('rooms', 'Rooms')) + '</span></div>';
      for (var i = 0; i < viewDates.length; i++) {
        var d = viewDates[i];
        var isToday = (d === todayYmd);
        var day = formatRackHeader(d);
        var occ = occupancyByDate && occupancyByDate[d] ? occupancyByDate[d] : null;
        var occText = (occ && occ.rate !== null && occ.rate !== undefined) ? String(occ.rate) + '%' : '';
        html += '<div class="ltlb-hotel-rack__cell' + (isToday ? ' ltlb-hotel-rack__cell--today' : '') + '" title="' + esc(d) + '">' + esc(day);
        if (occText) {
          html += ' <span class="ltlb-hotel-rack__occ">' + esc(occText) + '</span>';
        }
        html += '</div>';
      }
      html += '</div>';

      // Groups
      (model.groupOrder || []).forEach(function (gk) {
        var rooms = model.groups[gk] || [];
        var isCollapsed = !!hotelRackCollapsed[gk];

        // Group header row
        html += '<div class="ltlb-hotel-rack__row" style="grid-template-columns:' + esc(gridTpl) + '">';
        html += '<div class="ltlb-hotel-rack__cell">';
        html += '<button type="button" class="ltlb-hotel-rack__group-toggle" data-ltlb-group="' + esc(gk) + '" aria-expanded="' + (isCollapsed ? 'false' : 'true') + '">';
        html += esc(groupLabel(gk)) + ' <span class="ltlb-hotel-rack__group-sub">(' + esc(String(rooms.length)) + ')</span>';
        html += '</button>';
        html += '</div>';
        for (var c = 0; c < viewDates.length; c++) {
          var gd = viewDates[c];
          html += '<div class="ltlb-hotel-rack__cell' + (gd === todayYmd ? ' ltlb-hotel-rack__cell--today' : '') + '"></div>';
        }
        html += '</div>';

        if (isCollapsed) return;

        rooms.forEach(function (room) {
          var rid = fmt(room && room.id);
          html += '<div class="ltlb-hotel-rack__row ltlb-hotel-rack__row--room" style="grid-template-columns:' + esc(gridTpl) + '">';
          html += '<div class="ltlb-hotel-rack__cell"><span class="ltlb-hotel-rack__room-label">' + esc(fmt(room.name)) + '</span></div>';
          for (var j = 0; j < viewDates.length; j++) {
            var rd = viewDates[j];
            html += '<div class="ltlb-hotel-rack__cell' + (rd === todayYmd ? ' ltlb-hotel-rack__cell--today' : '') + '"></div>';
          }

          var bookings = (rid && model.bookingsByRoom[rid]) ? model.bookingsByRoom[rid] : [];
          bookings.forEach(function (b) {
            var rng = clampRangeToView(b.startYmd, b.endYmd, viewDates);
            if (!rng) return;
            var cls = 'ltlb-hotel-rack__booking';
            if (b.status) cls += ' ltlb-fc-status-' + cssEscape(b.status);
            html += '<div class="' + esc(cls) + '" data-ltlb-appt-id="' + esc(b.id) + '" data-ltlb-status="' + esc(b.status) + '" data-ltlb-title="' + esc(b.title) + '" style="grid-column:' + esc(String(rng.startIdx + 2)) + ' / ' + esc(String(rng.endIdx + 2)) + '">' + esc(b.title) + '</div>';
          });

          html += '</div>';
        });
      });

      hotelRackEl.innerHTML = html;
      hotelRackEl.hidden = false;
    }

    function loadHotelRackRange(startStr, endStr) {
      if (!isHotelMode || !hotelRackEl || !window.wp || !wp.apiFetch) return;
      var key = String(startStr || '') + '|' + String(endStr || '');
      if (key === lastHotelRackKey && hotelRackDataCache) {
        renderHotelRack(listDatesExclusive(startStr, endStr), hotelRackDataCache);
        return;
      }
      lastHotelRackKey = key;

      Promise.all([
        fetchHotelRooms(),
        fetchHotelEvents(startStr, endStr)
      ]).then(function (res) {
        var rooms = res[0] || [];
        var events = res[1] || [];
        hotelRackDataCache = buildHotelRackModel(rooms, events);
        renderHotelRack(listDatesExclusive(startStr, endStr), hotelRackDataCache);
		updateHotelTitleOccupancy(startStr, endStr);
      }).catch(function () {
        // Keep it silent; the calendar header still works.
        hotelRackEl.innerHTML = '<div class="ltlb-muted" style="padding:10px;">' + esc(t('could_not_load_rooms', 'Could not load rooms.')) + '</div>';
        hotelRackEl.hidden = false;
      });
    }

    // Toggle group collapse in the rack
    if (hotelRackEl) {
      hotelRackEl.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('.ltlb-hotel-rack__group-toggle') : null;
        if (!btn) return;
        var gk = fmt(btn.getAttribute('data-ltlb-group'));
        if (!gk) return;
        hotelRackCollapsed[gk] = !hotelRackCollapsed[gk];
        if (hotelRackDataCache) {
          // Re-render using last known range
          renderHotelRack(listDatesExclusive(lastHotelRackKey.split('|')[0] || '', lastHotelRackKey.split('|')[1] || ''), hotelRackDataCache);
        }
      });

    hotelRackEl.addEventListener('click', function (e) {
      var card = e.target && e.target.closest ? e.target.closest('.ltlb-hotel-rack__booking[data-ltlb-appt-id]') : null;
      if (!card) return;
      var id = fmt(card.getAttribute('data-ltlb-appt-id'));
      if (!id) return;

      // Visual selection
      try {
        var prev = hotelRackEl.querySelectorAll('.ltlb-hotel-rack__booking--selected');
        for (var i = 0; i < prev.length; i++) prev[i].classList.remove('ltlb-hotel-rack__booking--selected');
        card.classList.add('ltlb-hotel-rack__booking--selected');
      } catch (e1) {
        // ignore
      }

      clearDetails();
      announce(t('loading_details', 'Loading appointment details…'), { assertive: false });

      var evLite = {
        id: id,
        title: fmt(card.getAttribute('data-ltlb-title')),
        extendedProps: { status: fmt(card.getAttribute('data-ltlb-status')) }
      };

      wp.apiFetch({
        path: restPath('/ltlb/v1/admin/appointments/' + encodeURIComponent(id))
      }).then(function (payload) {
        renderDetails(evLite, payload);
        announce(t('details_loaded', 'Appointment details loaded.'), { assertive: false });
      }).catch(function () {
        announce(t('could_not_load_details', 'Could not load appointment details.'), { assertive: true });
        setDetails('<div class="notice notice-error"><p>' + esc(t('could_not_load_details', 'Could not load appointment details.')) + '</p></div>');
      });
    });
    }
    var currentViewType = '';

    // Show calendar and hide loading spinner
    var loading = qs('ltlb-admin-calendar-loading');
    if (loading) {
      setTimeout(function() {
        loading.style.display = 'none';
        container.hidden = false;
      }, 100);
    } else {
      container.hidden = false;
    }

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

      if (isHotelMode) {
        html += '<div class="ltlb-calendar-details__section ltlb-room-assistant" id="ltlb-room-assistant" data-ltlb-appt-id="' + esc(ev.id) + '">';
        html += '<div class="ltlb-calendar-details__section-title">' + esc(t('room_assignment', 'Room Assignment')) + '</div>';
        html += '<div class="ltlb-muted">' + esc(t('loading_room', 'Loading room suggestions…')) + '</div>';
        html += '</div>';
      }

      setDetails(html);

      if (isHotelMode) {
        loadRoomAssistant(ev.id);
      }
    }

    function loadOccupancyRange(startStr, endStr) {
      if (!isHotelMode) return;

      var key = String(startStr || '') + '|' + String(endStr || '');
      if (key === lastOccupancyKey) {
        applyOccupancyBadges();
        return;
      }
      lastOccupancyKey = key;

      wp.apiFetch({
        path: restPath('/ltlb/v1/admin/calendar/occupancy?start=' + encodeURIComponent(startStr) + '&end=' + encodeURIComponent(endStr))
      }).then(function (res) {
        occupancyByDate = {};
        if (res && res.days && Array.isArray(res.days)) {
          res.days.forEach(function (d) {
            if (d && d.date) occupancyByDate[String(d.date)] = d;
          });
        }
        applyOccupancyBadges();
		// Also refresh the hotel rack header (it shows occupancy per day).
		if (hotelRackDataCache) {
			renderHotelRack(listDatesExclusive(startStr, endStr), hotelRackDataCache);
		}
		updateHotelTitleOccupancy(startStr, endStr);
      }).catch(function () {
        // Silent failure; overlay is optional.
      });
    }

    function applyOccupancyBadges() {
      if (!isHotelMode) return;

      var root = container;
      if (!root) return;

      // FullCalendar re-renders parts of the DOM between view changes and date navigations.
      // Remove any existing badges first to avoid duplicates.
      try {
        var existing = root.querySelectorAll('.ltlb-occupancy-badge');
        for (var i = 0; i < existing.length; i++) {
          if (existing[i] && existing[i].parentNode) existing[i].parentNode.removeChild(existing[i]);
        }
      } catch (e) {
        // ignore
      }

      var isDayGrid = String(currentViewType || '').indexOf('dayGrid') === 0;

      Object.keys(occupancyByDate || {}).forEach(function (date) {
        var d = occupancyByDate[date];
        if (!d) return;

        var rate = (d.rate === null || d.rate === undefined) ? '' : String(d.rate);
        var occupied = (d.occupied === null || d.occupied === undefined) ? '' : String(d.occupied);
        var total = (d.total === null || d.total === undefined) ? '' : String(d.total);

        var title = t('occupancy', 'Occupancy') + ': ' + occupied + '/' + total + ' ' + t('rooms', 'Rooms');

        if (isDayGrid) {
          // Month grid cells (dayGridMonth)
          var dayCell = root.querySelector('.fc-daygrid-day[data-date="' + cssEscape(date) + '"]');
          if (dayCell) {
            var top = dayCell.querySelector('.fc-daygrid-day-top') || dayCell;
            var badge = document.createElement('span');
            badge.className = 'ltlb-occupancy-badge';
            badge.textContent = rate + '%';
            badge.setAttribute('title', title);
            top.appendChild(badge);
          }
          return;
        }

        // Week/day header cells (timeGridWeek/timeGridDay)
        var headerCell = root.querySelector('.fc-col-header-cell[data-date="' + cssEscape(date) + '"]');
        if (headerCell) {
          var cushion = headerCell.querySelector('.fc-col-header-cell-cushion') || headerCell;
          var badge2 = document.createElement('span');
          badge2.className = 'ltlb-occupancy-badge';
          badge2.textContent = rate + '%';
          badge2.setAttribute('title', title);
          cushion.appendChild(badge2);
        }
      });
    }

    function renderRoomAssistant(el, data) {
      if (!el) return;
      var assignedName = (data && data.assigned && data.assigned.name) ? String(data.assigned.name) : '';
      var assignedId = data && data.assigned_id ? String(data.assigned_id) : '';
      var bestId = data && data.best_id ? String(data.best_id) : '';
      var candidates = (data && Array.isArray(data.candidates)) ? data.candidates : [];

      var html = '';
      html += '<div class="ltlb-room-assistant__grid">';

      html += '<div>';
      html += '<div class="ltlb-muted">' + esc(t('assigned_room', 'Assigned room')) + '</div>';
      html += '<div>' + esc(assignedName || t('unassigned', 'Unassigned')) + '</div>';
      html += '</div>';

      html += '<div>';
      html += '<div class="ltlb-muted">' + esc(t('suggested_room', 'Suggested room')) + '</div>';
      var best = candidates.length ? candidates[0] : null;
      html += '<div>' + esc(best && best.name ? best.name : '') + '</div>';
      html += '</div>';

      html += '</div>';

      html += '<div class="ltlb-inline" style="margin-top:10px;">';
      html += '<label class="ltlb-muted" for="ltlb-room-select" style="margin-right:6px;">' + esc(t('choose_room', 'Choose room')) + '</label>';
      html += '<select id="ltlb-room-select" data-ltlb-field="room" data-ltlb-id="' + esc(String(data.appointment_id || '')) + '">';
      html += '<option value="">' + esc(t('choose_room', 'Choose room')) + '</option>';
      candidates.forEach(function (r) {
        var rid = String(r.id);
        var sel = '';
        if (assignedId && rid === assignedId) sel = ' selected';
        if (!assignedId && bestId && rid === bestId) sel = ' selected';
        var label = String(r.name || '') + ' (' + String(r.available) + '/' + String(r.capacity) + ')';
        html += '<option value="' + esc(rid) + '"' + sel + '>' + esc(label) + '</option>';
      });
      html += '</select>';
      html += '</div>';

      html += '<div class="ltlb-calendar-details__actions" style="margin-top:10px;">';
      html += '<button type="button" class="button" data-ltlb-action="assign-room" data-ltlb-id="' + esc(String(data.appointment_id || '')) + '">' + esc(t('assign_room', 'Assign room')) + '</button>';
      html += '<button type="button" class="button" data-ltlb-action="propose-room" data-ltlb-id="' + esc(String(data.appointment_id || '')) + '">' + esc(t('propose_room', 'Propose via Outbox')) + '</button>';
      html += '</div>';

      el.innerHTML = html;
    }

    function loadRoomAssistant(appointmentId) {
      if (!isHotelMode) return;
      var el = qs('ltlb-room-assistant');
      if (!el) return;

      el.innerHTML = '<div class="ltlb-muted">' + esc(t('loading_room', 'Loading room suggestions…')) + '</div>';

      wp.apiFetch({
        path: restPath('/ltlb/v1/admin/appointments/' + encodeURIComponent(appointmentId) + '/room-suggestions')
      }).then(function (res) {
        if (!res || res.ok === false) {
          el.innerHTML = '<div class="notice notice-error"><p>' + esc(t('could_not_load_room', 'Could not load room suggestions.')) + '</p></div>';
          return;
        }
        renderRoomAssistant(el, res);
      }).catch(function () {
        el.innerHTML = '<div class="notice notice-error"><p>' + esc(t('could_not_load_room', 'Could not load room suggestions.')) + '</p></div>';
      });
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

      datesSet: function (info) {
        try {
          currentViewType = info && info.view ? info.view.type : currentViewType;
        } catch (e) {
          // ignore
        }

        // Occupancy overlay (hotel mode only)
        try {
          loadOccupancyRange(info.startStr, info.endStr);
        } catch (e2) {
          // ignore
        }

        // Hotel room-rack (connected to the same visible range)
        try {
          loadHotelRackRange(info.startStr, info.endStr);
        } catch (e3) {
          // ignore
        }
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
          info.el.setAttribute('title', [
            title,
            st ? (t('status', 'Status') + ': ' + st) : '',
            start ? (t('start', 'Start') + ': ' + start) : '',
            end ? (t('end', 'End') + ': ' + end) : ''
          ].filter(Boolean).join('\n'));
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
      announce(t('could_not_update_appointment', 'Could not update appointment.'), { assertive: true });
			alert(t('could_not_update_appointment', 'Could not update appointment.'));
            return;
          }
          announce(t('appointment_updated', 'Appointment updated.'), { assertive: false });
        }).catch(function (err) {
          info.revert();
		  if (err && err.data && err.data.error === 'conflict') {
    announce(t('conflict_message', 'This time slot conflicts with an existing booking.'), { assertive: true });
		alert(t('conflict_message', 'This time slot conflicts with an existing booking.'));
			return;
		  }
      announce(t('could_not_update_appointment', 'Could not update appointment.'), { assertive: true });
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
      announce(t('could_not_update_appointment', 'Could not update appointment.'), { assertive: true });
			alert(t('could_not_update_appointment', 'Could not update appointment.'));
            return;
          }
          announce(t('appointment_updated', 'Appointment updated.'), { assertive: false });
        }).catch(function (err) {
          info.revert();
		  if (err && err.data && err.data.error === 'conflict') {
    announce(t('conflict_message', 'This time slot conflicts with an existing booking.'), { assertive: true });
		alert(t('conflict_message', 'This time slot conflicts with an existing booking.'));
			return;
		  }
      announce(t('could_not_update_appointment', 'Could not update appointment.'), { assertive: true });
		  alert(t('could_not_update_appointment', 'Could not update appointment.'));
        });
      },
      eventClick: function (info) {
        var ev = info.event;
        clearDetails();

        announce(t('loading_details', 'Loading appointment details…'), { assertive: false });

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
		  announce(t('details_loaded', 'Appointment details loaded.'), { assertive: false });
        }).catch(function () {
          announce(t('could_not_load_details', 'Could not load appointment details.'), { assertive: true });
          setDetails('<div class="notice notice-error"><p>' + esc(t('could_not_load_details', 'Could not load appointment details.')) + '</p></div>');
        });
      },
    });

    calendar.render();

    // In hotel mode, render the room-rack inside the same calendar panel container,
    // so the layout stays compact and visually consistent.
    if (isHotelMode && hotelRackEl && hotelRackEl.parentNode !== container) {
      try {
        container.appendChild(hotelRackEl);
      } catch (e) {
        // ignore
      }
    }

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
    			announce(t('appointment_deleted', 'Appointment deleted.'), { assertive: false });
          } else {
    			announce(t('could_not_delete_appointment', 'Could not delete appointment.'), { assertive: true });
            alert(t('could_not_delete_appointment', 'Could not delete appointment.'));
          }
        }).catch(function () {
    		  announce(t('could_not_delete_appointment', 'Could not delete appointment.'), { assertive: true });
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
    			announce(t('status_updated', 'Status updated.'), { assertive: false });
          } else {
    			announce(t('could_not_update_status', 'Could not update status.'), { assertive: true });
            alert(t('could_not_update_status', 'Could not update status.'));
          }
        }).catch(function () {
    		  announce(t('could_not_update_status', 'Could not update status.'), { assertive: true });
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
    			announce(t('customer_saved', 'Customer saved.'), { assertive: false });
            alert(t('customer_saved', 'Customer saved.'));
          } else {
    			announce(t('could_not_save_customer', 'Could not save customer.'), { assertive: true });
            alert(t('could_not_save_customer', 'Could not save customer.'));
          }
        }).catch(function () {
    		  announce(t('could_not_save_customer', 'Could not save customer.'), { assertive: true });
          alert(t('could_not_save_customer', 'Could not save customer.'));
        });
        return;
      }

      if (action === 'assign-room') {
        var apptId2 = target.getAttribute('data-ltlb-id');
        if (!apptId2) return;
        var sel2 = qs('ltlb-room-select');
        var rid2 = sel2 ? sel2.value : '';
        if (!rid2) return;

        wp.apiFetch({
          path: restPath('/ltlb/v1/admin/appointments/' + encodeURIComponent(apptId2) + '/assign-room'),
          method: 'POST',
          data: { resource_id: parseInt(rid2, 10) }
        }).then(function (res) {
          if (res && res.ok) {
            announce(t('room_assigned', 'Room assigned.'), { assertive: false });
            loadRoomAssistant(apptId2);
          } else {
            announce(t('could_not_assign_room', 'Could not assign room.'), { assertive: true });
            alert(t('could_not_assign_room', 'Could not assign room.'));
          }
        }).catch(function () {
          announce(t('could_not_assign_room', 'Could not assign room.'), { assertive: true });
          alert(t('could_not_assign_room', 'Could not assign room.'));
        });
        return;
      }

      if (action === 'propose-room') {
        var apptId3 = target.getAttribute('data-ltlb-id');
        if (!apptId3) return;
        var sel3 = qs('ltlb-room-select');
        var rid3 = sel3 ? sel3.value : '';

        wp.apiFetch({
          path: restPath('/ltlb/v1/admin/appointments/' + encodeURIComponent(apptId3) + '/propose-room'),
          method: 'POST',
          data: { resource_id: rid3 ? parseInt(rid3, 10) : 0 }
        }).then(function (res) {
          if (res && res.ok) {
            announce(t('room_proposed', 'Room proposal sent to Outbox.'), { assertive: false });
            alert(t('room_proposed', 'Room proposal sent to Outbox.'));
          } else {
            announce(t('could_not_propose_room', 'Could not propose room.'), { assertive: true });
            alert(t('could_not_propose_room', 'Could not propose room.'));
          }
        }).catch(function () {
          announce(t('could_not_propose_room', 'Could not propose room.'), { assertive: true });
          alert(t('could_not_propose_room', 'Could not propose room.'));
        });
        return;
      }
    });
  });
})();
