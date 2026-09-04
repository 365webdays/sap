/**
 * Service worker — app shell caching.
 *
 * Caches the static assets (HTML, JS, CSS, images, icons) so the app loads
 * instantly and renders its shell even offline. API calls are never cached
 * — they need fresh data, and the app's existing error states handle
 * network failures gracefully.
 */

const CACHE_VERSION = "v2";
const CACHE_NAME = "stanthony-adoration-" + CACHE_VERSION;

// Assets to pre-cache on install. The hashed JS/CSS filenames are captured
// at build time by the fetch handler below, so this list only needs the
// shell files with stable names.
const PRECACHE_URLS = [
  "/",
  "/index.html",
  "/manifest.json",
  "/logo_web.png",
  "/favicon.ico",
  "/favicon-32x32.png",
  "/apple-icon.png",
  "/apple-icon-180x180.png",
  "/icon-512x512.png",
  "/android-icon-192x192.png",
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(PRECACHE_URLS))
  );
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  // Remove old cache versions.
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys
          .filter((key) => key.startsWith("stanthony-adoration-") && key !== CACHE_NAME)
          .map((key) => caches.delete(key))
      )
    )
  );
  self.clients.claim();
});

self.addEventListener("fetch", (event) => {
  const { request } = event;

  // Only handle GET requests.
  if (request.method !== "GET") return;

  const url = new URL(request.url);

  // Only handle same-origin requests — let cross-origin (Google Fonts,
  // etc.) go straight to the network without SW interference.
  if (url.origin !== self.location.origin) return;

  // Never cache API calls — they need fresh data.
  if (url.pathname.startsWith("/api/")) return;

  // Navigation requests (page loads): network-first, fall back to cached
  // shell so the app renders offline. This ensures users get fresh HTML
  // when online but still see the app shell when offline.
  if (request.mode === "navigate") {
    event.respondWith(
      fetch(request)
        .then((response) => {
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put("/index.html", copy));
          return response;
        })
        .catch(() => caches.match("/index.html").then((r) => r || caches.match("/")))
    );
    return;
  }

  // Static assets: cache-first. If it's in the cache, serve from there
  // (fast). If not, fetch from network and cache it for next time.
  event.respondWith(
    caches.match(request).then((cached) => {
      if (cached) return cached;
      return fetch(request).then((response) => {
        // Only cache successful, same-origin responses.
        if (!response || response.status !== 200 || response.type !== "basic") {
          return response;
        }
        const copy = response.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
        return response;
      });
    })
  );
});
