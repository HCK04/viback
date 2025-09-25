/*
  API Smoke Test for Vi-santé
  - Verifies key profile endpoints across all professional and organization types
  - Ensures slug resolver and id-based resolvers return stable JSON
  Requirements: Node.js >= 18 (for global fetch)
  Usage (Windows PowerShell):
    node backend/scripts/api_smoke_test.js
*/

const API_BASE = process.env.API_BASE || 'http://localhost:8000/api';
const DEFAULT_HEADERS = {
  Origin: process.env.TEST_ORIGIN || 'http://localhost:3000',
  Referer: process.env.TEST_REFERER || 'http://localhost:3000/',
  Accept: 'application/json',
  'Cache-Control': 'no-cache',
};

function slugify(input) {
  try {
    if (!input) return 'profil';
    let s = String(input)
      .normalize('NFD')
      .replace(/\p{Diacritic}/gu, '')
      .toLowerCase();
    s = s.replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)+/g, '');
    return s || 'profil';
  } catch (_) {
    return 'profil';
  }
}

function roleOf(item) {
  const r = (item?.role?.name || item?.role_name || item?.type || '').toString().toLowerCase();
  return r;
}

function nameFor(item) {
  return (
    item?.nom_clinique ||
    item?.nom_pharmacie ||
    item?.org_name ||
    item?.name ||
    `${item?.prenom || ''} ${item?.nom || ''}`.trim() ||
    'Profil'
  );
}

async function fetchJson(url, desc, timeoutMs = 8000) {
  const ctrl = new AbortController();
  const t = setTimeout(() => ctrl.abort(), timeoutMs);
  try {
    const res = await fetch(url, {
      signal: ctrl.signal,
      headers: DEFAULT_HEADERS,
    });
    const ct = res.headers.get('content-type') || '';
    let body = null;
    if (ct.includes('application/json')) {
      body = await res.json();
    } else {
      body = await res.text();
    }
    return { ok: res.ok, status: res.status, url, desc, body };
  } catch (e) {
    return { ok: false, status: 0, url, desc, body: { error: e.message } };
  } finally {
    clearTimeout(t);
  }
}

function endpointsFor(item) {
  const id = item?.id;
  const type = roleOf(item);
  const name = nameFor(item);
  const slug = slugify(name);

  const urls = [];

  // Universal endpoints
  urls.push({ url: `${API_BASE}/profiles/${id}`, desc: 'profiles/{id}' });
  urls.push({ url: `${API_BASE}/profiles/slug/${encodeURIComponent(slug)}?id=${encodeURIComponent(id)}${type ? `&type=${encodeURIComponent(type)}` : ''}`, desc: 'profiles/slug/{slug}?id&type' });

  // Type-specific
  if (type === 'medecin') {
    urls.push({ url: `${API_BASE}/professionals/${id}`, desc: 'professionals/{id}' });
    urls.push({ url: `${API_BASE}/medecins/${id}`, desc: 'medecins/{id}' });
  } else if (['kine', 'orthophoniste', 'psychologue'].includes(type)) {
    urls.push({ url: `${API_BASE}/professionals/${id}`, desc: 'professionals/{id}' });
  } else if (type === 'clinique') {
    urls.push({ url: `${API_BASE}/clinics/${id}`, desc: 'clinics/{id}' });
    urls.push({ url: `${API_BASE}/organizations/${id}`, desc: 'organizations/{id}' });
  } else if (type === 'pharmacie') {
    urls.push({ url: `${API_BASE}/pharmacies/${id}`, desc: 'pharmacies/{id}' });
    urls.push({ url: `${API_BASE}/organizations/${id}`, desc: 'organizations/{id}' });
    urls.push({ url: `${API_BASE}/pharmacies/slug/${encodeURIComponent(slug)}`, desc: 'pharmacies/slug/{slug}' });
  } else if (type === 'parapharmacie') {
    urls.push({ url: `${API_BASE}/parapharmacies/${id}`, desc: 'parapharmacies/{id}' });
    urls.push({ url: `${API_BASE}/organizations/${id}`, desc: 'organizations/{id}' });
    urls.push({ url: `${API_BASE}/parapharmacies/slug/${encodeURIComponent(slug)}`, desc: 'parapharmacies/slug/{slug}' });
  } else if (type === 'labo_analyse' || type === 'centre_radiologie') {
    urls.push({ url: `${API_BASE}/organizations/${id}`, desc: 'organizations/{id}' });
  } else {
    // Unknown: test generic organizations/professionals if makes sense
    urls.push({ url: `${API_BASE}/organizations/${id}`, desc: 'organizations/{id} (generic try)' });
    urls.push({ url: `${API_BASE}/professionals/${id}`, desc: 'professionals/{id} (generic try)' });
  }

  // Optional: users/{id}
  urls.push({ url: `${API_BASE}/users/${id}`, desc: 'users/{id}' });

  return { id, type, name, slug, urls };
}

function short(obj, len = 140) {
  try {
    const s = typeof obj === 'string' ? obj : JSON.stringify(obj);
    return s.length > len ? s.slice(0, len) + '…' : s;
  } catch (_) {
    return String(obj);
  }
}

async function main() {
  console.log(`API_BASE: ${API_BASE}`);
  const usersRes = await fetchJson(`${API_BASE}/users`, 'users');
  if (!usersRes.ok) {
    console.error('Failed to fetch /users', usersRes.status, usersRes.body);
    process.exit(1);
  }
  const list = Array.isArray(usersRes.body?.data) ? usersRes.body.data : (Array.isArray(usersRes.body) ? usersRes.body : []);
  if (!Array.isArray(list) || list.length === 0) {
    console.warn('No users returned by /users. Nothing to test.');
    process.exit(0);
  }

  // Group by role for summary
  const byRole = new Map();
  for (const item of list) {
    const r = roleOf(item) || 'unknown';
    byRole.set(r, (byRole.get(r) || 0) + 1);
  }
  console.log('Discovered roles:', Object.fromEntries(byRole));

  let failures = 0;
  let total = 0;

  for (const item of list) {
    const { id, type, name, slug, urls } = endpointsFor(item);
    console.log(`\n=== Testing user ${id} (${type || 'unknown'}) - ${name} ===`);
    for (const { url, desc } of urls) {
      total++;
      const res = await fetchJson(url, desc);
      const ok = res.ok && res.status >= 200 && res.status < 300;
      const idMatches = ok && res.body && typeof res.body === 'object' && !Array.isArray(res.body) && (res.body.id == null || String(res.body.id) === String(id));
      const verdict = ok && idMatches ? 'OK' : (ok ? 'WARN' : 'FAIL');
      if (verdict !== 'OK') failures++;
      console.log(`[${verdict}] ${desc} -> ${res.status} ${res.url}`);
      if (!ok) {
        console.log('  Response:', short(res.body));
      }
    }
  }

  console.log(`\nCompleted. Total endpoint checks: ${total}. Failures/Warnings: ${failures}.`);
  if (failures > 0) process.exitCode = 2;
}

main().catch((e) => {
  console.error('Fatal error:', e);
  process.exit(2);
});
