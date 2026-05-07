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
  status: '[data-region="splitview-status"]',
  activity: ".activity.activity-wrapper",
  activityLink: ".activityname a, .activityname .aalink",
  actionMenu:
    '.action-menu a, [data-action="open-chooser"], [data-action="toggle"], .dropdown-toggle',
};

const TEXT = {
  empty: "Selecciona una actividad para verla aqui.",
  loading: "Cargando actividad...",
  error: "No fue posible cargar esta actividad.",
};

const GRADE_URL_PATTERNS = [
  /[?&]action=grader(?:&|$)/i,
  /[?&]action=grade(?:&|$)/i,
  /[?&]action=grading(?:&|$)/i,
  /\/grade(?:\.php|\/|$)/i,
  /\/grading(?:\.php|\/|$)/i,
];

const ASSIGN_PATH_PATTERN = /\/mod\/assign\/view\.php$/i;
const NORMAL_NAVIGATION_PATTERNS = [
  /\/mod\/h5pactivity\/view\.php(?:$|\?)/i,
  /\/mod\/scorm\/view\.php(?:$|\?)/i,
];

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

const updatePanel = (splitView, state, activityName = "") => {
  const title = splitView.querySelector(SELECTORS.title);
  const status = splitView.querySelector(SELECTORS.status);

  if (title) {
    title.textContent = activityName || "Vista previa";
  }

  if (status) {
    status.textContent = TEXT[state] || "";
    status.hidden = !TEXT[state];
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

  content.classList.add("is-loading");
  updatePanel(splitView, "loading", activityName);
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
    content.classList.remove("is-loading");

    try {
      const doc = frame.contentDocument;
      const frameUrl = getFrameDocumentUrl(frame);

      setupGradingRedirectHandlers(splitView, doc);

      if (frameUrl && isGradingUrl(frameUrl)) {
        window.top?.location.replace(getUrlWithoutContentOnly(frameUrl));
        return;
      }

      if (!doc) {
        updatePanel(splitView, "");
        return;
      }

      /**
       * 🔥 MOSTRAR SOLO #topofscroll
       */
      const target = doc.querySelector("#topofscroll");

      if (target) {
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
        updatePanel(splitView, "", cleanedTitle);
      } else {
        updatePanel(splitView, "");
      }
    } catch (e) {
      updatePanel(splitView, "");
    }
  });

  frame.addEventListener("error", () => {
    content.classList.remove("is-loading");
    updatePanel(splitView, "error");
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
  updatePanel(splitView, "empty");

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
  document.querySelectorAll(SELECTORS.splitView).forEach(initSplitView);
};

export default {
  init,
};
