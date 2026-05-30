/**
 * Post-registration identity verification modal.
 */
(function (global) {
  "use strict";

  var config = {
    apiBase: "",
    appBasePath: "",
    onUserUpdated: null
  };

  var modal = null;
  var form = null;
  var notice = null;
  var submitBtn = null;
  var profilePreview = null;

  function needsOnboarding(user) {
    if (!user) {
      return false;
    }
    var flag = user.is_profile_completed;
    if (flag === undefined || flag === null || flag === "") {
      return true;
    }
    return Number(flag) !== 1 && String(flag) !== "1";
  }

  function bindModalElements() {
    form = document.getElementById("profileOnboardingForm");
    notice = document.getElementById("profileOnboardingNotice");
    submitBtn = document.getElementById("profileOnboardingSubmit");
    profilePreview = document.getElementById("onboardingPhotoPreviews");
  }

  function ensureModal() {
    if (modal) {
      return;
    }

    modal = document.getElementById("profileOnboardingModal");
    if (modal) {
      bindModalElements();
      if (form && !form.dataset.boundSubmit) {
        form.dataset.boundSubmit = "1";
        form.addEventListener("submit", handleSubmit);
      }
      var photosInput = document.getElementById("onboardingPhotos");
      if (photosInput && !photosInput.dataset.boundChange) {
        photosInput.dataset.boundChange = "1";
        photosInput.addEventListener("change", function () {
          renderPhotoPreviews(photosInput.files);
        });
      }
      return;
    }

    modal = document.createElement("div");
    modal.className = "modal-backdrop profile-onboarding-backdrop";
    modal.id = "profileOnboardingModal";
    modal.setAttribute("aria-hidden", "true");
    modal.innerHTML = [
      '<div class="profile-onboarding-modal" role="dialog" aria-modal="true" aria-labelledby="profileOnboardingTitle">',
      '  <div class="profile-onboarding-head">',
      '    <h2 id="profileOnboardingTitle">ยืนยันตัวตน</h2>',
      '    <p>สมัครสมาชิกเรียบร้อยแล้ว กรุณาอัปโหลดรูปและกรอกรายละเอียดเพื่อยืนยันตัวตนก่อนเริ่มใช้งาน</p>',
      "  </div>",
      '  <form class="profile-onboarding-form" id="profileOnboardingForm">',
      '    <label class="profile-onboarding-field">',
      "      <span>ชื่อที่แสดงในโปรไฟล์</span>",
      '      <input type="text" name="display_name" id="onboardingDisplayName" required minlength="2" maxlength="150" placeholder="ชื่อเล่นหรือชื่อที่ต้องการแสดง">',
      "    </label>",
      '    <label class="profile-onboarding-field">',
      "      <span>วันเกิด</span>",
      '      <input type="date" name="birth_date" id="onboardingBirthDate" required>',
      "    </label>",
      '    <div class="profile-onboarding-grid">',
      '      <label class="profile-onboarding-field">',
      "        <span>จังหวัด</span>",
      '        <input type="text" name="province" id="onboardingProvince" required maxlength="120" placeholder="เช่น กรุงเทพมหานคร">',
      "      </label>",
      '      <label class="profile-onboarding-field">',
      "        <span>เมือง / เขต</span>",
      '        <input type="text" name="city" id="onboardingCity" required maxlength="120" placeholder="เช่น บางรัก">',
      "      </label>",
      "    </div>",
      '    <label class="profile-onboarding-field">',
      "      <span>เบอร์โทร (ไม่บังคับ)</span>",
      '      <input type="tel" name="phone" id="onboardingPhone" maxlength="30" placeholder="08xxxxxxxx">',
      "    </label>",
      '    <label class="profile-onboarding-field">',
      "      <span>แนะนำตัวสั้นๆ</span>",
      '      <textarea name="bio" id="onboardingBio" maxlength="2000" placeholder="บอกเล่าเกี่ยวกับตัวคุณสักเล็กน้อย"></textarea>',
      "    </label>",
      '    <div class="profile-onboarding-upload">',
      '      <span class="profile-onboarding-field"><span>รูปโปรไฟล์ (อย่างน้อย 1 รูป)</span></span>',
      '      <input type="file" name="photos" id="onboardingPhotos" accept="image/jpeg,image/png,image/webp" multiple required>',
      "      <small>JPG, PNG หรือ WEBP ไม่เกิน 5MB ต่อรูป (สูงสุด 5 รูป)</small>",
      '      <div class="profile-onboarding-previews" id="onboardingPhotoPreviews"></div>',
      "    </div>",
      '    <div class="profile-onboarding-upload">',
      '      <span class="profile-onboarding-field"><span>รูปยืนยันตัวตน</span></span>',
      '      <input type="file" name="verification_photo" id="onboardingVerificationPhoto" accept="image/jpeg,image/png,image/webp" required>',
      "      <small>เช่น ถ่ายคู่บัตรประชาชน หรือรูปเซลฟี่ถือบัตร เพื่อให้ทีมงานตรวจสอบ</small>",
      "    </div>",
      '    <div class="profile-onboarding-notice" id="profileOnboardingNotice" role="status"></div>',
      '    <button type="submit" class="profile-onboarding-submit" id="profileOnboardingSubmit">ส่งข้อมูลยืนยันตัวตน</button>',
      "  </form>",
      "</div>"
    ].join("");

    document.body.appendChild(modal);
    bindModalElements();

    var photosInput = document.getElementById("onboardingPhotos");
    if (photosInput) {
      photosInput.addEventListener("change", function () {
        renderPhotoPreviews(photosInput.files);
      });
    }

    if (form) {
      form.addEventListener("submit", handleSubmit);
    }

    modal.addEventListener("click", function (event) {
      if (event.target === modal) {
        event.stopPropagation();
      }
    });
  }

  function renderPhotoPreviews(fileList) {
    if (!profilePreview) {
      return;
    }
    profilePreview.innerHTML = "";
    if (!fileList || !fileList.length) {
      return;
    }
    Array.prototype.forEach.call(fileList, function (file) {
      if (!file.type || file.type.indexOf("image/") !== 0) {
        return;
      }
      var img = document.createElement("img");
      img.alt = "ตัวอย่างรูปโปรไฟล์";
      img.src = URL.createObjectURL(file);
      profilePreview.appendChild(img);
    });
  }

  function setNotice(message, type) {
    if (!notice) {
      return;
    }
    notice.textContent = message || "";
    notice.className = "profile-onboarding-notice" + (type ? " is-" + type : "");
  }

  function prefillFromUser(user) {
    if (!form) {
      return;
    }
    user = user || {};
    var display = document.getElementById("onboardingDisplayName");
    if (display && !display.value) {
      display.value = user.display_name || "";
    }
    var birth = document.getElementById("onboardingBirthDate");
    if (birth) {
      var adult = new Date();
      adult.setFullYear(adult.getFullYear() - 18);
      birth.max = adult.toISOString().slice(0, 10);
    }
  }

  function openModal() {
    ensureModal();
    document.body.appendChild(modal);
    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");
    modal.style.display = "flex";
    modal.style.zIndex = "500001";
    document.body.classList.add("profile-onboarding-locked");
  }

  function closeModal() {
    if (!modal) {
      return;
    }
    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");
    modal.style.display = "";
    modal.style.zIndex = "";
    document.body.classList.remove("profile-onboarding-locked");
    setNotice("", "");
    if (form) {
      form.reset();
    }
    if (profilePreview) {
      profilePreview.innerHTML = "";
    }
  }

  function openIfNeeded(user) {
    if (!needsOnboarding(user)) {
      return;
    }
    ensureModal();
    prefillFromUser(user);
    openModal();
  }

  /** เปิดป๊อปอัปหลังสมัครสำเร็จเสมอ (ไม่เช็ค is_profile_completed) */
  function openAfterRegister(user) {
    ensureModal();
    prefillFromUser(user);
    openModal();
  }

  async function handleSubmit(event) {
    event.preventDefault();
    if (!config.apiBase) {
      setNotice("ไม่พบการตั้งค่า API", "error");
      return;
    }

    setNotice("", "");
    submitBtn.disabled = true;

    var formData = new FormData(form);
    var photos = document.getElementById("onboardingPhotos");
    if (photos && photos.files.length > 5) {
      setNotice("อัปโหลดรูปโปรไฟล์ได้ไม่เกิน 5 รูป", "error");
      submitBtn.disabled = false;
      return;
    }

    try {
      var response = await global.fetch(config.apiBase + "/profile/onboarding", {
        method: "POST",
        credentials: "same-origin",
        body: formData
      });
      var text = await response.text();
      var data = {};
      if (text) {
        try {
          data = JSON.parse(text);
        } catch (ignore) {
          throw new global.Error("ตอบกลับไม่ถูกต้องจากเซิร์ฟเวอร์");
        }
      }
      if (!response.ok || data.success === false) {
        throw new global.Error(data.message || "HTTP " + response.status);
      }

      var updatedUser = data.data && data.data.user ? data.data.user : null;
      if (typeof config.onUserUpdated === "function") {
        config.onUserUpdated(updatedUser);
      }
      setNotice(data.message || "บันทึกเรียบร้อยแล้ว", "success");
      global.setTimeout(closeModal, 900);
    } catch (error) {
      setNotice(error.message || "ส่งข้อมูลไม่สำเร็จ", "error");
    } finally {
      submitBtn.disabled = false;
    }
  }

  global.ProfileOnboarding = {
    configure: function (opts) {
      config.apiBase = (opts && opts.apiBase) || config.apiBase;
      config.appBasePath = (opts && opts.appBasePath) || config.appBasePath;
      config.onUserUpdated = (opts && opts.onUserUpdated) || config.onUserUpdated;
    },
    needsOnboarding: needsOnboarding,
    openIfNeeded: openIfNeeded,
    openAfterRegister: openAfterRegister,
    close: closeModal
  };

  function initStaticModal() {
    if (document.getElementById("profileOnboardingModal")) {
      ensureModal();
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initStaticModal);
  } else {
    initStaticModal();
  }
})(typeof window !== "undefined" ? window : this);
