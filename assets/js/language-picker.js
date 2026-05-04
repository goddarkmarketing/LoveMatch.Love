/**
 * Language picker modal + Google Translate (page source: Thai).
 * Modal has translate="no" so UI stays readable while the rest of the page can translate.
 */
(function (global) {
  "use strict";

  /** g = Google Translate language code; code = short display id (e.g. us = English) */
  var LANGS = [
    { g: "th", code: "th", native: "ไทย", en: "Thai" },
    { g: "en", code: "us", native: "English", en: "English" },
    { g: "zh-CN", code: "cn", native: "中文简体", en: "Chinese (Simplified)" },
    { g: "zh-TW", code: "tw", native: "中文繁體", en: "Chinese (Traditional)" },
    { g: "ja", code: "jp", native: "日本語", en: "Japanese" },
    { g: "ko", code: "kr", native: "한국어", en: "Korean" },
    { g: "vi", code: "vn", native: "Tiếng Việt", en: "Vietnamese" },
    { g: "id", code: "id", native: "Bahasa Indonesia", en: "Indonesian" },
    { g: "ms", code: "my", native: "Bahasa Melayu", en: "Malay" },
    { g: "tl", code: "ph", native: "Filipino", en: "Filipino (Tagalog)" },
    { g: "hi", code: "in", native: "हिन्दी", en: "Hindi" },
    { g: "ar", code: "sa", native: "العربية", en: "Arabic" },
    { g: "tr", code: "tr", native: "Türkçe", en: "Turkish" },
    { g: "ru", code: "ru", native: "Русский", en: "Russian" },
    { g: "es", code: "es", native: "Español", en: "Spanish" },
    { g: "fr", code: "fr", native: "Français", en: "French" },
    { g: "de", code: "de", native: "Deutsch", en: "German" },
    { g: "it", code: "it", native: "Italiano", en: "Italian" },
    { g: "pt", code: "br", native: "Português (Brasil)", en: "Portuguese (Brazil)" },
    { g: "pt", code: "pt", native: "Português", en: "Portuguese" },
    { g: "pl", code: "pl", native: "Polski", en: "Polish" },
    { g: "nl", code: "nl", native: "Nederlands", en: "Dutch" },
    { g: "sv", code: "se", native: "Svenska", en: "Swedish" },
    { g: "no", code: "no", native: "Norsk", en: "Norwegian" },
    { g: "da", code: "dk", native: "Dansk", en: "Danish" },
    { g: "fi", code: "fi", native: "Suomi", en: "Finnish" },
    { g: "cs", code: "cz", native: "Čeština", en: "Czech" },
    { g: "sk", code: "sk", native: "Slovenčina", en: "Slovak" },
    { g: "ro", code: "ro", native: "Română", en: "Romanian" },
    { g: "hu", code: "hu", native: "Magyar", en: "Hungarian" },
    { g: "el", code: "gr", native: "Ελληνικά", en: "Greek" },
    { g: "he", code: "il", native: "עברית", en: "Hebrew" },
    { g: "uk", code: "ua", native: "Українська", en: "Ukrainian" },
    { g: "bg", code: "bg", native: "Български", en: "Bulgarian" },
    { g: "hr", code: "hr", native: "Hrvatski", en: "Croatian" },
    { g: "sr", code: "rs", native: "Српски", en: "Serbian" },
    { g: "sl", code: "si", native: "Slovenščina", en: "Slovenian" },
    { g: "lt", code: "lt", native: "Lietuvių", en: "Lithuanian" },
    { g: "lv", code: "lv", native: "Latviešu", en: "Latvian" },
    { g: "et", code: "ee", native: "Eesti", en: "Estonian" },
    { g: "is", code: "is", native: "Íslenska", en: "Icelandic" },
    { g: "ga", code: "ie", native: "Gaeilge", en: "Irish" },
    { g: "mt", code: "mt", native: "Malti", en: "Maltese" },
    { g: "sw", code: "ke", native: "Kiswahili", en: "Swahili" },
    { g: "zu", code: "za", native: "isiZulu", en: "Zulu" },
    { g: "af", code: "za2", native: "Afrikaans", en: "Afrikaans" },
    { g: "am", code: "am", native: "አማርኛ", en: "Amharic" },
    { g: "bn", code: "bd", native: "বাংলা", en: "Bengali" },
    { g: "ta", code: "lk", native: "தமிழ்", en: "Tamil" },
    { g: "te", code: "in2", native: "తెలుగు", en: "Telugu" },
    { g: "mr", code: "in3", native: "मराठी", en: "Marathi" },
    { g: "gu", code: "in4", native: "ગુજરાતી", en: "Gujarati" },
    { g: "kn", code: "in5", native: "ಕನ್ನಡ", en: "Kannada" },
    { g: "ml", code: "in6", native: "മലയാളം", en: "Malayalam" },
    { g: "pa", code: "in7", native: "ਪੰਜਾਬੀ", en: "Punjabi" },
    { g: "ur", code: "pk", native: "اردو", en: "Urdu" },
    { g: "fa", code: "ir", native: "فارسی", en: "Persian" },
    { g: "ne", code: "np", native: "नेपाली", en: "Nepali" },
    { g: "si", code: "lk2", native: "සිංහල", en: "Sinhala" },
    { g: "my", code: "mm", native: "မြန်မာ", en: "Burmese" },
    { g: "km", code: "kh", native: "ខ្មែរ", en: "Khmer" },
    { g: "lo", code: "la", native: "ລາວ", en: "Lao" },
    { g: "mn", code: "mn", native: "Монгол", en: "Mongolian" },
    { g: "ka", code: "ge", native: "ქართული", en: "Georgian" },
    { g: "hy", code: "hy", native: "Հայերեն", en: "Armenian" },
    { g: "az", code: "az", native: "Azərbaycanca", en: "Azerbaijani" },
    { g: "kk", code: "kz", native: "Қазақша", en: "Kazakh" },
    { g: "uz", code: "uz", native: "Oʻzbek", en: "Uzbek" },
    { g: "ky", code: "kg", native: "Кыргызча", en: "Kyrgyz" },
    { g: "tg", code: "tj", native: "Тоҷикӣ", en: "Tajik" },
    { g: "ps", code: "ps", native: "پښتو", en: "Pashto" },
    { g: "sq", code: "al", native: "Shqip", en: "Albanian" },
    { g: "mk", code: "mk", native: "Македонски", en: "Macedonian" },
    { g: "bs", code: "ba", native: "Bosanski", en: "Bosnian" },
    { g: "ca", code: "cat", native: "Català", en: "Catalan" },
    { g: "eu", code: "eus", native: "Euskara", en: "Basque" },
    { g: "gl", code: "gal", native: "Galego", en: "Galician" },
    { g: "cy", code: "cy", native: "Cymraeg", en: "Welsh" },
    { g: "gd", code: "gd", native: "Gàidhlig", en: "Scottish Gaelic" },
    { g: "lb", code: "lu", native: "Lëtzebuergesch", en: "Luxembourgish" },
    { g: "ceb", code: "ph2", native: "Cebuano", en: "Cebuano" },
    { g: "haw", code: "haw", native: "ʻŌlelo Hawaiʻi", en: "Hawaiian" },
    { g: "sm", code: "ws", native: "Gagana Samoa", en: "Samoan" },
    { g: "mi", code: "nz", native: "Te Reo Māori", en: "Māori" },
    { g: "so", code: "so", native: "Soomaali", en: "Somali" },
    { g: "ha", code: "ng", native: "Hausa", en: "Hausa" },
    { g: "yo", code: "yo", native: "Yorùbá", en: "Yoruba" },
    { g: "ig", code: "ig", native: "Igbo", en: "Igbo" },
    { g: "xh", code: "xh", native: "isiXhosa", en: "Xhosa" },
    { g: "mg", code: "mg", native: "Malagasy", en: "Malagasy" },
    { g: "ny", code: "mw", native: "Chichewa", en: "Chichewa" },
    { g: "co", code: "co", native: "Corsu", en: "Corsican" },
    { g: "la", code: "la", native: "Latina", en: "Latin" },
    { g: "eo", code: "eo", native: "Esperanto", en: "Esperanto" },
    { g: "jv", code: "jv", native: "Basa Jawa", en: "Javanese" },
    { g: "su", code: "su", native: "Basa Sunda", en: "Sundanese" },
    { g: "be", code: "by", native: "Беларуская", en: "Belarusian" },
    { g: "tk", code: "tm", native: "Türkmençe", en: "Turkmen" },
    { g: "yi", code: "yi", native: "ייִדיש", en: "Yiddish" },
    { g: "fy", code: "fy", native: "Frysk", en: "Frisian" },
    { g: "sn", code: "zw", native: "chiShona", en: "Shona" },
    { g: "ku", code: "ku", native: "Kurdî", en: "Kurdish" },
    { g: "ug", code: "ug", native: "ئۇيغۇرچە", en: "Uyghur" }
  ];

  var backdrop = null;
  var gridEl = null;
  var searchEl = null;
  var gtLoaded = false;

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function clearGoogTransCookies() {
    var expires = "Thu, 01 Jan 1970 00:00:00 GMT";
    var host = location.hostname;
    document.cookie = "googtrans=;expires=" + expires + ";path=/";
    document.cookie = "googtrans=;expires=" + expires + ";path=/;domain=" + host;
    if (host.indexOf(".") !== -1) {
      document.cookie = "googtrans=;expires=" + expires + ";path=/;domain=." + host;
    }
  }

  function applyLanguage(googleCode) {
    if (googleCode === "th") {
      clearGoogTransCookies();
      location.reload();
      return;
    }
    document.cookie = "googtrans=/th/" + googleCode + ";path=/";
    location.reload();
  }

  function filterLangs(q) {
    q = (q || "").trim().toLowerCase();
    if (!q) {
      return LANGS.slice();
    }
    return LANGS.filter(function (L) {
      return (
        L.code.toLowerCase().indexOf(q) !== -1 ||
        L.g.toLowerCase().indexOf(q) !== -1 ||
        L.native.toLowerCase().indexOf(q) !== -1 ||
        L.en.toLowerCase().indexOf(q) !== -1
      );
    });
  }

  function renderGrid() {
    if (!gridEl) {
      return;
    }
    var list = filterLangs(searchEl ? searchEl.value : "");
    gridEl.innerHTML = "";
    if (!list.length) {
      var empty = document.createElement("div");
      empty.className = "lm-lang-empty";
      empty.textContent = "ไม่พบภาษาที่ค้นหา";
      gridEl.appendChild(empty);
      return;
    }
    list.forEach(function (L) {
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "lm-lang-btn";
      btn.innerHTML =
        '<span class="lm-lang-btn-code">' + escapeHtml(L.code) + "</span>" +
        '<span class="lm-lang-btn-native">' + escapeHtml(L.native) + "</span>" +
        '<span class="lm-lang-btn-en">(' + escapeHtml(L.en) + ")</span>";
      btn.addEventListener("click", function () {
        applyLanguage(L.g);
      });
      gridEl.appendChild(btn);
    });
  }

  function close() {
    if (backdrop) {
      backdrop.classList.remove("is-open");
    }
    document.body.classList.remove("lm-lang-modal-open");
  }

  function open() {
    ensureModal();
    renderGrid();
    backdrop.classList.add("is-open");
    document.body.classList.add("lm-lang-modal-open");
    if (searchEl) {
      searchEl.value = "";
      searchEl.focus();
    }
  }

  function ensureModal() {
    if (backdrop) {
      return;
    }
    backdrop = document.createElement("div");
    backdrop.className = "lm-lang-backdrop";
    backdrop.setAttribute("translate", "no");
    backdrop.innerHTML =
      '<div class="lm-lang-modal notranslate" translate="no">' +
      '<div class="lm-lang-head">' +
      '<h2 class="lm-lang-head-title"><span class="lm-lang-head-dot" aria-hidden="true"></span>เลือกภาษา (100+ ภาษา)</h2>' +
      '<button type="button" class="lm-lang-close" aria-label="ปิด">×</button>' +
      "</div>" +
      '<div class="lm-lang-search-wrap">' +
      '<input type="search" class="lm-lang-search" placeholder="ค้นหาภาษา..." autocomplete="off">' +
      "</div>" +
      '<div class="lm-lang-grid-wrap"><div class="lm-lang-grid"></div></div>' +
      "</div>";

    document.body.appendChild(backdrop);
    gridEl = backdrop.querySelector(".lm-lang-grid");
    searchEl = backdrop.querySelector(".lm-lang-search");

    backdrop.addEventListener("click", function (e) {
      if (e.target === backdrop) {
        close();
      }
    });
    backdrop.querySelector(".lm-lang-close").addEventListener("click", close);
    backdrop.querySelector(".lm-lang-modal").addEventListener("click", function (e) {
      e.stopPropagation();
    });

    searchEl.addEventListener("input", function () {
      renderGrid();
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && backdrop && backdrop.classList.contains("is-open")) {
        close();
      }
    });
  }

  function ensureGoogleTranslateHook() {
    if (gtLoaded) {
      return;
    }
    if (!document.getElementById("google_translate_element")) {
      var d = document.createElement("div");
      d.id = "google_translate_element";
      document.body.appendChild(d);
    }
    gtLoaded = true;
  }

  function onTranslateReady() {
    ensureGoogleTranslateHook();
    var g = window.google;
    if (!g || !g.translate || !g.translate.TranslateElement) {
      return;
    }
    try {
      new g.translate.TranslateElement(
        {
          pageLanguage: "th",
          layout: g.translate.TranslateElement.InlineLayout.SIMPLE
        },
        "google_translate_element"
      );
    } catch (err) {
      console.warn("Google Translate init:", err);
    }
  }

  global.LoveMatchLanguagePicker = {
    open: open,
    close: close,
    onTranslateReady: onTranslateReady,
    applyLanguage: applyLanguage,
    clearTranslation: function () {
      applyLanguage("th");
    }
  };
})(window);
