// content/injected.js
// Runs in the MAIN world (not isolated extension world).
// Purpose: detect internal WA objects when available (fallback), without relying on them.
//
// We DO NOT depend on internal APIs for core functionality (DOM is primary).
(() => {
  try {
    const info = {
      hasStore: Boolean(window.Store),
      hasRequire: typeof window.require === "function",
      hasWebpackChunk:
        typeof window.webpackChunkwhatsapp_web_client !== "undefined" ||
        typeof window.webpackChunkbuild !== "undefined" ||
        typeof window.webpackChunkwhatsapp_web !== "undefined",
      ua: navigator.userAgent
    };

    window.postMessage({
      source: "WHL",
      type: "INJECTED_STATUS",
      info
    }, "*");
  } catch (e) {
    window.postMessage({
      source: "WHL",
      type: "INJECTED_STATUS",
      info: { error: String(e?.message || e) }
    }, "*");
  }
})();
