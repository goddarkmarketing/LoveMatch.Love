/**
 * Floating contact button (bottom-right). Same links as site footer on index.html.
 */
(function () {
  if (document.getElementById("floatingContactHost")) {
    return;
  }

  var CONTACT = {
    line: "https://line.me/ti/p/ZXG7M5U6rd",
    facebook: "https://www.facebook.com/datethaigirl",
    email: "thailovematch.love@gmail.com"
  };

  var icoLine =
    '<svg class="fc-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.5 10.5c0 3.9-3.8 7.2-8.5 7.2h-2l-2.8 2.8c-.2.2-.5 0-.4-.3l.7-2.5C4.8 16.5 3.5 14.2 3.5 10.5 3.5 6.6 7.4 3.5 12 3.5s8.5 3.1 8.5 7Z"/></svg>';
  var icoFb =
    '<svg class="fc-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.99 3.66 9.12 8.44 9.88v-7H7.9V12h2.54V9.8c0-2.51 1.49-3.89 3.78-3.89 1.09 0 2.24.2 2.24.2v2.48H13.9c-1.25 0-1.63.78-1.63 1.57V12h2.78l-.45 2.89h-2.33v7A10 10 0 0 0 22 12Z"/></svg>';
  var icoMail =
    '<svg class="fc-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2Zm0 4-8 5L4 8V6l8 5 8-5v2Z"/></svg>';
  var icoChat =
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17 1H7C5.34 1 4 2.34 4 4v14c0 1.66 1.34 3 3 3h1l1 2 1-2h7c1.66 0 3-1.34 3-3V4c0-1.66-1.34-3-3-3Zm-1 12H8v-2h8v2Zm0-3H8V8h8v2Zm0-3H8V5h8v2Z"/></svg>';
  var icoClose =
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18.3 5.71a1 1 0 0 0-1.41 0L12 10.59 7.11 5.7A1 1 0 0 0 5.7 7.11L10.59 12 5.7 16.89a1 1 0 1 0 1.41 1.41L12 13.41l4.89 4.89a1 1 0 0 0 1.41-1.41L13.41 12l4.89-4.89a1 1 0 0 0-.01-1.4Z"/></svg>';

  var host = document.createElement("div");
  host.id = "floatingContactHost";
  host.className = "floating-contact";
  host.setAttribute("lang", "th");
  host.innerHTML =
    '<div class="floating-contact-panel" id="floatingContactPanel" role="dialog" aria-labelledby="floatingContactTitle" aria-modal="false">' +
      '<div class="floating-contact-head">' +
        '<p class="floating-contact-title" id="floatingContactTitle">ติดต่อเรา</p>' +
        '<button type="button" class="floating-contact-close" id="floatingContactClose" aria-label="ปิด">' +
          icoClose +
        "</button>" +
      "</div>" +
      '<div class="floating-contact-body">' +
        '<a class="floating-contact-link floating-contact-link--line" href="' +
        CONTACT.line +
        '" target="_blank" rel="noopener noreferrer">' +
        icoLine +
        "<span>แอด Line</span></a>" +
        '<a class="floating-contact-link floating-contact-link--fb" href="' +
        CONTACT.facebook +
        '" target="_blank" rel="noopener noreferrer">' +
        icoFb +
        "<span>Facebook</span></a>" +
        '<a class="floating-contact-link floating-contact-link--email" href="mailto:' +
        CONTACT.email +
        '">' +
        icoMail +
        "<span>อีเมล<span class=\"floating-contact-mail-text\">" +
        CONTACT.email +
        "</span></span></a>" +
      "</div>" +
    "</div>" +
    '<button type="button" class="floating-contact-fab" id="floatingContactToggle" aria-expanded="false" aria-controls="floatingContactPanel" title="ติดต่อเรา">' +
    icoChat +
    "</button>";

  document.body.appendChild(host);

  var panel = document.getElementById("floatingContactPanel");
  var toggle = document.getElementById("floatingContactToggle");
  var closeBtn = document.getElementById("floatingContactClose");

  function setOpen(open) {
    host.classList.toggle("is-open", open);
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
    if (open) {
      var first = panel.querySelector("a");
      if (first) {
        window.setTimeout(function () {
          first.focus();
        }, 180);
      }
    } else {
      toggle.focus();
    }
  }

  toggle.addEventListener("click", function (e) {
    e.stopPropagation();
    setOpen(!host.classList.contains("is-open"));
  });

  closeBtn.addEventListener("click", function () {
    setOpen(false);
  });

  document.addEventListener("click", function (e) {
    if (!host.classList.contains("is-open")) {
      return;
    }
    if (!host.contains(e.target)) {
      setOpen(false);
    }
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape" && host.classList.contains("is-open")) {
      setOpen(false);
    }
  });
})();
