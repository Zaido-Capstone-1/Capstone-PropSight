(function () {
    const DEFAULT_LAT = 11.9674, DEFAULT_LNG = 121.9248, DEFAULT_ZOOM = 13;

    const map = L.map('propertyMap', { scrollWheelZoom: true });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>',
      maxZoom: 19,
    }).addTo(map);

    function initMapView() {
      map.invalidateSize({ animate: false });
      map.setView([DEFAULT_LAT, DEFAULT_LNG], DEFAULT_ZOOM);
    }

    function scheduleInit() {
      requestAnimationFrame(function () {
        requestAnimationFrame(function () {
          initMapView();
        });
      });
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', scheduleInit);
    } else {
      scheduleInit();
    }

    if (typeof ResizeObserver !== 'undefined') {
      let initialized = false;
      const ro = new ResizeObserver(function (entries) {
        for (const entry of entries) {
          if (entry.contentRect.width > 0 && entry.contentRect.height > 0) {
            map.invalidateSize({ animate: false });
            if (!initialized) {
              map.setView([DEFAULT_LAT, DEFAULT_LNG], DEFAULT_ZOOM);
              initialized = true;
            }
          }
        }
      });
      ro.observe(document.getElementById('propertyMap'));
    }

    const pinIcon = L.divIcon({
      html: `<div style="background:#2563eb;width:12px;height:12px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:1px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,.2);display:flex;align-items:center;justify-content:center;">
      <svg viewBox="0 0 24 24" fill="#fff" style="width:4px;height:4px;transform:rotate(45deg)">
        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/>
      </svg></div>`,
      className: '', iconSize: [12, 12], iconAnchor: [6, 12], popupAnchor: [0, -16],
    });

    let marker = null;

    function placeMarker(lat, lng, label) {
      if (marker) {
        marker.setLatLng([lat, lng]);
      } else {
        marker = L.marker([lat, lng], { icon: pinIcon, draggable: true }).addTo(map);
        marker.on('dragend', function () {
          const p = marker.getLatLng();
          setCoords(p.lat, p.lng);
          reverseGeocode(p.lat, p.lng);
        });
      }
      if (label) marker.bindPopup(`<strong style="font-size:.85rem;">${label}</strong>`, { closeButton: false }).openPopup();
      setCoords(lat, lng);
    }

    function setCoords(lat, lng) {
      const la = parseFloat(lat).toFixed(6), lo = parseFloat(lng).toFixed(6);
      document.getElementById('fieldLat').value = la;
      document.getElementById('fieldLng').value = lo;
      document.getElementById('displayLat').textContent = la;
      document.getElementById('displayLng').textContent = lo;
    }

    map.on('click', function (e) {
      placeMarker(e.latlng.lat, e.latlng.lng, null);
      reverseGeocode(e.latlng.lat, e.latlng.lng);
    });

    function showAutofill() {
      const n = document.getElementById('autofillNotice');
      n.classList.add('visible');
      clearTimeout(n._t);
      n._t = setTimeout(() => n.classList.remove('visible'), 4000);
    }

    function fillFields(a) {
      const road = a.road || a.pedestrian || a.footway || a.path || '';
      const street = [a.house_number || '', road].filter(Boolean).join(' ');
      const city = a.city || a.town || a.village || a.municipality || a.county || '';
      const state = a.state || a.region || '';
      const zip = a.postcode || '';
      if (street) document.getElementById('fieldAddress').value = street;
      if (city) document.getElementById('fieldCity').value = city;
      if (state) document.getElementById('fieldState').value = state;
      if (zip) document.getElementById('fieldZip').value = zip;
      showAutofill();
    }

    async function reverseGeocode(lat, lng) {
      try {
        const r = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1`, { headers: { 'Accept-Language': 'en' } });
        const d = await r.json();
        if (!d || !d.address) return;
        fillFields(d.address);
        if (marker && d.display_name) {
          const lbl = d.display_name.split(',').slice(0, 2).join(',');
          marker.bindPopup(`<strong style="font-size:.85rem;">${lbl}</strong>`, { closeButton: false }).openPopup();
        }
      } catch (e) { console.warn('Reverse geocode error:', e); }
    }

    window.mapSearch = async function () {
      const q = document.getElementById('mapSearchInput').value.trim();
      if (!q) return;
      const btn = document.querySelectorAll('.map-search-btn')[0];
      const orig = btn.innerHTML;
      btn.innerHTML = '<svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:#fff;fill:none;stroke-width:2;animation:mapspin .7s linear infinite"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/></svg> Searching…';
      btn.disabled = true;
      try {
        const res = await fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&addressdetails=1&q=' + encodeURIComponent(q), { headers: { 'Accept-Language': 'en' } });
        const data = await res.json();
        if (data && data.length > 0) {
          const r = data[0];
          const lat = parseFloat(r.lat), lng = parseFloat(r.lon);
          map.setView([lat, lng], 16);
          const lbl = r.display_name.split(',').slice(0, 2).join(',');
          placeMarker(lat, lng, lbl);
          if (r.address) fillFields(r.address);
        } else {
          alert('Location not found. Try a more specific search.');
        }
      } catch (e) { alert('Search failed. Please check your connection.'); }
      btn.innerHTML = orig;
      btn.disabled = false;
    };

    window.mapGeolocate = function () {
      if (!navigator.geolocation) { alert('Geolocation not supported.'); return; }
      navigator.geolocation.getCurrentPosition(
        pos => { map.setView([pos.coords.latitude, pos.coords.longitude], 16); placeMarker(pos.coords.latitude, pos.coords.longitude, 'Your location'); reverseGeocode(pos.coords.latitude, pos.coords.longitude); },
        () => alert('Unable to get your location.')
      );
    };

    window.mapReset = function () {
      if (marker) { map.removeLayer(marker); marker = null; }
      map.setView([DEFAULT_LAT, DEFAULT_LNG], DEFAULT_ZOOM);
      document.getElementById('fieldLat').value = '';
      document.getElementById('fieldLng').value = '';
      document.getElementById('displayLat').textContent = 'Not set';
      document.getElementById('displayLng').textContent = 'Not set';
      document.getElementById('autofillNotice').classList.remove('visible');
    };

    /* Restore pin on re-submission with errors */
    const oldLat = window.__PS_ADD_PROPERTY__.oldLat;
    const oldLng = window.__PS_ADD_PROPERTY__.oldLng;
    if (!isNaN(oldLat) && !isNaN(oldLng) && oldLat !== 0) {
      setTimeout(function () {
        map.setView([oldLat, oldLng], 16);
        placeMarker(oldLat, oldLng, 'Saved pin');
      }, 200);
    }
  })();

  document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form[action*="add_property.php"]');
    if (!form) return;
    form.addEventListener('submit', function () {
      const btn = form.querySelector('button[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Saving...';
      }
    });
  });