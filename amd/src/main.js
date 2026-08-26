/**
 * Split view loader for single section course pages.
 *
 * @module     format_edukav/main
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SELECTORS = {
  splitView: ".edukav-splitview",
  sidebar: ".edukav-sidebar",
  content: '[data-region="splitview-content"]',
  frame: '[data-region="splitview-frame"]',
  title: '[data-region="splitview-title"]',
  activity: ".activity.activity-wrapper",
  activityLink: ".activityname a, .activityname .aalink",
  actionMenu:
    '.action-menu a, [data-action="open-chooser"], [data-action="toggle"], .dropdown-toggle',
};

const GRADE_URL_PATTERNS = [
  /[?&]action=grader(?:&|$)/i,
  /[?&]action=grade(?:&|$)/i,
  /[?&]action=grading(?:&|$)/i,
  /\/grade(?:\.php|\/|$)/i,
  /\/grading(?:\.php|\/|$)/i,
];

const ASSIGN_PATH_PATTERN = /\/mod\/assign\/view\.php$/i;
const NORMAL_NAVIGATION_PATTERNS = [];

const SINGLE_SECTION_SELECTOR = ".single-section";
const ACTIVITY_RELOAD_GUARD_MS = 1500;
let activityLoadSequence = 0;
let resizeAnimationFrameId = 0;

const isEditingMode = () => {
  return (
    document.body.classList.contains("editing") ||
    document.body.classList.contains("editingon")
  );
};

const isPlainLeftClick = (event) => {
  return (
    event.button === 0 &&
    !event.metaKey &&
    !event.ctrlKey &&
    !event.shiftKey &&
    !event.altKey
  );
};

const getActivityLink = (activity) => {
  return activity.querySelector(SELECTORS.activityLink);
};

const setActiveActivity = (splitView, activityToActivate) => {
  splitView.querySelectorAll(SELECTORS.activity).forEach((activity) => {
    activity.classList.toggle("current", activity === activityToActivate);
    activity.classList.toggle(
      "edukav-activity-active",
      activity === activityToActivate
    );
  });
};

const updatePanel = (splitView, activityName = "") => {
  const title = splitView.querySelector(SELECTORS.title);

  if (title) {
    title.textContent = activityName || "Vista previa";
  }
};

const isGradingUrl = (url = "") => {
  try {
    const parsedUrl = new URL(url, window.location.href);
    const pathname = parsedUrl.pathname || "";
    const action = (parsedUrl.searchParams.get("action") || "").toLowerCase();

    if (ASSIGN_PATH_PATTERN.test(pathname)) {
      return action === "grader";
    }

    return GRADE_URL_PATTERNS.some((pattern) => pattern.test(url));
  } catch (e) {
    return GRADE_URL_PATTERNS.some((pattern) => pattern.test(url));
  }
};

const shouldOpenInNormalNavigation = (url = "") => {
  return NORMAL_NAVIGATION_PATTERNS.some((pattern) => pattern.test(url));
};

const getUrlWithoutContentOnly = (url = "") => {
  try {
    const parsedUrl = new URL(url, window.location.href);
    parsedUrl.searchParams.delete("contentonly");
    return parsedUrl.toString();
  } catch (e) {
    return url.replace(/([?&])contentonly=1(&?)/i, "").replace(/[?&]$/, "");
  }
};

const getNavigationUrl = (element) => {
  if (!element) {
    return "";
  }

  const link = element.closest("a[href], area[href]");
  if (link) {
    return link.getAttribute("href") || "";
  }

  const submitControl = element.closest(
    'button[type="submit"], input[type="submit"]'
  );
  if (submitControl) {
    const form = submitControl.form;
    return form?.getAttribute("action") || form?.action || "";
  }

  return "";
};

const redirectToTopWindowIfGrading = (event) => {
  const target = event.target instanceof Element ? event.target : null;
  const url = getNavigationUrl(target);

  if (!url) {
    return false;
  }

  if (!isGradingUrl(url)) {
    return false;
  }

  event.preventDefault();
  event.stopPropagation();
  window.top?.location.replace(getUrlWithoutContentOnly(url));
  return true;
};

const setupGradingRedirectHandlers = (splitView, doc) => {
  if (!doc || doc.documentElement.dataset.edukavGradingRedirectBound === "1") {
    return;
  }

  doc.documentElement.dataset.edukavGradingRedirectBound = "1";

  doc.addEventListener("pointerdown", redirectToTopWindowIfGrading, true);
  doc.addEventListener("mousedown", redirectToTopWindowIfGrading, true);
  doc.addEventListener("click", redirectToTopWindowIfGrading, true);
  doc.addEventListener(
    "submit",
    (event) => {
      const form = event.target instanceof HTMLFormElement ? event.target : null;
      const url = form?.getAttribute("action") || form?.action || "";

      if (!url || !isGradingUrl(url)) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();
      window.top?.location.replace(getUrlWithoutContentOnly(url));
    },
    true
  );

  splitView.dataset.edukavGradingRedirectBound = "1";
};

const getFrameDocumentUrl = (frame) => {
  try {
    return frame?.contentDocument?.location?.href || frame?.contentWindow?.location?.href || "";
  } catch (e) {
    return "";
  }
};

const setFrameLoadingState = (frame, isLoading) => {
  if (!frame) {
    return;
  }

  frame.style.visibility = isLoading ? "hidden" : "visible";
};

const scheduleSplitViewHeightSync = () => {
  if (resizeAnimationFrameId) {
    return;
  }

  const schedule =
    window.requestAnimationFrame ||
    window.setTimeout.bind(window);

  resizeAnimationFrameId = schedule(() => {
    resizeAnimationFrameId = 0;
    syncSplitViewHeight();
  });
};

const syncSplitViewHeight = () => {
  if (isEditingMode()) {
    return;
  }

  const viewportHeight = window.visualViewport?.height || window.innerHeight || 0;

  document.querySelectorAll(SINGLE_SECTION_SELECTOR).forEach((container) => {
    const rect = container.getBoundingClientRect();
    const availableHeight = Math.max(480, Math.floor(viewportHeight - rect.top - 8));

    container.style.setProperty(
      "--edukav-single-section-height",
      `${availableHeight}px`
    );
  });
};

const enableSplitViewPageMode = () => {
  if (!document.querySelector(SELECTORS.splitView)) {
    document.documentElement.classList.remove("edukav-splitview-page");
    document.body.classList.remove("edukav-splitview-page");
    return false;
  }

  if (isEditingMode()) {
    document.documentElement.classList.remove("edukav-splitview-page");
    document.body.classList.remove("edukav-splitview-page");
    return false;
  }

  document.documentElement.classList.add("edukav-splitview-page");
  document.body.classList.add("edukav-splitview-page");
  return true;
};

/**
 * Cargar actividad en iframe
 *
 * @param {HTMLElement} splitView
 * @param {HTMLElement} activity
 * @param {string} url
 * @param {string} activityName
 */
const loadActivity = (splitView, activity, url, activityName) => {
  const frame = splitView.querySelector(SELECTORS.frame);
  const content = splitView.querySelector(SELECTORS.content);

  if (!frame || !content || !url) {
    return;
  }

  if (isGradingUrl(url)) {
    window.top?.location.replace(getUrlWithoutContentOnly(url));
    return;
  }

  if (shouldOpenInNormalNavigation(url)) {
    window.top?.location.assign(getUrlWithoutContentOnly(url));
    return;
  }

  const separator = url.includes("?") ? "&" : "?";
  const finalUrl = url + separator + "contentonly=1";
  const requestId = `${++activityLoadSequence}`;
  const lastLoadedUrl = splitView.dataset.edukavLastLoadedUrl || "";
  const lastLoadedAt = Number(splitView.dataset.edukavLastLoadedAt || 0);
  const shouldSkipReload =
    lastLoadedUrl === finalUrl &&
    Date.now() - lastLoadedAt <= ACTIVITY_RELOAD_GUARD_MS;

  splitView.dataset.edukavPendingRequestId = requestId;
  frame.dataset.edukavRequestId = requestId;

  if (shouldSkipReload) {
    content.classList.remove("is-loading");
    setFrameLoadingState(frame, false);
    updatePanel(splitView, activityName);
    setActiveActivity(splitView, activity);
    return;
  }

  splitView.dataset.edukavLastLoadedUrl = finalUrl;
  splitView.dataset.edukavLastLoadedAt = `${Date.now()}`;

  content.classList.add("is-loading");
  setFrameLoadingState(frame, true);
  updatePanel(splitView, activityName);
  setActiveActivity(splitView, activity);

  frame.src = finalUrl;
};

/**
 * Eventos del iframe
 *
 * @param {HTMLElement} splitView
 */
const setupFrameEvents = (splitView) => {
  const frame = splitView.querySelector(SELECTORS.frame);
  const content = splitView.querySelector(SELECTORS.content);

  if (!frame || !content) {
    return;
  }

  frame.addEventListener("load", () => {
    const currentRequestId = splitView.dataset.edukavPendingRequestId || "";
    const frameRequestId = frame.dataset.edukavRequestId || "";

    if (currentRequestId && frameRequestId && currentRequestId !== frameRequestId) {
      return;
    }

    content.classList.remove("is-loading");
    setFrameLoadingState(frame, false);

    try {
      const doc = frame.contentDocument;
      const frameUrl = getFrameDocumentUrl(frame);

      setupGradingRedirectHandlers(splitView, doc);

      if (frameUrl && isGradingUrl(frameUrl)) {
        window.top?.location.replace(getUrlWithoutContentOnly(frameUrl));
        return;
      }

      if (!doc) {
        return;
      }

      const target = doc.querySelector("#topofscroll");

      if (target && doc.body) {
        doc.body.innerHTML = "";
        doc.body.appendChild(target);

        doc.body.style.margin = "0";
        doc.body.style.padding = "20px";
        doc.body.style.background = "#fff";

        target.style.display = "block";
        target.style.maxWidth = "1100px";
        target.style.margin = "0 auto";
      }

      const frameTitle = doc.title || "";

      if (frameTitle) {
        const cleanedTitle = frameTitle.split(":").pop().trim();
        updatePanel(splitView, cleanedTitle);
      }
    } catch (e) {
      return;
    }
  });

  frame.addEventListener("error", () => {
    content.classList.remove("is-loading");
    setFrameLoadingState(frame, false);
  });
};

const shouldIgnoreClick = (event, splitView) => {
  if (!isPlainLeftClick(event)) {
    return true;
  }

  if (event.defaultPrevented) {
    return true;
  }

  if (event.target.closest(SELECTORS.actionMenu)) {
    return true;
  }

  return (
    !event.target.closest(
      `${SELECTORS.sidebar} ${SELECTORS.activityLink}`
    ) || !splitView.contains(event.target)
  );
};

/**
 * Inicializar split view
 *
 * @param {HTMLElement} splitView
 */
const initSplitView = (splitView) => {
  setupFrameEvents(splitView);

  splitView.addEventListener("click", (event) => {
    if (shouldIgnoreClick(event, splitView)) {
      return;
    }

    const link = event.target.closest(SELECTORS.activityLink);
    const activity = event.target.closest(SELECTORS.activity);

    if (!link || !activity) {
      return;
    }

    const url = link.getAttribute("href");

    if (!url || url.startsWith("#")) {
      return;
    }

    if (isGradingUrl(url)) {
      event.preventDefault();
      window.top?.location.replace(getUrlWithoutContentOnly(url));
      return;
    }

    if (shouldOpenInNormalNavigation(url)) {
      event.preventDefault();
      window.top?.location.assign(getUrlWithoutContentOnly(url));
      return;
    }

    event.preventDefault();

    loadActivity(
      splitView,
      activity,
      url,
      link.textContent.trim()
    );
  });

  const firstActivity = splitView.querySelector(SELECTORS.activity);
  const firstLink = firstActivity
    ? getActivityLink(firstActivity)
    : null;

  if (firstActivity && firstLink && firstLink.getAttribute("href")) {
    const firstUrl = firstLink.getAttribute("href");

    if (!shouldOpenInNormalNavigation(firstUrl)) {
      loadActivity(
        splitView,
        firstActivity,
        firstUrl,
        firstLink.textContent.trim()
      );
    }
  }
};

export const init = () => {
  const splitViews = document.querySelectorAll(SELECTORS.splitView);

  if (!splitViews.length) {
    document.documentElement.classList.remove("edukav-splitview-page");
    document.body.classList.remove("edukav-splitview-page");
    return;
  }

  const splitViewPageModeEnabled = enableSplitViewPageMode();
  splitViews.forEach(initSplitView);
  if (splitViewPageModeEnabled) {
    syncSplitViewHeight();
  }

  if (!window.__edukavSplitViewResizeBound) {
    window.__edukavSplitViewResizeBound = true;
    window.addEventListener("resize", scheduleSplitViewHeightSync, {
      passive: true,
    });
    window.addEventListener("orientationchange", scheduleSplitViewHeightSync, {
      passive: true,
    });
  }
};

export default {
  init,
};
