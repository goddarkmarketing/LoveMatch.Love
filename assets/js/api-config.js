/**
 * API base URL + shared fetch helper.
 * Optional: <meta name="lovematch-api-base" content="https://your-host/subdir"> when the API root
 * is not at origin + app-folder + "/api" (e.g. API on another subdomain).
 */
(function (global) {
  "use strict";

  function metaApiBase() {
    var m = global.document.querySelector('meta[name="lovematch-api-base"]');
    if (!m) {
      return null;
    }
    var c = (m.getAttribute("content") || "").trim();
    return c ? c.replace(/\/$/, "") : null;
  }

  function resolveAppBasePath(pathname, entryHtmlFilename) {
    var p = pathname || "/";
    if (entryHtmlFilename) {
      var esc = String(entryHtmlFilename).replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
      p = p.replace(new RegExp("\\/" + esc + "$"), "");
    }
    return p.replace(/\/$/, "");
  }

  function resolveApiBase(pathname, entryHtmlFilename) {
    var fromMeta = metaApiBase();
    if (fromMeta) {
      return fromMeta;
    }
    var app = resolveAppBasePath(pathname, entryHtmlFilename);
    return global.location.origin + (app || "") + "/api";
  }

  async function defaultApiFetch(apiBase, path, options) {
    var response = await global.fetch(apiBase + path, Object.assign(
      {
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" }
      },
      options || {}
    ));

    var text = await response.text();
    var data = {};
    if (text) {
      try {
        data = JSON.parse(text);
      } catch (ignore) {
        var hint = text.length > 160 ? text.slice(0, 160) + "…" : text;
        hint = hint.replace(/\s+/g, " ").trim();
        throw new global.Error(
          hint
            ? "ตอบกลับไม่ใช่ JSON (HTTP " + response.status + "): " + hint
            : "ตอบกลับไม่ใช่ JSON (HTTP " + response.status + ")"
        );
      }
    }

    if (!response.ok) {
      throw new global.Error(data.message || "HTTP " + response.status);
    }
    if (data && data.success === false) {
      throw new global.Error(data.message || "เกิดข้อผิดพลาด");
    }
    return data;
  }

  global.LoveMatchApiConfig = {
    apiBase: resolveApiBase,
    appBasePath: resolveAppBasePath,
    createApiFetch: function (apiBase) {
      return function (path, options) {
        return defaultApiFetch(apiBase, path, options);
      };
    }
  };
})(typeof window !== "undefined" ? window : this);
