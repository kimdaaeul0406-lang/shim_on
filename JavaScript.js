/* ============================================================
   쉼on – Mobile UX Script (Consolidated / Clean)
   Requires jQuery
   ============================================================ */
$(function () {
  /* ---------- 공통 셀렉터 ---------- */
  const $body = $("body");
  const $menu = $(".menu");
  const $panel = $(".menu .panel");
  const $ovl = $("#composer-ovl"); // 모달 오버레이
  const $modal = $(".modal"); // 모달 본체
  const $img = $("#cmpr-img"); // 미리보기 이미지
  const $txt = $("#cmpr-text"); // 미리보기 텍스트

  /* ---------- 공용 유틸 ---------- */
  const openMenu = () => {
    $menu.addClass("open");
    $body.addClass("no-scroll");
  };
  const closeMenu = () => {
    $menu.removeClass("open");
    $body.removeClass("no-scroll");
  };

  // 토스트(길이에 따라 자동 시간) – 전역 1개만
  window.showToast = (msg, type = "info") => {
    const len = (msg || "").length;
    const dur = Math.max(2000, Math.min(4500, len * 90)); // 2.0s~4.5s
    const $to = $(".toast");
    $to.text(msg).removeClass("success error info").addClass(`show ${type}`);
    setTimeout(() => $to.removeClass("show"), dur);
  };

  // 햅틱 피드백 (모바일에서 진동)
  const hapticFeedback = () => {
    if ("vibrate" in navigator) {
      navigator.vibrate(10); // 짧은 진동
    }
  };

  // 터치 피드백 애니메이션
  const addTouchFeedback = (element) => {
    $(element).addClass("touch-feedback");
    setTimeout(() => {
      $(element).removeClass("touch-feedback");
    }, 150);
  };

  // 알림 권한 요청
  const requestNotificationPermission = async () => {
    if ("Notification" in window) {
      console.log("Requesting notification permission...");
      try {
        const permission = await Notification.requestPermission();
        console.log("Permission result:", permission);

        if (permission === "granted") {
          showToast("알림이 활성화되었습니다!", "success");
          return true;
        } else if (permission === "denied") {
          showToast(
            "알림 권한이 거부되었습니다. 브라우저 설정에서 수동으로 허용해주세요.",
            "error"
          );
          return false;
        } else {
          showToast("알림 권한 요청이 취소되었습니다.", "info");
          return false;
        }
      } catch (error) {
        console.error("Notification permission error:", error);
        showToast("알림 권한 요청 중 오류가 발생했습니다.", "error");
        return false;
      }
    }
    return false;
  };

  // 서비스 워커 등록
  if ("serviceWorker" in navigator) {
    window.addEventListener("load", () => {
      navigator.serviceWorker
        .register("./sw.js")
        .then((registration) => {
          console.log("SW registered: ", registration);
        })
        .catch((registrationError) => {
          console.log("SW registration failed: ", registrationError);
        });
    });
  }

  // 알림 권한 상태 확인 및 UI 업데이트
  const updateNotificationStatus = () => {
    const statusEl = $("#notification-status");
    const requestBtn = $("#request-notification");
    const resetBtn = $("#reset-notification");

    if (!("Notification" in window)) {
      statusEl.html("❌ 이 브라우저는 알림을 지원하지 않습니다.");
      requestBtn.hide();
      resetBtn.hide();
      return;
    }

    // 디버깅 정보 추가
    console.log("Notification permission:", Notification.permission);
    console.log("User Agent:", navigator.userAgent);

    switch (Notification.permission) {
      case "granted":
        statusEl.html("✅ 알림이 활성화되었습니다. 알림을 받을 수 있습니다.");
        statusEl.css("color", "var(--green)");
        requestBtn.hide();
        resetBtn.hide();
        break;
      case "denied":
        statusEl.html(
          "❌ 알림이 차단되었습니다. 브라우저 설정에서 수동으로 허용해주세요."
        );
        statusEl.css("color", "var(--danger)");
        requestBtn.hide();
        resetBtn.show();
        break;
      case "default":
        statusEl.html(
          "⚠️ 알림 권한이 필요합니다. 아래 버튼을 눌러 허용해주세요."
        );
        statusEl.css("color", "var(--point)");
        requestBtn.show();
        resetBtn.hide();
        break;
      default:
        // 예상치 못한 상태
        statusEl.html(
          `⚠️ 알림 상태를 확인할 수 없습니다. (${Notification.permission})`
        );
        statusEl.css("color", "var(--point)");
        requestBtn.show();
        resetBtn.show();
        break;
    }
  };

  // 알림 권한 요청 버튼 이벤트
  $("#request-notification").on("click", async () => {
    hapticFeedback();
    const granted = await requestNotificationPermission();
    updateNotificationStatus();
  });

  // 권한 재설정 버튼 이벤트
  $("#reset-notification").on("click", () => {
    hapticFeedback();
    showToast("브라우저 설정에서 알림 권한을 수동으로 재설정해주세요.", "info");
    // 브라우저 설정 페이지로 이동하는 안내
    if (confirm("브라우저 설정 페이지로 이동하시겠습니까?")) {
      // 모바일에서 브라우저 설정으로 이동하는 방법 안내
      showToast("설정 > 사이트 설정 > 알림에서 권한을 변경해주세요.", "info");
    }
  });

  // 페이지 로드 시 알림 상태 확인
  updateNotificationStatus();

  // 메인 페이지에서 알림 힌트 표시
  const showNotificationHint = () => {
    const hintEl = $("#notification-hint");
    if (hintEl.length && "Notification" in window) {
      console.log(
        "Main page notification permission:",
        Notification.permission
      );
      if (Notification.permission !== "granted") {
        hintEl.show();
      } else {
        hintEl.hide();
      }
    }
  };

  // 메인 페이지에서만 힌트 표시
  if (
    window.location.search.includes("page=main") ||
    window.location.pathname.endsWith("/index.php") ||
    window.location.pathname === "/"
  ) {
    showNotificationHint();
  }

  // 체크박스 터치 개선 (이벤트 위임 사용)
  $(document).on("click", 'input[type="checkbox"]', function (e) {
    hapticFeedback();
    // 기본 동작은 그대로 두고 햅틱 피드백만 추가
  });

  // 라벨 클릭 시 체크박스 토글 (이벤트 위임 사용)
  $(document).on("click", "label", function (e) {
    const checkbox = $(this).find('input[type="checkbox"]')[0];
    if (checkbox) {
      hapticFeedback();
      // 기본 동작은 그대로 두고 햅틱 피드백만 추가
    }
  });

  /* ---------- 메뉴 열기/닫기 ---------- */
  $(".hamburger").on("click", openMenu);

  // 패널 바깥 클릭 시 닫기
  $menu.on("click", (e) => {
    if ($(e.target).closest(".panel").length === 0) closeMenu();
  });
  // ESC
  $(document).on("keydown", (e) => {
    if (e.key === "Escape") closeMenu();
  });

  // 패널 헤더: "X만" 주입(중복 방지)
  (function ensurePanelHeader() {
    // 하단 '닫기' 유사 요소가 있었다면 제거
    $panel.find('.btn-close, .close, [data-role="close"]').remove();
    if ($panel.find(".panel-header").length === 0) {
      $panel.prepend(`
          <div class="panel-header">
            <button class="close-btn" aria-label="닫기">×</button>
          </div>
        `);
    }
    $panel.off("click.close").on("click.close", ".close-btn", closeMenu);
  })();

  // 스와이프 닫기(오른쪽→왼쪽 70px)
  (function bindSwipeToClose() {
    let sx = null;
    $panel.on("touchstart.swc", (e) => {
      sx = e.originalEvent.touches[0].clientX;
    });
    $panel.on("touchmove.swc", (e) => {
      if (sx === null) return;
      const cx = e.originalEvent.touches[0].clientX;
      if (sx - cx > 70) {
        closeMenu();
        sx = null;
      }
    });
    $panel.on("touchend.swc", () => {
      sx = null;
    });
  })();
  // ---- 카메라 복귀 중복 방지용 플래그들 ----
  let photoChangeLock = false; // 연속 change 방지 (디바운스)
  let didAutoScrollOnce = false; // 카메라 복귀 후 스크롤 1회만

  /* ---------- 모달 열기/닫기 ---------- */
  const openModal = () => {
    $ovl.addClass("open");
    $body.addClass("no-scroll");
  };
  const closeModal = () => {
    $ovl.removeClass("open");
    $body.removeClass("no-scroll");
    const f = document.getElementById("memo-form");
    if (f) f.reset();

    $("#photo").val(""); // 파일 입력 강제 초기화

    $img.attr("src", "").hide();
    $txt.text("").hide();
    $(".preview-wrap").removeClass("is-filled loading");

    // 상태 초기화
    photoChangeLock = false;
    didAutoScrollOnce = false;
  };

  $("#btn-add-main").on("click", (e) => {
    e.preventDefault();
    hapticFeedback();
    addTouchFeedback(e.target);
    openModal();
  });
  $ovl.on("click", (e) => {
    if (e.target === e.currentTarget) closeModal();
  });
  $(document).on("keydown", (e) => {
    if (e.key === "Escape") closeModal();
  });
  $(document).on("click", "#btn-cancel", () => {
    hapticFeedback();
    closeModal();
  });

  // 두 버튼이 숨김 input(#photo)을 각각 다른 모드로 클릭
  $("#btn-pick").on("click", function (e) {
    hapticFeedback();
    addTouchFeedback(e.target);
    $("#photo").removeAttr("capture"); // 파일선택
    $("#photo").trigger("click");
  });
  $("#btn-camera").on("click", function (e) {
    hapticFeedback();
    addTouchFeedback(e.target);
    $("#photo").attr("capture", "environment"); // 사진찍기(후면카메라 우선)
    $("#photo").trigger("click");
  });

  // 아래로 스와이프하면 모달 닫기 (80px 임계값)
  (function bindModalSwipeDown() {
    let startY = null;
    $modal.on("touchstart.msd", (e) => {
      startY = e.originalEvent.touches[0].clientY;
    });
    $modal.on("touchmove.msd", (e) => {
      if (startY === null) return;
      const currentY = e.originalEvent.touches[0].clientY;
      if (currentY - startY > 80) {
        closeModal();
        startY = null;
      }
    });
    $modal.on("touchend.msd", () => {
      startY = null;
    });
  })();

  /* ---------- 미리보기 상태 업데이트 ---------- */
  function updatePreviewState() {
    const hasImg = $img.is(":visible") && $img.attr("src");
    const hasText = ($txt.text() || "").trim().length > 0;
    $(".preview-wrap").toggleClass("is-filled", !!(hasImg || hasText));
  }

  // 파일 선택(카메라 복귀 포함) – 디바운스 + createObjectURL + 1회 스크롤
  $(document).on("change", "#photo", function () {
    if (photoChangeLock) return;
    photoChangeLock = true;
    setTimeout(() => {
      photoChangeLock = false;
    }, 600);

    const file = this.files && this.files[0];
    // 이미지 용량이 너무 크면 리사이즈 (예: 1200px로 축소) → dataURL 로 미리보기 (★중요)
    if (file && file.size > 2 * 1024 * 1024) {
      // 2MB 이상일 때
      const img = new Image();
      const reader = new FileReader();
      reader.onload = (e) => {
        img.onload = () => {
          const canvas = document.createElement("canvas");
          const maxW = 1200;
          const scale = maxW / img.width;
          canvas.width = maxW;
          canvas.height = img.height * scale;
          const ctx = canvas.getContext("2d");
          ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
          const dataURL = canvas.toDataURL("image/jpeg", 0.85); // ★ dataURL
          $img.attr("src", dataURL).show();
          $(".preview-wrap").addClass("is-filled");
          updatePreviewState();
          hapticFeedback();
          showToast("사진이 선택되었습니다", "success");
        };
        img.src = e.target.result;
      };
      reader.readAsDataURL(file);
      return; // 원본 미리보기 로직은 건너뜀
    }

    if (!file) {
      $img.hide().attr("src", "");
      updatePreviewState();
      return;
    }

    // 이미지 로딩 중 상태 표시
    $(".preview-wrap").addClass("loading");

    const url = URL.createObjectURL(file);
    $img.one("load", () => {
      URL.revokeObjectURL(url);
      $(".preview-wrap").removeClass("loading");
      // 성공 피드백
      hapticFeedback();
      showToast("사진이 선택되었습니다", "success");
    });
    // 기존: $img.attr('src', url).show();
    setTimeout(() => {
      // RAF 두 번으로 페인팅 안정화
      requestAnimationFrame(() =>
        requestAnimationFrame(() => {
          $img.attr("src", url).show();
        })
      );
    }, 200);

    $(".preview-wrap").addClass("is-filled");
    updatePreviewState();

    // 카메라 복귀 후 텍스트 입력창으로 부드럽게 이동
    if (!didAutoScrollOnce) {
      didAutoScrollOnce = true;
      setTimeout(() => {
        const ta = document.getElementById("memo-text");
        if (ta) {
          ta.focus();
          // 부드러운 스크롤로 텍스트 입력창이 보이도록
          setTimeout(() => {
            ta.scrollIntoView({ behavior: "smooth", block: "nearest" });
          }, 100);
        }
        setTimeout(() => {
          didAutoScrollOnce = false;
        }, 2000);
      }, 600); // 카메라 복귀 후 잠시 대기
    }

    $("#photo").removeAttr("capture");
  });

  $(document).on("focus", "#memo-text", function () {
    const el = this;
    setTimeout(() => {
      el.scrollIntoView({ behavior: "smooth", block: "nearest" });
    }, 100);
  });

  // 텍스트 입력
  $(document).on("input", "#memo-text", function () {
    const v = $(this).val().trim();
    $txt.toggle(!!v).html(v.replace(/\n/g, "<br>")); // 줄바꿈 반영
    updatePreviewState();
  });

  // 이미지 로드 실패 처리
  $img.on("error", function () {
    $(this).hide().attr("src", "");
    updatePreviewState();
  });

  // 미리보기 영역 클릭으로 파일 선택
  $(".preview-wrap").on("click", function () {
    hapticFeedback();
    $("#btn-pick").trigger("click");
  });

  // 미리보기 영역에 안내 텍스트 추가
  $(".preview-wrap .preview-label").html(`
    <div>사진을 선택하거나 찍어보세요</div>
  `);

  /* ---------- 탭/아코디언 (고객센터) ---------- */
  const $tabs = $("[data-tab]");
  const showTab = (name) => {
    $(".tabview").removeClass("active");
    $("#tab-" + name).addClass("active");
    $tabs.removeClass("primary");
    $tabs.filter('[data-tab="' + name + '"]').addClass("primary");
  };
  $tabs.on("click", function () {
    showTab($(this).data("tab"));
  });

  // 주소에 ?tab=xxx 있을 경우 프리셀렉트
  const t = new URL(location.href).searchParams.get("tab");
  if (t) {
    showTab(t);
  }

  // 아코디언 토글
  $(document).on("click", ".kitem h4", function () {
    const $ans = $(this).closest(".kitem").find(".ans");
    if (!$ans.length) return;
    $ans.stop(true, true).slideToggle(160);
    const $icon = $(this).find("span:last");
    if ($icon.length) {
      $icon.text($icon.text() === "＋" ? "－" : "＋");
    }
  });

  /* ---------- 플래시/토스트 ---------- */
  const url = new URL(location.href);
  if (url.searchParams.get("saved") === "1") {
    showToast("저장되었습니다");
  }

  const ok = $(".flash-ok").text ? $(".flash-ok").text().trim() : "";
  const err = $(".flash-err").text ? $(".flash-err").text().trim() : "";
  if (ok) {
    showToast(ok);
  }
  if (err) {
    alert(err);
  }

  /* ---------- 알림 엔진 (옵션: SHIM.reminders 있을 때만) ---------- */
  (function () {
    if (!window.SHIM || !window.SHIM.loggedIn) return;

    const pad = (n) => String(n).padStart(2, "0");
    const dayMap = { sun: 0, mon: 1, tue: 2, wed: 3, thu: 4, fri: 5, sat: 6 };

    const beep = () => {
      try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = "sine";
        osc.frequency.value = 880;
        osc.connect(gain);
        gain.connect(ctx.destination);
        gain.gain.setValueAtTime(0.001, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.3, ctx.currentTime + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 1.0);
        osc.start();
        osc.stop(ctx.currentTime + 1.05);
      } catch (e) { }
    };

    const ring = (label, time) => {
      beep();
      showToast(`⏰ ${label} · ${time}`);

      // 웹 알림
      if ("Notification" in window && Notification.permission === "granted") {
        const notification = new Notification("쉼on 알림", {
          body: `${label} · ${time}`,
          icon: "./logo-mark.png",
          badge: "./logo-mark.png",
          vibrate: [200, 100, 200],
          requireInteraction: true,
          tag: "shim-on-reminder",
        });

        // 알림 클릭 시 앱으로 이동
        notification.onclick = () => {
          window.focus();
          notification.close();
        };

        // 5초 후 자동 닫기
        setTimeout(() => notification.close(), 5000);
      }

      // 진동 (모바일)
      if ("vibrate" in navigator) {
        navigator.vibrate([200, 100, 200, 100, 200]);
      }
    };

    const reminders = Array.isArray(window.SHIM.reminders)
      ? window.SHIM.reminders
      : [];
    const firedKey = (idx, time, d) =>
      `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(
        d.getDate()
      )}__${idx}__${time}`;

    const check = () => {
      if (!reminders.length) return;
      const d = new Date();
      const dow = d.getDay();
      const hm = `${pad(d.getHours())}:${pad(d.getMinutes())}`;

      reminders.forEach((r, idx) => {
        const time = (r.time || "14:00").slice(0, 5);
        const days = String(r.days || "mon,tue,wed,thu,fri,sat,sun").split(",");
        if (!days.some((k) => dayMap[k] === dow)) return;
        if (hm !== time) return;

        const key = firedKey(idx, time, d);
        if (localStorage.getItem(`shim_fired_${key}`) === "1") return;
        localStorage.setItem(`shim_fired_${key}`, "1");
        ring(r.label || "알림", time);
      });
    };

    // 자정 이후 fired 키 리셋
    const resetAtMidnight = () => {
      const next = new Date();
      next.setHours(24, 0, 0, 0);
      setTimeout(() => {
        Object.keys(localStorage)
          .filter((k) => k.startsWith("shim_fired_"))
          .forEach((k) => localStorage.removeItem(k));
        resetAtMidnight();
      }, next.getTime() - Date.now() + 1000);
    };
    resetAtMidnight();

    check();
    setInterval(check, 15000);
  })();

  // ===== 🌿 FEED: 렌더링 유틸 =====
  function readAllMemos() {
    try {
      // localStorage 사용 가능 여부 체크
      if (!window.localStorage) {
        console.warn("localStorage를 사용할 수 없습니다");
        return [];
      }
      const data = localStorage.getItem("memos");
      if (!data) return [];
      return JSON.parse(data);
    } catch (e) {
      console.error("메모 읽기 오류:", e);
      return [];
    }
  }
  function writeAllMemos(arr) {
    try {
      // localStorage 사용 가능 여부 체크
      if (!window.localStorage) {
        throw new Error(
          "localStorage를 사용할 수 없습니다. 브라우저 설정을 확인하세요."
        );
      }

      // 데이터 크기 체크 (5MB 제한)
      const jsonStr = JSON.stringify(arr || []);
      const sizeInMB = new Blob([jsonStr]).size / (1024 * 1024);

      if (sizeInMB > 4.5) {
        throw new Error("저장 공간이 부족합니다. 오래된 메모를 삭제해주세요.");
      }

      localStorage.setItem("memos", jsonStr);
      console.log(`메모 저장 완료 (${arr.length}개, ${sizeInMB.toFixed(2)}MB)`);
    } catch (e) {
      console.error("메모 저장 오류:", e);
      throw e; // 상위로 에러 전달
    }
  }
  function renderFeedPage() {
    const wrap = document.getElementById("feed-page");
    if (!wrap) {
      console.log("피드 페이지 요소를 찾을 수 없음");
      return;
    }

    // 페이지 파라미터 확인
    const u = new URL(location.href);
    const page = u.searchParams.get("page");
    const isFeed = page === "feed";

    console.log("현재 페이지:", page, "/ 피드 여부:", isFeed);

    // 표시/숨김
    wrap.style.display = isFeed ? "block" : "none";

    if (!isFeed) return;

    const listEl = document.getElementById("feed-list");
    if (!listEl) {
      console.log("피드 리스트 요소를 찾을 수 없음");
      return;
    }

    // Debugging: Check if feed-list exists
    if (!listEl) {
      console.error("[Debug] feed-list element not found in DOM.");
      return;
    } else {
      console.log("[Debug] feed-list element found.");
    }

    // Debugging: Check if feed-list is visible
    const feedListStyle = window.getComputedStyle(listEl);
    if (feedListStyle.display === "none") {
      console.warn("[Debug] feed-list is hidden (display: none).");
    } else {
      console.log("[Debug] feed-list is visible.");
    }

    // Mobile-specific debugging
    console.log("[Debug - Mobile] Checking feed-list visibility...");
    const feedListStyleMobile = window.getComputedStyle(listEl);
    if (feedListStyleMobile.display === "none") {
      console.warn(
        "[Debug - Mobile] feed-list is hidden (display: none) on mobile."
      );
    } else {
      console.log("[Debug - Mobile] feed-list is visible on mobile.");
    }

    // Force feed-list to be visible
    listEl.style.display = "block";
    listEl.style.visibility = "visible";
    const feedParent = listEl.closest("section");
    if (feedParent) {
      feedParent.style.display = "block";
      feedParent.style.visibility = "visible";
    }
    console.log(
      "[Debug - Mobile] feed-list and parent elements forced to display and visible."
    );

    // localStorage에서 메모 읽기
    let all = [];
    try {
      const stored = localStorage.getItem("memos");
      if (stored) {
        all = JSON.parse(stored);
        console.log("저장된 메모 총 개수:", all.length);
      } else {
        console.log("저장된 메모가 없음");
      }
    } catch (e) {
      console.error("메모 읽기 실패:", e);
    }

    // public: true인 것만 필터링
    const pubs = all.filter((m) => m && m.public === true);
    console.log("공유된 메모 개수:", pubs.length);

    if (!pubs.length) {
      listEl.innerHTML = `<div class="feed-empty"><div class="feed-empty-text">아직 공유된 사진이 없어요<br/>작성할 때 <strong>'공유하기'</strong>를 체크해 보세요!</div></div>`;
      return;
    }

    // 최신순 정렬
    pubs.sort((a, b) => (b.date || "").localeCompare(a.date || ""));

    // Extend sharing functionality to include user information
    const currentUser = window.SHIM?.currentUser || "guest"; // Default to 'guest' if no user info

    // Remove duplicate entries based on ID
    const uniquePubs = pubs.filter(
      (memo, index, self) => index === self.findIndex((m) => m.id === memo.id)
    );

    const html = uniquePubs
      .map((m) => {
        console.log("렌더링 메모:", m.id, "이미지:", m.image ? "있음" : "없음");
        return `
    <article class="feed-card">
      <div class="img-wrap">
        ${m.image
            ? `<img src="${m.image}" alt="shared photo" loading="lazy" onerror="console.error('이미지 로드 실패:', this.src)">`
            : `<div style="padding:20px;text-align:center;color:var(--muted)">이미지 없음</div>`
          }
      </div>
      ${m.text
            ? `<p class="text">${(m.text || "").replace(/\n/g, "<br>")}</p>`
            : ``
          }
      <div class="meta">
        <span class="user">Shared by: ${m.userId}</span>
        <span class="date">${new Date(m.date || Date.now()).toLocaleString(
            "ko-KR"
          )}</span>
        <span class="tag chip">공유됨</span>
      </div>
    </article>
  `;
      })
      .join("");

    // Debugging: Check rendered HTML
    console.log("[Debug] Rendered HTML:", html);

    // Debugging: Rendered HTML on mobile
    console.log("[Debug - Mobile] Rendered HTML:", html);

    listEl.innerHTML = html;
    console.log("피드 렌더링 완료");
  }

  // Debugging: Check localStorage data
  console.log(
    "[Debug] Current localStorage memos:",
    localStorage.getItem("memos")
  );

  // Debugging: Verify renderFeedPage execution
  console.log("[Debug] Executing renderFeedPage...");

  /* ---------- 부드러운 페이지 이동(선택) ---------- */
  window.softNavigate = function (url, delay = 220) {
    $("body").addClass("fadeout");
    setTimeout(() => {
      location.href = url;
    }, delay);
  };
  // 기존 호환
  window.movemain = function () {
    window.softNavigate("index.php?page=main");
  };

  /* ---------- 메뉴 링크 클릭 시 body 잠금 해제 보정 ---------- */
  $(".menu .links a").on("click", function () {
    $body.removeClass("no-scroll");
  });
});

// ===== 🔥 모바일 저장 - 공유 기능 포함 버전 =====
let submitting = false;

$(document).on("submit", "#memo-form", async function (e) {
  e.preventDefault();

  if (submitting) {
    console.log("중복 제출 차단");
    return false;
  }
  submitting = true;

  const $saveBtn = $("#btn-save");
  $saveBtn.prop("disabled", true).addClass("disabled").text("저장 중…");

  try {
    // 공유 체크 여부
    const share = $("#share-public").is(":checked");

    // 텍스트/이미지 상태
    const text = ($("#memo-text").val() || "").trim();
    const fileInput = document.getElementById("photo");
    const file = fileInput && fileInput.files && fileInput.files[0];
    const imgPreviewSrc = $("#cmpr-img").attr("src") || "";
    const hasAnyImg = file || imgPreviewSrc;

    if (!text && !hasAnyImg) {
      showToast("메모 내용이나 사진을 추가해주세요", "error");
      submitting = false;
      $saveBtn.prop("disabled", false).removeClass("disabled").text("저장");
      return false;
    }

    // ① 공유인 경우: 서버 업로드 + 서버 저장
    if (share) {
      try {
        if (!file) {
          throw new Error(
            "이미지 파일을 선택해 주세요(공유에는 파일 업로드가 필요)"
          );
        }
        const photoUrl = await uploadFile(file); // /shim-on/api/upload.php
        await shareMemo({
          text,
          photoUrl,
          author: window.SHIM?.currentUserId || "guest",
        });
        showToast("공유 완료!", "success");
        // localStorage update removed as we reload or submit form

        // 피드 페이지라면 새로고침을 위해 폼 제출을 막고 로드
        if (location.search.includes("page=feed")) {
          await loadFeed();
        }

        // ★ 공유 후에도 '내 피드(DB)'에 저장하기 위해 아래 로직(②)으로 진행!
        // 여기서 return false 하지 않고 fall-through


        // 입력/미리보기 초기화는 폼 제출 시 자동으로 됨
      } catch (err) {
        console.error("공유 실패:", err);
        showToast("공유 실패: " + err.message, "error");
        // 공유 실패해도 내 피드에는 저장할지 여부? 일단 진행
      }
    }

    // ② 공유가 아닌 경우: 기존처럼 네이티브 폼 제출
    showToast("저장 중...", "info");
    setTimeout(() => {
      const form = document.getElementById("memo-form");
      if (!form) {
        showToast("폼을 찾을 수 없습니다", "error");
        submitting = false;
        $saveBtn.prop("disabled", false).removeClass("disabled").text("저장");
        return false;
      }
      const shareCheckbox = document.getElementById("share-public");
      if (shareCheckbox) shareCheckbox.remove();

      form.action = "";
      form.method = "post";
      form.enctype = "multipart/form-data";
      HTMLFormElement.prototype.submit.call(form);
    }, 200);
  } catch (e2) {
    console.error("제출 오류:", e2);
    showToast("오류: " + e2.message, "error");
    submitting = false;
    $saveBtn.prop("disabled", false).removeClass("disabled").text("저장");
  }

  return false;
});

const memoForm = $("#memo-form")[0];
if (memoForm) {
  console.log($._data(memoForm, "events")?.submit?.length);
}
/** ====== 환경설정 ====== */
const FEED_ID = "shimfeed-global-1";
const API_MEMO = "./api/memos.php";
const API_UPLD = "./api/upload.php";

/** ====== 유틸 ====== */
function escapeHtml(s) {
  return $("<div>")
    .text(s || "")
    .html();
}

/** ====== 피드 불러오기 & 렌더 ====== */
async function loadFeed() {
  try {
    const res = await fetch(
      `${API_MEMO}?feed_id=${encodeURIComponent(FEED_ID)}&limit=200`
    );
    const items = await res.json();
    renderFeed(items);
  } catch (e) {
    console.error(e);
    showToast("피드 불러오기 실패", "error");
  }
}

function renderFeed(items) {
  const $list = $("#feed-list");
  $list.empty();

  if (!items || !items.length) {
    $list.before(`
      <div class="feed-empty">
        <div class="feed-empty-text">
          아직 공유된 메모가 없어요<br/>
          메모를 작성할 때 <strong>'공유하기'</strong>를 체크해 보세요!
        </div>
      </div>
    `);
    return;
  }

  // 기존 empty 상태 제거
  $(".feed-empty").remove();

  const me =
    window.SHIM && window.SHIM.currentUserId
      ? String(window.SHIM.currentUserId)
      : "guest";

  items.forEach((it, idx) => {
    const isOwner = String(it.user_id || "guest") === me;
    const li = $(`
      <li class="memo" style="animation-delay:${idx * 60}ms">
        ${it.photo_url
        ? `<img src="${it.photo_url}" alt="" class="memo-img" />`
        : ""
      }
        ${it.text ? `<p class="memo-text">${escapeHtml(it.text)}</p>` : ""}
        <div class="memo-meta">
          <span class="author">${escapeHtml(it.author || "익명")}</span>
          <span class="date">${new Date(it.created_at).toLocaleString(
        "ko-KR"
      )}</span>
          ${isOwner
        ? `<button class="del btn danger small" data-id="${it.id}">삭제</button>`
        : ""
      }
        </div>
      </li>
    `);
    $list.append(li);
  });
}
/** ====== 메모 공유(저장) ====== */
// ✅ 최종본: JSON 바디로 전송
async function shareMemo({ text, photoUrl, author }) {
  const res = await fetch(API_MEMO, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      feed_id: FEED_ID,
      text,
      photo_url: photoUrl, // snake_case 유지
      author: author || "guest",
    }),
  });

  const ct = res.headers.get("content-type") || "";
  const payload = ct.includes("application/json")
    ? await res.json()
    : await res.text();
  if (!res.ok || (payload && payload.success === false)) {
    const msg = (payload && payload.message) || String(payload) || "저장 실패";
    throw new Error(msg);
  }
  return payload;
}

/** ====== 이미지 업로드 ====== */
// 🔽 여기에 넣으세요: JavaScript.js의 맨 아래쪽 또는 다른 함수들 정의 아래쪽
async function uploadFile(file) {
  const fd = new FormData();
  fd.append("file", file, file.name);
  const res = await fetch("./api/upload.php", {
    method: "POST",
    body: fd,
  });
  const raw = await res.text();
  console.log("UPLOAD raw:", raw);
  const json = JSON.parse(raw);
  if (!res.ok || !json.success) throw new Error(json.error || "업로드 실패");
  return json.url;
}
/** ====== 삭제 (memos.php: DELETE 지원) ====== */
async function deleteMemo(id) {
  const body = new URLSearchParams({ id });
  const res = await fetch(
    `${API_MEMO}?feed_id=${encodeURIComponent(FEED_ID)}`,
    {
      method: "DELETE",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body,
    }
  );

  // 컨텐트 타입에 따라 안전하게 파싱
  const ct = res.headers.get("content-type") || "";
  let payload = { ok: false };
  try {
    payload = ct.includes("application/json")
      ? await res.json()
      : { ok: false, error: await res.text() };
  } catch (e) {
    payload = { ok: false, error: `HTTP ${res.status}` };
  }

  if (!res.ok || !payload.ok) {
    throw new Error(payload.error || `HTTP ${res.status}`);
  }
  return payload;
}

/** ====== 이벤트 바인딩 ====== */
$(function () {
  // 초기 로드
  $(".feed-empty").remove(); // 중복 방지
  loadFeed();

  // 새로고침 버튼
  $("#btn-feed-reload").on("click", async function () {
    const $btn = $(this);
    $btn.prop("disabled", true);
    $(".feed-empty").remove();
    showToast("새로고침 중...", "info");
    await loadFeed();
    $btn.prop("disabled", false);
  });

  // 파일 선택 -> 업로드 -> URL hidden에 저장
  $("#photo").on("change", async function (e) {
    const file = e.target.files && e.target.files[0];
    if (!file) return;
    try {
      const url = await uploadFile(file);
      $("#memo-photo-url").val(url);
      showToast("이미지 업로드 완료", "success");
    } catch (err) {
      console.error(err);
      showToast(err.message, "error");
    }
  });

  // 공유하기
  $("#share-btn").on("click", async function () {
    const text = $("#memo-text").val();
    const author = $("#memo-author").val();
    const photoUrl = $("#memo-photo-url").val();

    try {
      await shareMemo({ text, photoUrl, author });
      showToast("공유 완료!", "success");

      // 입력값 초기화
      $("#memo-text").val("");
      $("#memo-author").val("");
      $("#memo-photo-url").val("");
      $("#photo").val("");

      // 새 목록
      await loadFeed();
    } catch (err) {
      console.error(err);
      showToast(err.message, "error");
    }
  });

  // 삭제 버튼 (이벤트 위임)
  $("#feed-list").on("click", ".del", async function () {
    const id = $(this).data("id");
    if (!id) return;
    if (!confirm("정말 삭제할까요?")) return;
    try {
      await deleteMemo(id);
      showToast("삭제 완료", "success");
      await loadFeed();
    } catch (err) {
      showToast(err.message, "error");
    }
  });
});
(function () {
  const isFeed = new URL(location.href).searchParams.get("page") === "feed";
  const feed = document.getElementById("feed-page");
  if (!feed) return;
  feed.style.display = isFeed ? "block" : "none";
  if (isFeed)
    document.querySelectorAll("main > section, main > div").forEach((el) => {
      if (el !== feed) el.style.display = "none";
    });
})();
