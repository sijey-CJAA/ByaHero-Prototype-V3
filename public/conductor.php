<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>ByaHero - Conductor Dashboard</title>

  <!-- Bootstrap CSS (mobile-first) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Leaflet CSS for the mini map -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

  <style>
    :root { --navbar-h:56px; }

    html, body { height:100%; margin:0; font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg,#eef2ff 0%,#f3f7ff 100%); }

    .page-wrapper { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:1rem; box-sizing:border-box; }
    .dashboard-card { width:100%; max-width:880px; border-radius:12px; overflow:hidden; box-shadow:0 10px 30px rgba(2,6,23,0.12); background:#fff; }

    .dashboard-header { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:#fff; padding:1.25rem 1.5rem; display:flex; gap:1rem; align-items:center; }
    .dashboard-header h1 { margin:0; font-size:1.15rem; font-weight:700; }
    .dashboard-header p { margin:0; opacity:0.92; font-size:.9rem; }

    .card-body { padding:1rem 1.25rem; }

    .form-row { display:flex; flex-wrap:wrap; gap:.75rem; margin-bottom:.75rem; }
    .form-group { flex:1 1 220px; min-width:180px; }

    label { font-weight:600; font-size:.95rem; margin-bottom:.25rem; display:block; }

    /* Status indicators */
    .status-indicator { padding:.7rem 0.9rem; border-radius:8px; margin-bottom:.75rem; font-weight:600; display:flex; align-items:center; gap:.5rem; }
    .status-inactive { background:#fff7ed; color:#92400e; border-left:4px solid #fb923c; }
    .status-active { background:#ecfdf5; color:#065f46; border-left:4px solid #10b981; }

    .info-box { background:#f8fafc; padding:.9rem; border-radius:8px; margin-top:.75rem; }
    .info-item { display:flex; justify-content:space-between; padding:.45rem 0; border-bottom:1px solid #eef2f6; font-size:.95rem; }
    .info-item:last-child { border-bottom:0; }

    /* Mini map */
    #miniMapWrap { margin-top:.75rem; border-radius:8px; overflow:hidden; border:1px solid #e6eefc; }
    #miniMap { width:100%; height:240px; display:block; }
    @media (max-width:560px) { #miniMap { height:200px; } }

    /* Seats control */
    .seat-control { display:flex; gap:.5rem; align-items:center; justify-content:center; max-width:260px; }
    .seat-btn { width:44px; height:44px; border-radius:8px; border:0; background:#f3f4f6; font-weight:700; font-size:1.1rem; display:inline-flex; align-items:center; justify-content:center; cursor:pointer; }
    .seat-count { min-width:64px; height:44px; display:flex; align-items:center; justify-content:center; border-radius:8px; background:#fff; border:1px solid #e8eefc; font-weight:700; font-size:1.05rem; }

    .routes-list { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.4rem; }
    .route-pill { padding:.35rem .6rem; border-radius:999px; background:#f3f4f6; border:1px solid #eef2ff; cursor:pointer; font-weight:600; font-size:.9rem; }
    .route-pill.active { background:linear-gradient(135deg,#667eea,#764ba2); color:#fff; border-color:transparent; }

    .visually-hidden { position:absolute !important; height:1px; width:1px; overflow:hidden; clip:rect(1px,1px,1px,1px); white-space:nowrap; }

    .btn-space { display:flex; gap:.6rem; flex-wrap:wrap; align-items:center; }

    /* small helper for alerts */
    .alert-placeholder { min-height:2rem; margin-bottom:.5rem; }
  </style>
</head>
<body>
  <div class="page-wrapper">
    <article class="dashboard-card" role="application" aria-labelledby="pageTitle">
      <header class="dashboard-header">
        <div style="font-size:1.6rem">🚌</div>
        <div>
          <h1 id="pageTitle">Conductor Dashboard</h1>
          <p>Manage your bus and share location with passengers</p>
        </div>
      </header>

      <div class="card-body">
        <div class="alert-placeholder" id="alertBox" aria-live="polite"></div>

        <!-- Setup -->
        <section id="setupSection" aria-labelledby="setupTitle">
          <div class="status-indicator status-inactive" id="setupStatus">⚠️ Please select your bus and configure route to start tracking</div>

          <div class="form-row">
            <div class="form-group">
              <label for="busSelect">Select Your Bus</label>
              <select id="busSelect" class="form-select" aria-label="Select bus">
                <option value="">-- Select Bus --</option>
              </select>
            </div>

            <div class="form-group">
              <label for="routeSelect">Fixed Routes</label>
              <select id="routeSelect" class="form-select" aria-label="Choose a fixed route (optional)">
                <option value="">Custom / Other...</option>
                <option value="LAUREL - TANAUAN">LAUREL - TANAUAN</option>
                <option value="TANAUAN - LAUREL">TANAUAN - LAUREL</option>
              </select>

              <label for="routeInput" class="mt-2">Or enter custom route name</label>
              <input id="routeInput" type="text" class="form-control" placeholder="e.g. LAUREL ⇄ MARKET" aria-label="Custom route name" />
            </div>
          </div>

          <div class="btn-space">
            <button class="btn btn-primary" id="startBtn" type="button">Start Tracking</button>
            <button class="btn btn-outline-secondary" id="prefillBtn" type="button">Refresh Buses</button>
          </div>
        </section>

        <!-- Tracking (hidden initially) -->
        <section id="trackingSection" style="display:none" aria-labelledby="trackingTitle">
          <div class="status-indicator status-active" id="trackingStatus">✅ Location tracking is inactive</div>

          <div class="form-row">
            <div class="form-group">
              <label for="statusSelect">Bus Status</label>
              <select id="statusSelect" class="form-select" aria-label="Bus status">
                <option value="available">Available</option>
                <option value="on_stop">On Stop</option>
                <option value="full">Full</option>
                <option value="unavailable">Unavailable</option>
              </select>
            </div>

            <div class="form-group">
              <label>Seats Available</label>
              <div class="seat-control" role="group" aria-label="Seats control">
                <button type="button" id="seatMinus" class="seat-btn" aria-label="Decrease seats">−</button>
                <div id="seatsCount" class="seat-count" role="status" aria-live="polite">25</div>
                <button type="button" id="seatPlus" class="seat-btn" aria-label="Increase seats">+</button>
              </div>
              <input id="seatsInput" type="number" class="visually-hidden" min="0" max="999" value="25" />
            </div>
          </div>

          <div id="miniMapWrap" aria-label="Your current location preview">
            <div id="miniMap" role="img" aria-label="Mini map showing your location"></div>
          </div>

          <div class="info-box" aria-live="polite">
            <div class="info-item"><div>Bus Code</div><div id="currentBusCode">-</div></div>
            <div class="info-item"><div>Route</div><div id="currentRoute">-</div></div>
            <div class="info-item"><div>Current Location</div><div id="currentLocation">-</div></div>
            <div class="info-item"><div>Last Update</div><div id="lastUpdate">-</div></div>
          </div>

          <div class="btn-space mt-3">
            <button class="btn btn-danger" id="stopBtn" type="button">Stop Tracking</button>
            <button class="btn btn-outline-secondary" id="sendNowBtn" type="button">Send Location Now</button>
          </div>
        </section>
      </div>
    </article>
  </div>

  <!-- Leaflet JS for mini map -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <!-- Bootstrap JS bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // Conductor client script (Bootstrap + Leaflet friendly)
    let trackingInterval = null;
    let currentBus = null;
    let currentPosition = null;
    let routeFeatures = [];

    // Mini map state
    let miniMap = null;
    let miniMarker = null;
    let miniMapHasCentered = false;

    // Cached DOM nodes
    const el = (id) => document.getElementById(id);
    const alertBox = el('alertBox');

    // Load route polygons/features for point-in-polygon name resolution
    async function loadRouteFeatures() {
      try {
        const res = await fetch('/map_data.php', { cache: 'no-store' });
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

    // Ray-casting algorithm for point-in-polygon
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
          if (pointInRing(lng, lat, rings[i])) return false; // inside hole -> outside
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

    // Load available buses into the select dropdown
    async function loadBuses() {
      try {
        const r = await fetch('/api.php?action=get_buses', { cache: 'no-store' });
        const json = await r.json();
        if (json && Array.isArray(json.buses)) {
          const sel = el('busSelect');
          // remove existing options except the placeholder
          [...sel.options].forEach(o => { if (o.value !== '') o.remove(); });
          json.buses.forEach(b => {
            const o = document.createElement('option');
            o.value = b.id;
            o.textContent = `${b.code} (${b.seats_total ?? 'N/A'} seats)`;
            o.dataset.code = b.code ?? `BUS-${b.id}`;
            o.dataset.route = b.route ?? '';
            sel.appendChild(o);
          });
        }
      } catch (e) {
        console.error('loadBuses error', e);
        showAlert('Unable to load buses', 'warning');
      }
    }

    // Initialize mini map
    function initMiniMap() {
      if (miniMap) return;
      try {
        miniMap = L.map('miniMap', { attributionControl: false, zoomControl: false }).setView([14.5995, 120.9842], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(miniMap);
      } catch (e) {
        console.warn('Leaflet init failed', e);
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
      // small ensure render on some mobile devices
      setTimeout(() => miniMap.invalidateSize(), 150);
    }

    // Controls: seats
    function getSeatsValue() {
      return parseInt(el('seatsCount').textContent || '0', 10);
    }
    function setSeatsValue(v) {
      const n = Math.max(0, Math.min(999, Number(v) || 0));
      el('seatsCount').textContent = n;
      el('seatsInput').value = n;
    }

    // Start tracking workflow
    async function startTracking() {
      await loadRouteFeatures(); // ensure polygons loaded
      const busId = el('busSelect').value;
      const fixed = el('routeSelect').value || '';
      const custom = el('routeInput').value.trim();
      const route = custom || fixed;
      if (!busId) { showAlert('Please select a bus', 'danger'); return; }
      if (!route) { showAlert('Please choose or enter a route', 'danger'); return; }
      if (!navigator.geolocation) { showAlert('Geolocation not supported by this browser', 'danger'); return; }

      const sel = el('busSelect');
      const selectedOption = sel.options[sel.selectedIndex];

      currentBus = { id: busId, code: selectedOption.dataset.code || ('BUS-' + busId), route };

      el('currentBusCode').textContent = currentBus.code;
      el('currentRoute').textContent = currentBus.route;

      el('setupSection').style.display = 'none';
      el('trackingSection').style.display = 'block';
      el('trackingStatus').textContent = '🔄 Location tracking is active';

      initMiniMap();
      // immediate send then interval
      updateLocation();
      trackingInterval = setInterval(updateLocation, 3000);

      showAlert('Tracking started', 'success');
    }

    // Stop tracking workflow
    function stopTracking() {
      if (trackingInterval) { clearInterval(trackingInterval); trackingInterval = null; }

      if (currentBus && currentBus.id) {
        fetch('/api.php?action=stop_tracking', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({ bus_id: currentBus.id })
        }).then(r=>r.json()).then(res => {
          if (!res || !res.success) showAlert('Failed to notify server when stopping', 'warning');
          else showAlert('Stopped tracking and notified server', 'success');
        }).catch(err => {
          console.error(err);
          showAlert('Error notifying server when stopping', 'warning');
        });
      } else {
        showAlert('Stopped tracking', 'info');
      }

      // reset UI
      currentBus = null;
      currentPosition = null;
      el('setupSection').style.display = '';
      el('trackingSection').style.display = 'none';
      el('routeInput').value = '';
      el('routeSelect').value = '';
      el('currentBusCode').textContent = '-';
      el('currentRoute').textContent = '-';
      el('currentLocation').textContent = '-';
      el('lastUpdate').textContent = '-';
      el('trackingStatus').textContent = '✅ Location tracking is inactive';

      // remove mini marker
      if (miniMarker && miniMap) {
        miniMap.removeLayer(miniMarker);
        miniMarker = null;
        miniMapHasCentered = false;
      }
    }

    // Trigger a single immediate location send
    function sendLocationNow() {
      if (!currentBus) { showAlert('Start tracking first', 'warning'); return; }
      updateLocation();
    }

    // Main location update: reads geolocation, resolves friendly location, sends to server
    function updateLocation() {
      if (!navigator.geolocation) { showAlert('Geolocation not available', 'danger'); return; }
      navigator.geolocation.getCurrentPosition(
        async (pos) => {
          currentPosition = { lat: pos.coords.latitude, lng: pos.coords.longitude };

          const locationName = findLocationNameForPoint(currentPosition.lat, currentPosition.lng) || `${currentPosition.lat.toFixed(6)}, ${currentPosition.lng.toFixed(6)}`;

          el('currentLocation').textContent = locationName;
          el('lastUpdate').textContent = new Date().toLocaleTimeString();

          const seatsAvailable = getSeatsValue();
          const status = el('statusSelect').value;

          updateMiniMarker(currentPosition.lat, currentPosition.lng);

          const geojsonFeature = {
            type: "Feature",
            geometry: { type: "Point", coordinates: [ currentPosition.lng, currentPosition.lat ] },
            properties: {
              bus_id: parseInt(currentBus.id, 10),
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
            if (!res || !res.success) console.error('GeoJSON update error', res);
          }).catch(err => console.error('GeoJSON send error', err));

          // Also update legacy API endpoint
          fetch('/api.php?action=update_location', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ bus_id: currentBus.id, geojson: geojsonFeature, route: currentBus.route, seats_available: seatsAvailable, status })
          }).then(r => r.json()).then(res => {
            if (!res || !res.success) console.error('Legacy update failed', res);
          }).catch(err => console.error(err));
        },
        (err) => {
          console.error('Geolocation error', err);
          showAlert('Unable to get location. Check permissions.', 'danger');
        },
        { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
      );
    }

    // UI interactions
    function showAlert(message, type='info') {
      // type: success, danger, warning, info
      const bsType = (type === 'danger' || type === 'error') ? 'danger' : (type === 'success' ? 'success' : (type === 'warning' ? 'warning' : 'info'));
      alertBox.innerHTML = `<div class="alert alert-${bsType} py-2 mb-0" role="alert">${message}</div>`;
      setTimeout(()=> { if (alertBox) alertBox.innerHTML = ''; }, 4500);
    }

    // Event wiring
    document.addEventListener('DOMContentLoaded', () => {
      // Buttons
      el('startBtn').addEventListener('click', startTracking);
      el('stopBtn').addEventListener('click', stopTracking);
      el('sendNowBtn').addEventListener('click', sendLocationNow);
      el('prefillBtn').addEventListener('click', () => { loadBuses().then(()=>showAlert('Bus list refreshed', 'success')); });

      // Seats control
      el('seatPlus').addEventListener('click', () => { setSeatsValue(getSeatsValue() + 1); if (currentBus) updateLocation(); });
      el('seatMinus').addEventListener('click', () => { setSeatsValue(getSeatsValue() - 1); if (currentBus) updateLocation(); });

      // Accessibility: arrow up/down for seat count
      const seatsCountEl = el('seatsCount');
      seatsCountEl.tabIndex = 0;
      seatsCountEl.addEventListener('keydown', (ev) => {
        if (ev.key === 'ArrowUp') { setSeatsValue(getSeatsValue() + 1); if (currentBus) updateLocation(); ev.preventDefault(); }
        if (ev.key === 'ArrowDown') { setSeatsValue(getSeatsValue() - 1); if (currentBus) updateLocation(); ev.preventDefault(); }
      });

      // Route select behavior: pick fixed route -> populate text input and disable it
      el('routeSelect').addEventListener('change', () => {
        const v = el('routeSelect').value || '';
        if (v) {
          el('routeInput').value = v;
          el('routeInput').setAttribute('disabled', 'disabled');
        } else {
          el('routeInput').removeAttribute('disabled');
          el('routeInput').value = '';
          el('routeInput').focus();
        }
      });

      // Status change should trigger immediate update when tracking
      el('statusSelect').addEventListener('change', () => { if (currentBus) updateLocation(); });

      // Ensure map reflow on orientation/resizes
      window.addEventListener('orientationchange', () => setTimeout(() => { if (miniMap) miniMap.invalidateSize(); }, 300));
      window.addEventListener('resize', () => setTimeout(() => { if (miniMap) miniMap.invalidateSize(); }, 250));

      // initial setup
      setSeatsValue(25);
      loadRouteFeatures().catch(()=>{});
      loadBuses().catch(()=>{});
    });
  </script>
</body>
</html>