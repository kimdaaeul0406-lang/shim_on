// Service Worker for 쉼on
const CACHE_NAME = "shim-on-v2";
const urlsToCache = [
  "./",
  "./index.php",
  "./style.css",
  "./JavaScript.js",
  "./logo-mark.png",
  "./logo-word.png",
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
  if (url.pathname.includes("/api/")) {
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
    icon: "./logo-mark.png",
    badge: "./logo-mark.png",
    vibrate: [100, 50, 100],
    data: {
      dateOfArrival: Date.now(),
      primaryKey: 1,
    },
    actions: [
      {
        action: "explore",
        title: "확인하기",
        icon: "./logo-mark.png",
      },
      {
        action: "close",
        title: "닫기",
        icon: "./logo-mark.png",
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
    event.waitUntil(clients.openWindow("./"));
  }
});
