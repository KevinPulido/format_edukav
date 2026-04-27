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
  activityLink: ".activityname a, .aalink",
  actionMenu:
    '.action-menu a, [data-action="open-chooser"], [data-action="toggle"], .dropdown-toggle',
};

const TEXT = {
  empty: "Selecciona una actividad para verla aqui.",
  loading: "Cargando actividad...",
  error: "No fue posible cargar esta actividad.",
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
    loadActivity(
      splitView,
      firstActivity,
      firstLink.getAttribute("href"),
      firstLink.textContent.trim()
    );
  }
};

export const init = () => {
  document.querySelectorAll(SELECTORS.splitView).forEach(initSplitView);
};

export default {
  init,
};