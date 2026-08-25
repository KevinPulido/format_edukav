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
  preview: '[data-region="splitview-preview"]',
  previewContent: '[data-region="splitview-preview-content"]',
  loading: '[data-region="splitview-loading"]',
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
const BASE_BODY_CLASSES = new Set(Array.from(document.body.classList));
const activityCache = new Map();
const loadedScriptSrcs = new Set();
let appliedBodyClasses = [];

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

const cleanText = (value = "") => {
  return String(value)
    .replace(/\s+/g, " ")
    .trim();
};

const escapeHtml = (value = "") => {
  return String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
};

const getActivityLink = (activity) => {
  return activity ? activity.querySelector(SELECTORS.activityLink) : null;
};

const getEndpointUrl = (splitView) => {
  return (
    splitView?.dataset?.activityContentUrl ||
    splitView?.closest("[data-activity-content-url]")?.dataset
      ?.activityContentUrl ||
    "/course/format/edukav/ajax/activity_content.php"
  );
};

const getSessionKey = () => {
  if (window.M && window.M.cfg && window.M.cfg.sesskey) {
    return window.M.cfg.sesskey;
  }

  const hiddenSesskey = document.querySelector('input[name="sesskey"]');
  return hiddenSesskey ? hiddenSesskey.value : "";
};

const getActivityCmid = (activity, link) => {
  const candidates = [
    activity?.dataset?.cmid,
    activity?.dataset?.id,
    link?.dataset?.cmid,
    link?.dataset?.id,
  ];

  for (const candidate of candidates) {
    const value = parseInt(candidate, 10);
    if (Number.isInteger(value) && value > 0) {
      return value;
    }
  }

  const href = link?.getAttribute("href") || "";
  if (!href) {
    return 0;
  }

  try {
    const parsedUrl = new URL(href, window.location.href);
    const urlcmid = parseInt(parsedUrl.searchParams.get("id") || "", 10);
    return Number.isInteger(urlcmid) && urlcmid > 0 ? urlcmid : 0;
  } catch (e) {
    const match = href.match(/[?&]id=(\d+)/i);
    return match ? parseInt(match[1], 10) : 0;
  }
};

const getActivityLabel = (activity, link, fallback = "") => {
  const candidates = [
    activity?.dataset?.activityname,
    activity?.querySelector(".instancename")?.textContent,
    link?.querySelector(".instancename")?.textContent,
    link?.textContent,
    fallback,
  ];

  for (const candidate of candidates) {
    const value = cleanText(candidate || "");
    if (value) {
      return value;
    }
  }

  return "";
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
  return isGradingUrl(url);
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

const setActiveActivity = (splitView, activityToActivate) => {
  splitView.querySelectorAll(SELECTORS.activity).forEach((activity) => {
    activity.classList.toggle("current", activity === activityToActivate);
    activity.classList.toggle(
      "edukav-activity-active",
      activity === activityToActivate
    );
  });
};

const setLoadingState = (splitView, isLoading) => {
  const preview = splitView.querySelector(SELECTORS.preview);
  const loading = splitView.querySelector(SELECTORS.loading);
  const content = splitView.querySelector(SELECTORS.previewContent);

  if (preview) {
    preview.setAttribute("aria-busy", isLoading ? "true" : "false");
  }

  if (loading) {
    loading.hidden = !isLoading;
  }

  if (content) {
    content.classList.toggle("is-loading", isLoading);
  }
};

const resetBodyClasses = () => {
  const currentClasses = Array.from(appliedBodyClasses);
  currentClasses.forEach((className) => {
    if (!BASE_BODY_CLASSES.has(className)) {
      document.body.classList.remove(className);
    }
  });
  appliedBodyClasses = [];
};

const applyBodyClasses = (classes) => {
  resetBodyClasses();

  const normalized = Array.isArray(classes)
    ? classes
    : cleanText(classes || "")
        .split(/\s+/)
        .filter(Boolean);

  normalized.forEach((className) => {
    if (!BASE_BODY_CLASSES.has(className)) {
      document.body.classList.add(className);
      appliedBodyClasses.push(className);
    }
  });
};

const parseScriptNode = (html) => {
  const template = document.createElement("template");
  template.innerHTML = html || "";
  return template.content.firstElementChild;
};

const appendScriptElement = (container, sourceScript) => {
  if (!sourceScript) {
    return Promise.resolve();
  }

  const targetScript = document.createElement("script");
  Array.from(sourceScript.attributes).forEach((attribute) => {
    targetScript.setAttribute(attribute.name, attribute.value);
  });

  if (sourceScript.src) {
    const normalizedSrc = sourceScript.src;
    if (loadedScriptSrcs.has(normalizedSrc)) {
      return Promise.resolve();
    }

    loadedScriptSrcs.add(normalizedSrc);
    targetScript.src = normalizedSrc;

    const shouldWait =
      !sourceScript.hasAttribute("async") && !sourceScript.hasAttribute("defer");
    const promise = shouldWait
      ? new Promise((resolve, reject) => {
          targetScript.addEventListener("load", resolve, { once: true });
          targetScript.addEventListener("error", reject, { once: true });
        })
      : Promise.resolve();

    container.appendChild(targetScript);
    return promise;
  }

  targetScript.textContent = sourceScript.textContent || "";
  container.appendChild(targetScript);
  return Promise.resolve();
};

const injectScripts = async (container, scripts = []) => {
  for (const scriptHtml of scripts) {
    const sourceScript = parseScriptNode(scriptHtml);
    if (!sourceScript || sourceScript.tagName !== "SCRIPT") {
      continue;
    }

    try {
      await appendScriptElement(container, sourceScript);
    } catch (e) {
      // Optional scripts should not block rendering.
    }
  }
};

const renderPreview = async (splitView, response) => {
  const content = splitView.querySelector(SELECTORS.previewContent);
  const secondaryHtml = response?.secondaryHtml || "";
  const mainHtml = response?.mainHtml || "";
  const scripts = Array.isArray(response?.scripts) ? response.scripts : [];

  if (!content) {
    return;
  }

  content.innerHTML = `
    <div class="edukav-activity-preview-secondary">
      ${secondaryHtml}
    </div>
    <div class="edukav-activity-preview-main">
      ${mainHtml}
    </div>
  `;

  applyBodyClasses(response?.bodyClasses || []);
  await injectScripts(content, scripts);
  setLoadingState(splitView, false);
};

const renderError = (splitView, message) => {
  const content = splitView.querySelector(SELECTORS.previewContent);
  if (content) {
    content.innerHTML = `
      <div class="edukav-preview-error" role="alert">
        <strong>No fue posible cargar la actividad.</strong>
        <div>${escapeHtml(message)}</div>
      </div>
  `;
  }

  resetBodyClasses();
  setLoadingState(splitView, false);
};

const fetchActivityContent = async (endpointUrl, cmid) => {
  const body = new URLSearchParams();
  body.set("cmid", String(cmid));
  body.set("sesskey", getSessionKey());

  const response = await fetch(endpointUrl, {
    method: "POST",
    credentials: "include",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      "X-Requested-With": "XMLHttpRequest",
    },
    body: body.toString(),
  });

  const payload = await response.json();
  if (!response.ok || !payload || payload.success !== true) {
    const message = payload?.message || `HTTP ${response.status}`;
    throw new Error(message);
  }

  return payload;
};

const loadActivity = async (
  splitView,
  activity,
  link,
  url,
  cmid,
  activityName
) => {
  const endpointUrl = getEndpointUrl(splitView);
  const cacheKey = String(cmid || url || activityName);
  const cachedResponse = activityCache.get(cacheKey);

  if (isGradingUrl(url)) {
    window.top?.location.replace(getUrlWithoutContentOnly(url));
    return;
  }

  setActiveActivity(splitView, activity);
  setLoadingState(splitView, true);

  if (cachedResponse) {
    await renderPreview(splitView, cachedResponse);
    return;
  }

  try {
    const payload = await fetchActivityContent(endpointUrl, cmid);
    activityCache.set(cacheKey, payload);
    await renderPreview(splitView, payload);
  } catch (error) {
    renderError(splitView, error.message || "Hubo un problema inesperado.");
  }
};

const shouldIgnoreClick = (event, splitView) => {
  if (!isPlainLeftClick(event)) {
    return true;
  }

  if (event.defaultPrevented) {
    return true;
  }

  const target = event.target instanceof Element ? event.target : null;
  if (!target) {
    return true;
  }

  if (target.closest(SELECTORS.actionMenu)) {
    return true;
  }

  return !target.closest(`${SELECTORS.sidebar} ${SELECTORS.activityLink}`) ||
    !splitView.contains(target);
};

const initSplitView = (splitView) => {
  splitView.addEventListener("click", (event) => {
    if (shouldIgnoreClick(event, splitView)) {
      return;
    }

    const target = event.target instanceof Element ? event.target : null;
    const link = target ? target.closest(SELECTORS.activityLink) : null;
    const activity = target ? target.closest(SELECTORS.activity) : null;

    if (!link || !activity) {
      return;
    }

    const url = link.getAttribute("href") || "";
    if (!url || url.startsWith("#") || link.hasAttribute("download")) {
      return;
    }

    if (link.target && link.target !== "_self") {
      return;
    }

    if (shouldOpenInNormalNavigation(url)) {
      event.preventDefault();
      window.top?.location.assign(getUrlWithoutContentOnly(url));
      return;
    }

    const cmid = getActivityCmid(activity, link);
    if (!cmid) {
      return;
    }

    event.preventDefault();
    void loadActivity(
      splitView,
      activity,
      link,
      url,
      cmid,
      getActivityLabel(activity, link, link.textContent)
    );
  });

  const firstActivity = splitView.querySelector(SELECTORS.activity);
  const firstLink = getActivityLink(firstActivity);
  if (firstActivity && firstLink) {
    const firstUrl = firstLink.getAttribute("href") || "";
    const firstCmid = getActivityCmid(firstActivity, firstLink);
    if (firstUrl && firstCmid && !shouldOpenInNormalNavigation(firstUrl)) {
      void loadActivity(
        splitView,
        firstActivity,
        firstLink,
        firstUrl,
        firstCmid,
        getActivityLabel(firstActivity, firstLink, firstLink.textContent)
      );
    }
  }
};

const syncSplitViewHeight = () => {
  if (isEditingMode()) {
    return;
  }

  const viewportHeight = window.visualViewport?.height || window.innerHeight || 0;

  document.querySelectorAll(".single-section").forEach((container) => {
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
    window.addEventListener("resize", syncSplitViewHeight, { passive: true });
    window.addEventListener("orientationchange", syncSplitViewHeight, {
      passive: true,
    });
  }
};

export default {
  init,
};
