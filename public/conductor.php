<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>ByaHero - Conductor Dashboard</title>

  <!-- Leaflet CSS for the mini map -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

  <style>
    /* same styling as before (kept compact here) */
    *{box-sizing:border-box}
    body{font-family:Segoe UI, Tahoma, Geneva, Verdana, sans-serif;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh;padding:2rem}
    .container{max-width:760px;margin:0 auto;background:#fff;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.2);overflow:hidden}
    .header{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;padding:1.5rem 2rem;display:flex;gap:1rem;align-items:center}
    .content{padding:1.5rem 2rem}
    .form-row{display:flex;gap:1rem;flex-wrap:wrap}
    .form-group{flex:1;min-width:220px;margin-bottom:1rem}
    label{display:block;font-weight:600;margin-bottom:.35rem}
    input,select{width:100%;padding:.6rem;border:2px solid #e5e7eb;border-radius:6px}
    .button-group{display:flex;gap:1rem;margin-top:.75rem}
    .btn{padding:.6rem 1rem;border-radius:6px;border:0;font-weight:600;cursor:pointer}
    .btn-primary{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff}
    .btn-danger{background:#ef4444;color:#fff}
    .status-indicator{padding:.8rem;border-radius:6px;margin-bottom:1rem}
    .status-inactive{background:#fef3c7;color:#92400e;border-left:4px solid #f59e0b}
    .status-active{background:#d1fae5;color:#065f46;border-left:4px solid #10b981}
    .info-box{background:#f3f4f6;padding:1rem;border-radius:6px;margin-top:1rem}
    .info-item{display:flex;justify-content:space-between;padding:.5rem 0;border-bottom:1px solid #e5e7eb}
    #trackingSection{display:none}
    .alert{padding:.8rem;border-radius:6px;margin-bottom:1rem}

    /* Mini map styles */
    #miniMapWrap { margin-top:0.75rem; border:1px solid #e5e7eb; border-radius:6px; overflow:hidden; }
    #miniMap { width:100%; height:240px; display:block; }

    @media (max-width:560px) {
      #miniMap { height:200px; }
    }

    /* Seats control: plus/minus with centered number */
    .seat-control {
      display:flex;
      align-items:center;
      justify-content:center;
      gap:.5rem;
      width:100%;
      max-width:220px;
      margin-top:.25rem;
    }
    .seat-btn {
      width:42px;
      height:42px;
      border-radius:8px;
      border:0;
      background:#f3f4f6;
      font-weight:700;
      cursor:pointer;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      font-size:1.15rem;
      color:#111;
      box-shadow:0 1px 0 rgba(0,0,0,0.03);
    }
    .seat-count {
      min-width:56px;
      height:42px;
      display:flex;
      align-items:center;
      justify-content:center;
      border-radius:8px;
      background:#fff;
      border:2px solid #e5e7eb;
      font-weight:700;
      font-size:1.05rem;
    }
    .seat-label { display:block; margin-top:.35rem; color:#6b7280; font-size:.85rem; }

    /* small helper for visually-hidden input (keeps compatibility) */
    .visually-hidden { position:absolute !important; height:1px; width:1px; overflow:hidden; clip:rect(1px,1px,1px,1px); white-space:nowrap; }

    /* Fixed-routes pill list */
    .routes-list { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.5rem; }
    .route-pill {
      padding:.35rem .6rem;
      border-radius:999px;
      background:#f3f4f6;
      border:1px solid #e5e7eb;
      cursor:pointer;
      font-weight:600;
      color:#111;
      font-size:.9rem;
    }
    .route-pill.active { background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; border-color:transparent; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div style="font-size:1.6rem">🚌</div>
      <div>
        <h1>Conductor Dashboard</h1>
        <p>Manage your bus and share location with passengers</p>
      </div>
    </div>

    <div class="content">
      <div id="alertBox"></div>

      <div id="setupSection">
        <div class="status-indicator status-inactive">⚠️ Please select your bus and configure route to start tracking</div>

        <div class="form-row">
          <div class="form-group">
            <label for="busSelect">Select Your Bus</label>
            <select id="busSelect">
              <option value="">-- Select Bus --</option>
            </select>
          </div>

          <div class="form-group">
            <label for="routeInput">Route Name</label>
            <!-- Fixed routes selector -->
            <div>
              <select id="routeInput" aria-label="Choose a fixed route or choose 'Custom'">
                <option value="">Custom / Other...</option>
                <option value="LAUREL - TANAUAN">LAUREL - TANAUAN</option>
                <option value="TANAUAN - LAUREL">TANAUAN - LAUREL</option>
              </select>
            </div>

            <small>Pick a fixed route above or enter a custom route name</small>
          </div>
        </div>

        <div class="button-group">
          <button class="btn btn-primary" onclick="startTracking()">Start Tracking</button>
        </div>
      </div>

      <div id="trackingSection">
        <div class="status-indicator status-active">✅ Location tracking is active</div>

        <div class="form-row">
          <div class="form-group">
            <label for="statusSelect">Bus Status</label>
            <select id="statusSelect" onchange="updateStatus()">
              <option value="available">Available</option>
              <option value="on_stop">On Stop</option>
              <option value="full">Full</option>
              <option value="unavailable">Unavailable</option>
            </select>
          </div>

          <div class="form-group" aria-label="Seats available control">
            <label>Seats Available</label>

            <!-- Seat control (plus/minus with centered number) -->
            <div class="seat-control" role="group" aria-label="Seats control">
              <button type="button" id="seatMinus" class="seat-btn" aria-label="Decrease seats">−</button>
              <div id="seatsCount" class="seat-count" role="status" aria-live="polite">25</div>
              <button type="button" id="seatPlus" class="seat-btn" aria-label="Increase seats">+</button>
            </div>

            <!-- hidden input kept for compatibility with code that expects an element -->
            <input id="seatsInput" type="number" class="visually-hidden" min="0" max="100" value="25" />
          </div>
        </div>

        <!-- Mini map for conductor to preview own location -->
        <div id="miniMapWrap" aria-label="Your current location preview">
          <div id="miniMap"></div>
        </div>

        <div class="info-box">
          <h3>Current Location Info</h3>
          <div class="info-item"><strong>Bus Code:</strong><span id="currentBusCode">-</span></div>
          <div class="info-item"><strong>Route:</strong><span id="currentRoute">-</span></div>
          <div class="info-item"><strong>Current Location:</strong><span id="currentLocation">-</span></div>
          <div class="info-item"><strong>Last Update:</strong><span id="lastUpdate">-</span></div>
        </div>

        <div class="button-group" style="margin-top:1rem">
          <button class="btn btn-danger" onclick="stopTracking()">Stop Tracking</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Leaflet JS for mini map -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

  <script>
    // Conductor client script - calculates human name from route polygons and sends GeoJSON
    let trackingInterval = null;
    let currentBus = null;
    let currentPosition = null;
    let routeFeatures = [];

    // Leaflet mini map state
    let miniMap = null;
    let miniMarker = null;
    let miniMapHasCentered = false;

    // load polygons/features (routes) from /map_data.php to use for point-in-polygon
    async function loadRouteFeatures() {
      try {
        const res = await fetch('/map_data.php');
        const json = await res.json();
        if (json && Array.isArray(json.features)) {
          routeFeatures = json.features.filter(f => f.geometry && (f.geometry.type === 'Polygon' || f.geometry.type === 'MultiPolygon'));
        } else {
          routeFeatures = [];
        }
      } catch (e) {
        console.warn('Failed to load route features', e);
        routeFeatures = [];
      }
    }

    function getFeatureName(feature) {
      if (!feature || !feature.properties) return null;
      const p = feature.properties;
      if (p['Current Location']) return p['Current Location'];
      if (p.current_location_name) return p.current_location_name;
      if (p.name) return p.name;
      if (p.title) return p.title;
      const keys = Object.keys(p);
      if (keys.length === 1) {
        const v = p[keys[0]];
        if (typeof v === 'string' && v.trim() !== '') return v.trim();
        return keys[0];
      }
      return keys.length ? keys[0] : null;
    }

    // point-in-polygon helpers (rings are arrays of [lng, lat])
    function pointInRing(x, y, ring) {
      let inside = false;
      for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
        const xi = ring[i][0], yi = ring[i][1];
        const xj = ring[j][0], yj = ring[j][1];
        const intersect = ((yi > y) !== (yj > y)) && (x < (xj - xi) * (y - yi) / ((yj - yi) || 1) + xi);
        if (intersect) inside = !inside;
      }
      return inside;
    }

    function pointInFeature(lng, lat, feature) {
      if (!feature || !feature.geometry) return false;
      const g = feature.geometry;
      if (g.type === 'Polygon') {
        const rings = g.coordinates;
        if (!rings || rings.length === 0) return false;
        if (!pointInRing(lng, lat, rings[0])) return false;
        for (let i = 1; i < rings.length; i++) {
          if (pointInRing(lng, lat, rings[i])) return false; // inside hole -> not in polygon
        }
        return true;
      } else if (g.type === 'MultiPolygon') {
        for (const poly of g.coordinates) {
          if (poly && poly.length > 0) {
            if (pointInRing(lng, lat, poly[0])) {
              let inHole = false;
              for (let i = 1; i < poly.length; i++) if (pointInRing(lng, lat, poly[i])) { inHole = true; break; }
              if (!inHole) return true;
            }
          }
        }
        return false;
      }
      return false;
    }

    function findLocationNameForPoint(lat, lng) {
      if (!routeFeatures || routeFeatures.length === 0) return null;
      for (const f of routeFeatures) {
        if (pointInFeature(lng, lat, f)) {
          const name = getFeatureName(f);
          if (name) return name;
        }
      }
      return null;
    }

    // load buses into select
    async function loadBuses() {
      try {
        const r = await fetch('/api.php?action=get_buses');
        const json = await r.json();
        if (json && Array.isArray(json.buses)) {
          const sel = document.getElementById('busSelect');
          sel.querySelectorAll('option:not([value=""])').forEach(o => o.remove());
          json.buses.forEach(b => {
            const o = document.createElement('option');
            o.value = b.id;
            o.textContent = `${b.code} (${b.seats_total} seats)`;
            o.dataset.code = b.code;
            o.dataset.route = b.route || '';
            sel.appendChild(o);
          });
        }
      } catch (e) {
        console.error('loadBuses error', e);
      }
    }

    // Initialize mini map (called when tracking starts)
    function initMiniMap() {
      if (miniMap) return;
      try {
        miniMap = L.map('miniMap', { attributionControl: false, zoomControl: false }).setView([14.5995,120.9842], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(miniMap);
      } catch (e) {
        console.warn('Leaflet may not be available', e);
      }
    }

    function updateMiniMarker(lat, lng) {
      if (!miniMap) return;
      const latlng = [lat, lng];
      if (miniMarker) {
        miniMarker.setLatLng(latlng);
      } else {
        miniMarker = L.marker(latlng).addTo(miniMap);
      }
      if (!miniMapHasCentered) {
        miniMap.setView(latlng, 15);
        miniMapHasCentered = true;
      }
    }

    async function startTracking() {
      // ensure we have route polygons loaded (so we can resolve names)
      await loadRouteFeatures();

      const busId = document.getElementById('busSelect').value;
      // take route from the text input (routeInput) which may be prefilled by fixed-route selection
      const route = document.getElementById('routeInput').value.trim();
      if (!busId) { showAlert('Please select a bus','error'); return; }
      if (!route) { showAlert('Please enter a route name','error'); return; }
      if (!navigator.geolocation) { showAlert('Geolocation not supported','error'); return; }

      const sel = document.getElementById('busSelect');
      const selectedOption = sel.options[sel.selectedIndex];

      currentBus = { id: busId, code: selectedOption.dataset.code || ('BUS-' + busId), route };

      document.getElementById('currentBusCode').textContent = currentBus.code;
      document.getElementById('currentRoute').textContent = currentBus.route;
      document.getElementById('setupSection').style.display = 'none';
      document.getElementById('trackingSection').style.display = 'block';

      // initialize mini map and marker
      initMiniMap();

      trackingInterval = setInterval(updateLocation, 3000);
      updateLocation();
      showAlert('Tracking started successfully!', 'success');
    }

    function stopTracking() {
      if (trackingInterval) { clearInterval(trackingInterval); trackingInterval = null; }

      if (currentBus && currentBus.id) {
        fetch('/api.php?action=stop_tracking', {
          method: 'POST', headers: {'Content-Type':'application/json'},
          body: JSON.stringify({ bus_id: currentBus.id })
        }).then(r=>r.json()).then(res => {
          if (!res.success) showAlert('Failed to notify server when stopping tracking','error');
          else showAlert('Stopped tracking and notified server.','success');
        }).catch(err=>{ console.error(err); showAlert('Error notifying server when stopping tracking','error'); });
      } else showAlert('Tracking stopped','success');

      currentBus = null; currentPosition = null;
      document.getElementById('setupSection').style.display = 'block';
      document.getElementById('trackingSection').style.display = 'none';
      document.getElementById('routeInput').value = '';
      document.getElementById('routeSelect').value = '';
      document.getElementById('currentBusCode').textContent = '-';
      document.getElementById('currentRoute').textContent = '-';
      document.getElementById('currentLocation').textContent = '-';
      document.getElementById('lastUpdate').textContent = '-';

      // reset mini map marker
      if (miniMarker && miniMap) {
        miniMap.removeLayer(miniMarker);
        miniMarker = null;
        miniMapHasCentered = false;
      }
    }

    function getSeatsValue() {
      const el = document.getElementById('seatsCount');
      return parseInt(el.textContent || '0', 10);
    }
    function setSeatsValue(v) {
      const clamped = Math.max(0, Math.min(999, parseInt(v || 0, 10)));
      document.getElementById('seatsCount').textContent = clamped;
      document.getElementById('seatsInput').value = clamped; // keep hidden input in sync
    }

    // mount seat button handlers and route selection behavior
    document.addEventListener('DOMContentLoaded', () => {
      const plus = document.getElementById('seatPlus');
      const minus = document.getElementById('seatMinus');
      plus.addEventListener('click', () => { setSeatsValue(getSeatsValue() + 1); updateSeats(); });
      minus.addEventListener('click', () => { setSeatsValue(getSeatsValue() - 1); updateSeats(); });

      // keyboard accessibility: allow arrow up/down on seat-count
      const seatsCountEl = document.getElementById('seatsCount');
      seatsCountEl.tabIndex = 0;
      seatsCountEl.addEventListener('keydown', (ev) => {
        if (ev.key === 'ArrowUp') { setSeatsValue(getSeatsValue() + 1); updateSeats(); ev.preventDefault(); }
        if (ev.key === 'ArrowDown') { setSeatsValue(getSeatsValue() - 1); updateSeats(); ev.preventDefault(); }
      });

      // route select behavior: if a fixed route is chosen, populate and disable text input
      const routeSelect = document.getElementById('routeSelect');
      const routeInput = document.getElementById('routeInput');
      routeSelect.addEventListener('change', () => {
        const v = routeSelect.value || '';
        if (v) {
          routeInput.value = v;
          routeInput.setAttribute('disabled', 'disabled');
          // visually indicate selected fixed route (optional)
          routeSelect.classList.add('has-fixed-route');
        } else {
          routeInput.removeAttribute('disabled');
          routeInput.value = '';
          routeInput.focus();
          routeSelect.classList.remove('has-fixed-route');
        }
      });
    });

    function updateLocation() {
      navigator.geolocation.getCurrentPosition(
        async (pos) => {
          currentPosition = { lat: pos.coords.latitude, lng: pos.coords.longitude };

          // determine place name using polygons - returns null if no match
          const locationName = findLocationNameForPoint(currentPosition.lat, currentPosition.lng) || `${currentPosition.lat.toFixed(6)}, ${currentPosition.lng.toFixed(6)}`;

          document.getElementById('currentLocation').textContent = locationName;
          document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString();

          const seatsAvailable = getSeatsValue();
          const status = document.getElementById('statusSelect').value;

          // update mini map marker
          updateMiniMarker(currentPosition.lat, currentPosition.lng);

          // Build GeoJSON feature and include both friendly-name fields for compatibility:
          const geojsonFeature = {
            type: "Feature",
            geometry: { type: "Point", coordinates: [ currentPosition.lng, currentPosition.lat ] },
            properties: {
              bus_id: parseInt(currentBus.id),
              code: currentBus.code,
              route: currentBus.route,
              seats_available: seatsAvailable,
              status: status,
              timestamp: new Date().toISOString(),
              current_location_name: locationName,
              "Current Location": locationName
            }
          };

          // Send to new endpoint (stores file + DB)
          fetch('/update_geo_location.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ bus_id: currentBus.id, geojson: geojsonFeature, route: currentBus.route, seats_available: seatsAvailable, status })
          }).then(r => r.json()).then(res => {
            if (!res.success) console.error('GeoJSON update error', res);
          }).catch(err => console.error('GeoJSON send error', err));

          // Also update legacy API (api.php) which now accepts geojson
          fetch('/api.php?action=update_location', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ bus_id: currentBus.id, geojson: geojsonFeature, route: currentBus.route, seats_available: seatsAvailable, status })
          }).then(r=>r.json()).then(res => { if (!res.success) console.error('Legacy update failed', res); }).catch(err => console.error(err));
        },
        (err) => { console.error('Geolocation error', err); showAlert('Unable to get location. Please check permissions.', 'error'); },
        { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
      );
    }

    function updateStatus() { if (currentBus) updateLocation(); }
    function updateSeats() { if (currentBus) updateLocation(); }

    function showAlert(msg, type='success') {
      const box = document.getElementById('alertBox');
      const cls = type === 'error' ? 'alert-error' : 'alert-success';
      box.innerHTML = `<div class="${cls}" style="padding:.6rem;border-radius:6px">${msg}</div>`;
      setTimeout(()=>box.innerHTML='',5000);
    }

    // bootstrap (init)
    (async function init(){
      await loadRouteFeatures();
      await loadBuses();
      // ensure initial seat value in hidden input is set
      setSeatsValue(25);
      // ensure route select/default state: custom enabled
      document.getElementById('routeSelect').value = '';
      document.getElementById('routeInput').removeAttribute('disabled');
    })();
  </script>
</body>
</html>