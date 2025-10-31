// Service Worker for 쉼on
const CACHE_NAME = "shim-on-v2";
const urlsToCache = [
  "/shim-on/",
  "/shim-on/index.php",
  "/shim-on/style.css",
  "/shim-on/JavaScript.js",
  "/shim-on/logo-mark.png",
  "/shim-on/logo-word.png",
];

// Install event
self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(urlsToCache);
    })
  );
});

// Fetch event
self.addEventListener("fetch", (event) => {
  const url = new URL(event.request.url);
  // 1) API 요청은 캐시를 거치지 않고 항상 네트워크로
  if (url.pathname.startsWith("/shim-on/api/")) {
    event.respondWith(fetch(event.request));
    return;
  }
  // 2) 그 외 정적 파일만 cache-first
  event.respondWith(
    caches.match(event.request).then((resp) => resp || fetch(event.request))
  );
});

// Background sync for notifications
self.addEventListener("sync", (event) => {
  if (event.tag === "background-sync") {
    event.waitUntil(doBackgroundSync());
  }
});

function doBackgroundSync() {
  // Check for pending notifications
  return new Promise((resolve) => {
    // This would check for scheduled reminders
    resolve();
  });
}

// Push notification handling
self.addEventListener("push", (event) => {
  const options = {
    body: event.data ? event.data.text() : "쉼on 알림",
    icon: "/shim-on/logo-mark.png",
    badge: "/shim-on/logo-mark.png",
    vibrate: [100, 50, 100],
    data: {
      dateOfArrival: Date.now(),
      primaryKey: 1,
    },
    actions: [
      {
        action: "explore",
        title: "확인하기",
        icon: "/shim-on/logo-mark.png",
      },
      {
        action: "close",
        title: "닫기",
        icon: "/shim-on/logo-mark.png",
      },
    ],
  };

  event.waitUntil(self.registration.showNotification("쉼on 알림", options));
});

// Notification click handling
self.addEventListener("notificationclick", (event) => {
  event.notification.close();

  if (event.action === "explore") {
    // Open the app
    event.waitUntil(clients.openWindow("/shim-on/"));
  }
});
self.addEventListener("fetch", (event) => {
  const url = new URL(event.request.url);

  // ✅ API는 네트워크로 바로 보내고, respondWith 안 건다
  if (url.pathname.includes("/api/")) return;

  // 나머지 정적 리소스는 기존 캐시 전략...
  // event.respondWith( ... );
});