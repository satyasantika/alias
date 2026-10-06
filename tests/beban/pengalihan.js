// k6 run -e BASE=http://alias-perf-nginx -e RPS=50 -e DURASI=60s -v /data:/data tests/beban/pengalihan.js
// Memukul 1.000 kode acak (daftar JSON di /data/kode.json) dengan laju tetap; redirect tidak diikuti.
import http from 'k6/http';
import { check } from 'k6';

const kode = JSON.parse(open('/data/kode.json'));
const rps = Number(__ENV.RPS || 50);

export const options = {
  scenarios: {
    pengalihan: {
      executor: 'constant-arrival-rate',
      rate: rps,
      timeUnit: '1s',
      duration: __ENV.DURASI || '60s',
      preAllocatedVUs: Math.max(20, rps),
      maxVUs: rps * 4,
    },
  },
  thresholds: { http_req_duration: ['p(95)<100'], http_req_failed: ['rate<0.001'] },
  summaryTrendStats: ['avg', 'min', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
};

export default function () {
  const k = kode[Math.floor(Math.random() * kode.length)];
  const r = http.get(`${__ENV.BASE || 'http://alias-nginx'}/${k}`, { redirects: 0, headers: { 'User-Agent': 'Mozilla/5.0 (k6 beban)' } });
  check(r, { '302': (res) => res.status === 302 });
}
