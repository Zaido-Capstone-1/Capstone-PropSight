// ── Hero banner live weather + precise location ──
// Uses the browser's geolocation API for an exact fix, then:
//   - Open-Meteo for current weather at those coordinates (no API key)
//   - Nominatim (OpenStreetMap) to reverse-geocode coordinates into a
//     real place name, same host already used for nearby-attractions.
// No hardcoded fallback location — if geolocation is denied/unavailable,
// both weather and location simply show as unavailable.
(function () {
    const elTemp = document.getElementById('hwTemp');
    const elDesc = document.getElementById('hwDesc');
    const elIcon = document.getElementById('hwIcon');
    const elLocation = document.getElementById('heroLocationText');

    if (!elTemp || !elDesc || !elIcon) return;

    // WMO weather code -> { label, icon }
    function weatherFromCode(code) {
        const ICONS = {
            sun: '<circle cx="12" cy="12" r="4.5"/><line x1="12" y1="1.5" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="22.5"/><line x1="4.2" y1="4.2" x2="5.9" y2="5.9"/><line x1="18.1" y1="18.1" x2="19.8" y2="19.8"/><line x1="1.5" y1="12" x2="4" y2="12"/><line x1="20" y1="12" x2="22.5" y2="12"/><line x1="4.2" y1="19.8" x2="5.9" y2="18.1"/><line x1="18.1" y1="5.9" x2="19.8" y2="4.2"/>',
            cloud: '<path d="M7 18a4.5 4.5 0 010-9 5.5 5.5 0 0110.7 1.7A4 4 0 0117 18H7z"/>',
            rain: '<path d="M7 16a4.5 4.5 0 010-9 5.5 5.5 0 0110.7 1.7A4 4 0 0117 16H7z"/><line x1="9" y1="19" x2="9" y2="21.5"/><line x1="13" y1="19" x2="13" y2="21.5"/><line x1="17" y1="19" x2="17" y2="21.5"/>',
            storm: '<path d="M7 16a4.5 4.5 0 010-9 5.5 5.5 0 0110.7 1.7A4 4 0 0117 16H7z"/><polyline points="13 18 10.5 22 14 22 11.5 26"/>'
        };
        const TABLE = {
            0: ['Clear sky', 'sun'], 1: ['Mostly clear', 'sun'], 2: ['Partly cloudy', 'cloud'], 3: ['Overcast', 'cloud'],
            45: ['Foggy', 'cloud'], 48: ['Foggy', 'cloud'],
            51: ['Light drizzle', 'rain'], 53: ['Drizzle', 'rain'], 55: ['Dense drizzle', 'rain'],
            61: ['Light rain', 'rain'], 63: ['Rain', 'rain'], 65: ['Heavy rain', 'rain'],
            80: ['Rain showers', 'rain'], 81: ['Rain showers', 'rain'], 82: ['Violent showers', 'rain'],
            95: ['Thunderstorm', 'storm'], 96: ['Thunderstorm', 'storm'], 99: ['Thunderstorm', 'storm']
        };
        const [label, key] = TABLE[code] || ['Tropical skies', 'sun'];
        return { label, paths: ICONS[key] };
    }

    function showWeatherUnavailable() {
        elTemp.textContent = '—';
        elDesc.textContent = 'Weather unavailable';
    }

    function showLocationUnavailable() {
        if (elLocation) elLocation.textContent = 'Location unavailable';
    }

    function loadWeather(lat, lon) {
        fetch(`https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current=temperature_2m,weather_code&timezone=auto`)
            .then(res => res.ok ? res.json() : Promise.reject(res.status))
            .then(data => {
                const cur = data && data.current;
                if (!cur) throw new Error('no current weather');
                const temp = Math.round(cur.temperature_2m);
                const { label, paths } = weatherFromCode(cur.weather_code);

                elTemp.textContent = `${temp}°C`;
                elDesc.textContent = label;
                elIcon.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">${paths}</svg>`;
            })
            .catch(showWeatherUnavailable);
    }

    function loadLocationLabel(lat, lon) {
        if (!elLocation) return;
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}&zoom=12&addressdetails=1`, {
            headers: { 'Accept-Language': 'en' }
        })
            .then(res => res.ok ? res.json() : Promise.reject(res.status))
            .then(data => {
                const a = data && data.address;
                if (!a) throw new Error('no address');
                const place = a.city || a.town || a.municipality || a.village || a.county || a.state;
                const country = a.country_code ? a.country_code.toUpperCase() : (a.country || '');
                if (place) {
                    elLocation.textContent = country ? `${place}, ${country}` : place;
                } else {
                    showLocationUnavailable();
                }
            })
            .catch(showLocationUnavailable);
    }

    if (!('geolocation' in navigator)) {
        showWeatherUnavailable();
        showLocationUnavailable();
        return;
    }

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            const { latitude, longitude } = pos.coords;
            loadWeather(latitude, longitude);
            loadLocationLabel(latitude, longitude);
        },
        () => {
            // permission denied / unavailable / timeout — show as unavailable, no fallback city
            showWeatherUnavailable();
            showLocationUnavailable();
        },
        { enableHighAccuracy: true, timeout: 8000, maximumAge: 10 * 60 * 1000 }
    );
})();