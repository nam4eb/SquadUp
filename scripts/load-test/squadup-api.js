import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';

const baseUrl = __ENV.BASE_URL || 'http://host.docker.internal:8001/api/v1';
const email = __ENV.BENCHMARK_EMAIL || 'demo@squadup.test';
const emails = (__ENV.BENCHMARK_EMAILS || email).split(',').map((value) => value.trim()).filter(Boolean);
const password = __ENV.BENCHMARK_PASSWORD;
const summaryFile = __ENV.SUMMARY_FILE || 'phase-3-load-summary.json';
const iterationSleepSeconds = Number.parseFloat(__ENV.ITERATION_SLEEP_SECONDS || '0.2');

const apiErrors = new Rate('api_errors');
const activitiesDuration = new Trend('activities_duration', true);
const storiesDuration = new Trend('stories_duration', true);
const conversationsDuration = new Trend('conversations_duration', true);
const presenceDuration = new Trend('presence_duration', true);
const completedRequests = new Counter('completed_api_requests');
const rateLimitedRequests = new Counter('rate_limited_requests');
const serverErrors = new Counter('server_errors');
const networkErrors = new Counter('network_errors');

export const options = {
  stages: [
    { duration: __ENV.STAGE_10 || '20s', target: Number.parseInt(__ENV.STAGE_10_TARGET || '10', 10) },
    { duration: __ENV.STAGE_50 || '20s', target: Number.parseInt(__ENV.STAGE_50_TARGET || '50', 10) },
    { duration: __ENV.STAGE_100 || '20s', target: Number.parseInt(__ENV.STAGE_100_TARGET || '100', 10) },
    { duration: __ENV.RAMP_DOWN_DURATION || '10s', target: 0 },
  ],
  thresholds: {
    api_errors: ['rate<0.01'],
    http_req_duration: ['p(95)<200', 'p(99)<400'],
    activities_duration: ['p(95)<200'],
    stories_duration: ['p(95)<200'],
    conversations_duration: ['p(95)<200'],
    presence_duration: ['p(95)<200'],
  },
};

export function setup() {
  if (!password) {
    throw new Error('BENCHMARK_PASSWORD is required');
  }

  const tokens = emails.map((loginEmail) => {
    const response = http.post(`${baseUrl}/auth/login`, JSON.stringify({
      email: loginEmail,
      password,
      device_name: 'squadup-load-test',
    }), { headers: { 'Content-Type': 'application/json', Accept: 'application/json' } });

    if (response.status !== 200) {
      throw new Error(`Login failed for ${loginEmail} with HTTP ${response.status}: ${response.body}`);
    }

    return response.json('data.token');
  });

  return { tokens };
}

function record(response, trend) {
  const success = check(response, { 'API returns 2xx': (res) => res.status >= 200 && res.status < 300 });
  apiErrors.add(!success);
  rateLimitedRequests.add(response.status === 429);
  serverErrors.add(response.status >= 500);
  networkErrors.add(response.status === 0);
  trend.add(response.timings.duration);
  completedRequests.add(1);
}

export default function (data) {
  const token = data.tokens[(__VU - 1) % data.tokens.length];
  const params = {
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
    tags: { endpoint: 'activities' },
  };

  record(http.get(`${baseUrl}/activities`, params), activitiesDuration);
  params.tags.endpoint = 'stories';
  record(http.get(`${baseUrl}/stories`, params), storiesDuration);
  params.tags.endpoint = 'conversations';
  record(http.get(`${baseUrl}/conversations`, params), conversationsDuration);
  params.tags.endpoint = 'presence';
  params.headers['Content-Type'] = 'application/json';
  record(http.post(`${baseUrl}/presence/heartbeat`, JSON.stringify({ status: 'online' }), params), presenceDuration);

  sleep(iterationSleepSeconds);
}

export function teardown(data) {
  data.tokens.forEach((token) => {
    http.post(`${baseUrl}/auth/logout`, null, {
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
      tags: { endpoint: 'load_test_logout' },
    });
  });
}

export function handleSummary(data) {
  // setup_data contains the short-lived Sanctum token returned by setup().
  // It must never be persisted in benchmark artifacts.
  delete data.setup_data;

  return {
    [`/results/${summaryFile}`]: JSON.stringify(data, null, 2),
  };
}
