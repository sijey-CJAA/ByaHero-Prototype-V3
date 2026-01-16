<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>ByaHero - Bus Tracker (Passenger View)</title>

  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

  <style>
    :root {
      --header-h: 64px;
      --sidebar-w: 340px;
      --bg: #2870ffff;
      --card-bg: #ffffff;
      --muted: #6b7280;
      --accent-start: #667eea;
      --accent-end: #764ba2;
    }

    * {
      box-sizing: border-box
    }

    html,
    body {
      height: 100%;
      margin: 0;
      font-family: Segoe UI, Tahoma, Geneva, Verdana, sans-serif;
      background: var(--bg);
      color: #111
    }

    /* Compact header to save vertical space */
    .header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: var(--header-h);
      padding: 0 20px;
      background: linear-gradient(135deg, var(--accent-start) 0%, var(--accent-end) 100%);
      color: #fff;
      box-shadow: 0 2px 8px rgba(16, 24, 40, 0.06);
    }

    .header .title {
      font-size: 1.25rem;
      font-weight: 700;
      margin: 0;
    }

    .header .subtitle {
      font-size: 0.85rem;
      opacity: 0.95;
    }

    .header .controls {
      display: flex;
      gap: 10px;
      align-items: center;
    }

    .btn {
      appearance: none;
      border: 0;
      background: #fff;
      color: #222;
      padding: 6px 10px;
      border-radius: 6px;
      font-size: 0.85rem;
      cursor: pointer;
      box-shadow: 0 1px 0 rgba(0, 0, 0, 0.03);
    }

    .btn:active {
      transform: translateY(1px)
    }

    .small-muted {
      font-size: 0.85rem;
      color: rgba(255, 255, 255, 0.9)
    }

    /* Layout */
    .app-body {
      display: flex;
      gap: 18px;
      padding: 16px;
      height: calc(100vh - var(--header-h) - 32px);
      /* 32px bottom margin */
      box-sizing: border-box;
    }

    #map {
      flex: 1;
      height: 100%;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 6px 18px rgba(2, 6, 23, 0.06);
      min-width: 200px;
    }

    .sidebar {
      width: var(--sidebar-w);
      max-width: 44%;
      background: var(--card-bg);
      padding: 16px;
      border-radius: 8px;
      box-shadow: 0 6px 18px rgba(2, 6, 23, 0.06);
      overflow: auto;
    }

    .section {
      margin-bottom: 14px;
    }

    h3 {
      margin: 0 0 8px 0;
      font-size: 1rem;
    }

    select,
    .filter-group {
      width: 100%;
      padding: 8px;
      font-size: 0.95rem;
      border-radius: 6px;
      border: 1px solid rgba(0, 0, 0, 0.06);
      background: #fff;
    }

    .legend {
      background: #fbfbfd;
      border-radius: 6px;
      padding: 12px;
      border: 1px solid rgba(0, 0, 0, 0.03);
    }

    .legend-row {
      display: flex;
      align-items: center;
      gap: 10px;
      margin: 8px 0;
      font-size: 0.95rem;
      color: var(--muted)
    }

    .dot {
      width: 14px;
      height: 14px;
      border-radius: 50%;
      display: inline-block;
    }

    .bus-list .bus-item {
      padding: 10px 8px;
      border-radius: 6px;
      background: transparent;
      margin-bottom: 8px;
      transition: background .12s ease;
      cursor: pointer;
      font-size: 0.95rem;
      border: 1px solid rgba(0, 0, 0, 0.03);
    }

    .bus-list .bus-item:hover {
      background: rgba(16, 24, 40, 0.03);
    }

    .meta {
      font-size: 0.85rem;
      color: var(--muted);
      display: block;
      margin-top: 4px;
    }

    .last-updated {
      font-size: 0.9rem;
      color: var(--muted);
      margin-top: 6px;
    }

    /* Make sure leaflet popups are readable */
    .leaflet-popup-content {
      font-size: 0.92rem;
      line-height: 1.25;
    }

    /* Responsive */
    @media (max-width: 1000px) {
      .app-body {
        flex-direction: column;
        height: auto;
        padding: 12px;
      }

      .sidebar {
        width: 100%;
        max-width: 100%;
        order: 2;
      }

      #map {
        height: 60vh;
        order: 1;
      }

      .header {
        padding: 0 12px;
      }
    }
  </style>
</head>

<body>

  <div class="header" role="banner" aria-label="ByaHero header">
    <div>
      <div class="title">ByaHero: Prototype V4</div>
      <div class="subtitle">Real-time bus tracking</div>
    </div>

    <div class="controls" role="region" aria-label="controls">
      <div class="small-muted">Last updated: <strong id="lastUpdateHeader">Never</strong></div>
      <button id="refreshBtn" class="btn" title="Refresh">Refresh</button>
    </div>
  </div>

  <div class="app-body">
    <div id="map" aria-label="Map showing buses"></div>

    <aside class="sidebar" role="complementary" aria-label="Sidebar with filters and list">
      <div class="section">
        <h3>Filter by Route</h3>
        <select id="routeFilter" aria-label="Route filter">
          <option value="">All Routes</option>
        </select>
      </div>

      <div class="section legend" aria-label="Bus status legend">
        <h3>Bus Status</h3>
        <div class="legend-row"><span class="dot" style="background:#10b981"></span> Available</div>
        <div class="legend-row"><span class="dot" style="background:#f59e0b"></span> On Stop</div>
        <div class="legend-row"><span class="dot" style="background:#ef4444"></span> Full</div>
        <div class="legend-row"><span class="dot" style="background:#6b7280"></span> Unavailable</div>
      </div>

      <div class="section bus-list" aria-label="Active buses">
        <h3>Active Buses (<span id="busCount">0</span>)</h3>
        <div id="busList" aria-live="polite"></div>
      </div>

      <div class="last-updated" aria-hidden="true">Last updated: <span id="lastUpdateFooter">Never</span></div>
    </aside>
  </div>

  <!-- Leaflet JS -->
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

  <script>
    // Map setup (unchanged core logic)
    const map = L.map('map', {
      zoomControl: true
    }).setView([14.5995, 120.9842], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors',
      maxZoom: 19
    }).addTo(map);

    const busMarkers = {};
    let selectedRoute = '';
    const statusColors = {
      available: '#10b981',
      on_stop: '#f59e0b',
      full: '#ef4444',
      unavailable: '#6b7280'
    };

    function createBusIcon(status) {
      const color = statusColors[status] || '#6b7280';
      // keep simple, high-contrast marker with thin white border
      return L.divIcon({
        html: `<div style="background:${color};width:30px;height:30px;border-radius:50%;border:3px solid #fff;box-shadow:0 1px 2px rgba(0,0,0,0.15)"></div>`,
        className: 'bus-marker',
        iconSize: [36, 36],
        iconAnchor: [18, 18]
      });
    }

    // Reusable parsing utilities (unchanged)
    function parseCurrentLocationField(bus) {
      if (!bus.current_location) return {
        coords: null,
        name: null
      };
      try {
        const gj = (typeof bus.current_location === 'string') ? JSON.parse(bus.current_location) : bus.current_location;
        if (!gj) return {
          coords: null,
          name: null
        };
        if (gj.type === 'Feature') {
          const props = gj.properties || {};
          const coords = gj.geometry && gj.geometry.coordinates ? [parseFloat(gj.geometry.coordinates[1]), parseFloat(gj.geometry.coordinates[0])] : null;
          if (props.current_location_name) return {
            coords,
            name: props.current_location_name
          };
          if (props['Current Location']) return {
            coords,
            name: props['Current Location']
          };
          if (props.name) return {
            coords,
            name: props.name
          };
          const keys = Object.keys(props);
          if (keys.length === 1) {
            const v = props[keys[0]];
            if (typeof v === 'string' && v.trim() !== '') return {
              coords,
              name: v.trim()
            };
            return {
              coords,
              name: keys[0]
            };
          }
        }
        if (gj.type === 'FeatureCollection' && Array.isArray(gj.features) && gj.features.length > 0) {
          const f = gj.features[0];
          if (f.geometry && f.geometry.type === 'Point' && f.geometry.coordinates) {
            const coords = [parseFloat(f.geometry.coordinates[1]), parseFloat(f.geometry.coordinates[0])];
            const props = f.properties || {};
            if (props.current_location_name) return {
              coords,
              name: props.current_location_name
            };
            if (props['Current Location']) return {
              coords,
              name: props['Current Location']
            };
            const keys = Object.keys(props);
            if (keys.length > 0) {
              const v = props[keys[0]];
              if (typeof v === 'string' && v.trim() !== '') return {
                coords,
                name: v.trim()
              };
              return {
                coords,
                name: keys[0]
              };
            }
            return {
              coords,
              name: null
            };
          }
        }
        if (gj.type && gj.coordinates && gj.type === 'Point') return {
          coords: [parseFloat(gj.coordinates[1]), parseFloat(gj.coordinates[0])],
          name: null
        };
      } catch (e) {
        console.warn('parse error', e);
      }
      return {
        coords: null,
        name: null
      };
    }

    function getBusCoordinates(bus) {
      const parsed = parseCurrentLocationField(bus);
      if (parsed.coords) return parsed.coords;
      if (bus.lat && bus.lng) return [parseFloat(bus.lat), parseFloat(bus.lng)];
      return null;
    }

    function getBusLocationName(bus) {
      const parsed = parseCurrentLocationField(bus);
      if (parsed.name) return parsed.name;
      const coords = getBusCoordinates(bus);
      if (coords) return `${coords[0].toFixed(6)}, ${coords[1].toFixed(6)}`;
      return null;
    }

    function escapeHtml(s) {
      if (s == null) return '';
      const d = document.createElement('div');
      d.textContent = String(s);
      return d.innerHTML;
    }

    function createPopupContent(bus) {
      const loc = escapeHtml(getBusLocationName(bus) || 'Location not available');
      const route = escapeHtml(bus.route || 'Not set');
      const status = escapeHtml(bus.status || '');
      const updated = bus.updated_at ? new Date(bus.updated_at).toLocaleString() : '';
      return `<div style="min-width:170px"><strong>${escapeHtml(bus.code)}</strong><br><strong>Route:</strong> ${route}<br><strong>Location:</strong> ${loc}<br><strong>Status:</strong> ${status}<br><strong>Seats:</strong> ${escapeHtml(bus.seats_available)} / ${escapeHtml(bus.seats_total)}<br><small style="color:#666;">Updated: ${escapeHtml(updated)}</small></div>`;
    }

    // Fetch + update loop
    async function updateBuses() {
      try {
        const res = await fetch('/api.php?action=get_buses', {
          cache: 'no-store'
        });
        if (!res.ok) throw new Error('Network response was not ok');
        const json = await res.json();
        if (json && json.buses) {
          const buses = json.buses;
          updateMap(buses);
          updateBusList(buses);
          updateRouteFilter(buses);
          const ts = new Date().toLocaleTimeString();
          document.getElementById('lastUpdateHeader').textContent = ts;
          document.getElementById('lastUpdateFooter').textContent = ts;
        }
      } catch (e) {
        console.error('Failed to update buses', e);
      }
    }

    function updateMap(buses) {
      const filtered = buses.filter(b => !selectedRoute || selectedRoute === '' || b.route === selectedRoute);
      // remove markers that are no longer present
      Object.keys(busMarkers).forEach(id => {
        if (!filtered.find(b => String(b.id) === String(id))) {
          map.removeLayer(busMarkers[id]);
          delete busMarkers[id];
        }
      });

      filtered.forEach(bus => {
        const pos = getBusCoordinates(bus);
        if (!pos) return;
        const id = String(bus.id);
        const icon = createBusIcon(bus.status);
        if (busMarkers[id]) {
          busMarkers[id].setLatLng(pos);
          busMarkers[id].setIcon(icon);
          busMarkers[id].setPopupContent(createPopupContent(bus));
        } else {
          const marker = L.marker(pos, {
            icon
          }).addTo(map);
          marker.bindPopup(createPopupContent(bus));
          busMarkers[id] = marker;
        }
      });

      // Fit bounds to markers on first load
      if (!window._mapHasBeenFitted && Object.keys(busMarkers).length > 0) {
        const group = L.featureGroup(Object.values(busMarkers));
        map.fitBounds(group.getBounds().pad(0.08));
        window._mapHasBeenFitted = true;
      }
    }

    function updateBusList(buses) {
      const listEl = document.getElementById('busList');
      const countEl = document.getElementById('busCount');
      const filtered = buses.filter(b => !selectedRoute || selectedRoute === '' || b.route === selectedRoute);
      const visible = filtered.filter(b => getBusCoordinates(b) !== null);
      countEl.textContent = visible.length;
      if (visible.length === 0) {
        listEl.innerHTML = '<p style="color:#999;font-size:.95rem;margin:6px 0">No buses available</p>';
        return;
      }

      listEl.innerHTML = visible.map(b => {
        const loc = escapeHtml(getBusLocationName(b) || 'Unknown');
        const seats = `${escapeHtml(b.seats_available)} / ${escapeHtml(b.seats_total)}`;
        const status = escapeHtml(b.status || '');
        return `<div class="bus-item" data-bus-id="${escapeHtml(b.id)}"><strong>${escapeHtml(b.code)}</strong><span class="meta">Route: ${escapeHtml(b.route || 'Not set')}</span><span class="meta">Location: ${loc}</span><span class="meta">Seats: ${seats} · ${status}</span></div>`;
      }).join('');

      // Attach click handlers to list items
      listEl.querySelectorAll('.bus-item').forEach(item => {
        item.addEventListener('click', () => {
          const id = item.getAttribute('data-bus-id');
          const marker = busMarkers[id];
          if (marker) {
            map.flyTo(marker.getLatLng(), 16, {
              duration: 0.7
            });
            setTimeout(() => marker.openPopup(), 700);
          } else {
            // fallback message
            alert('Location for this bus is not available on the map.');
          }
        });
      });
    }

    function updateRouteFilter(buses) {
      const sel = document.getElementById('routeFilter');
      const routes = Array.from(new Set(buses.map(b => b.route).filter(r => r && String(r).trim() !== '')));
      const current = sel.value;
      sel.innerHTML = '<option value="">All Routes</option>';
      routes.sort().forEach(r => {
        const opt = document.createElement('option');
        opt.value = r;
        opt.textContent = r;
        if (r === current) opt.selected = true;
        sel.appendChild(opt);
      });
    }

    // Wire up control events
    document.getElementById('routeFilter').addEventListener('change', e => {
      selectedRoute = e.target.value;
      updateBuses();
    });

    document.getElementById('refreshBtn').addEventListener('click', () => updateBuses());

    // Start polling
    updateBuses();
    setInterval(updateBuses, 3000);

    // Ensure map resizes correctly on layout changes
    window.addEventListener('resize', () => {
      setTimeout(() => map.invalidateSize(), 250);
    });
  </script>
</body>

</html>