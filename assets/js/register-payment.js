/**
 * Shared registration: subscription plans + Omise + multi-bank transfer display.
 * Expects DOM ids matching index.html register modal.
 */
(function (global) {
  "use strict";

  var registrationPayOpts = null;

  function assetUrl(appBasePath, logo) {
    if (!logo) {
      return "";
    }
    var p = String(logo).replace(/^\//, "");
    var base = String(appBasePath || "").replace(/\/$/, "");
    return base ? base + "/" + p : "/" + p;
  }

  function getSelectedRegisterPlan() {
    if (!registrationPayOpts || !registrationPayOpts.plans || !registrationPayOpts.plans.length) {
      return null;
    }
    var el = document.querySelector('input[name="registerPlanId"]:checked');
    var id = el ? Number(el.value) : Number(registrationPayOpts.plans[0].id);
    var found = registrationPayOpts.plans.find(function (p) {
      return Number(p.id) === id;
    });
    return found || registrationPayOpts.plans[0];
  }

  function getRegisterPaymentMethod() {
    var el = document.querySelector('input[name="registerPaymentMethod"]:checked');
    return el ? el.value : "bank_transfer";
  }

  function syncRegisterPayMethodUI() {
    var cardFields = document.getElementById("registerCardFields");
    var isCard = getRegisterPaymentMethod() === "credit_card";
    if (cardFields) {
      cardFields.hidden = !isCard;
    }
  }

  function renderBankPreviewElement(bankPrev, appBasePath) {
    if (!bankPrev || !registrationPayOpts) {
      return;
    }
    var bankMeta = registrationPayOpts.bank || {};
    var holder = bankMeta.account_name || bankMeta.bank_account_name || "";
    var note = bankMeta.transfer_reference_note || "";
    var accounts = registrationPayOpts.bank_accounts;
    if (!accounts || !accounts.length) {
      bankPrev.hidden = false;
      bankPrev.textContent = [
        bankMeta.bank_name,
        "เลขบัญชี " + (bankMeta.bank_account_number || ""),
        holder ? "ชื่อบัญชี " + holder : "",
        note
      ].filter(Boolean).join(" · ");
      return;
    }
    bankPrev.hidden = false;
    bankPrev.innerHTML = "";
    var wrap = document.createElement("div");
    wrap.className = "register-bank-rows";
    if (holder) {
      var h = document.createElement("div");
      h.className = "register-bank-holder";
      h.style.fontWeight = "600";
      h.style.marginBottom = "4px";
      h.style.color = "#2e2230";
      h.textContent = "ชื่อบัญชี: " + holder;
      bankPrev.appendChild(h);
    }
    accounts.forEach(function (acc) {
      var row = document.createElement("div");
      var isTall = acc.code === "krungsri";
      row.className = "register-bank-row" + (isTall ? " is-tall-logo" : "");
      var logoWrap = document.createElement("div");
      logoWrap.className = "register-bank-logo-wrap";
      var img = document.createElement("img");
      img.className = "register-bank-logo";
      img.alt = acc.name_th || "";
      img.loading = "lazy";
      var src = assetUrl(appBasePath, acc.logo);
      if (src) {
        img.src = src;
        img.onerror = function () {
          logoWrap.style.display = "none";
        };
      } else {
        logoWrap.style.display = "none";
      }
      logoWrap.appendChild(img);
      var meta = document.createElement("div");
      meta.className = "register-bank-meta";
      var title = document.createElement("strong");
      title.textContent = acc.name_th || "";
      var num = document.createElement("div");
      num.className = "register-bank-account";
      num.textContent = acc.type === "promptpay" ? "พร้อมเพย์ " + acc.account_number : "เลขที่ " + acc.account_number;
      meta.appendChild(title);
      meta.appendChild(num);
      row.appendChild(logoWrap);
      row.appendChild(meta);
      wrap.appendChild(row);
    });
    bankPrev.appendChild(wrap);
    if (note) {
      var n = document.createElement("div");
      n.className = "register-bank-preview-note";
      n.textContent = note;
      bankPrev.appendChild(n);
    }
  }

  function renderBankPreview(appBasePath) {
    var bankPrev = document.getElementById("registerBankPreview");
    renderBankPreviewElement(bankPrev, appBasePath);
  }

  function syncRegisterPlanAndPaymentUI(appBasePath) {
    var plan = getSelectedRegisterPlan();
    var summary = document.getElementById("registerPlanSummary");
    var paySection = document.getElementById("registerPayMethodSection");
    var bankPrev = document.getElementById("registerBankPreview");
    if (!plan) {
      if (summary) {
        summary.textContent = "ไม่พบแพ็กเกจ — โปรดลองใหม่ภายหลัง";
      }
      if (paySection) {
        paySection.hidden = true;
      }
      return;
    }
    var price = Number(plan.price_thb);
    if (summary) {
      summary.textContent = price <= 0
        ? plan.name + " — ฟรี (ไม่ต้องชำระเงิน)"
        : plan.name + " — ฿" + price.toLocaleString("th-TH") + " / เดือน";
    }
    if (paySection) {
      paySection.hidden = price <= 0;
    }
    if (bankPrev) {
      bankPrev.hidden = price <= 0;
    }
    if (price > 0) {
      renderBankPreview(appBasePath);
    }
    syncRegisterPayMethodUI();
  }

  function bindPayMethodOnce() {
    var pm = document.getElementById("registerPayMethods");
    if (pm && !pm._registerPayBound) {
      pm._registerPayBound = true;
      pm.addEventListener("change", syncRegisterPayMethodUI);
    }
  }

  function createOmiseToken() {
    return createOmiseTokenWithFields({
      name: "omiseCardName",
      number: "omiseCardNumber",
      month: "omiseExpMonth",
      year: "omiseExpYear",
      security: "omiseSecurityCode"
    });
  }

  function createOmiseTokenWithFields(fieldIds) {
    fieldIds = fieldIds || {};
    return new Promise(function (resolve, reject) {
      if (typeof Omise === "undefined") {
        reject(new Error("ระบบ Omise ยังไม่พร้อม โปรดรีเฟรชหน้า"));
        return;
      }
      var nameId = fieldIds.name || "omiseCardName";
      var numberId = fieldIds.number || "omiseCardNumber";
      var monthId = fieldIds.month || "omiseExpMonth";
      var yearId = fieldIds.year || "omiseExpYear";
      var secId = fieldIds.security || "omiseSecurityCode";
      var monthEl = document.getElementById(monthId);
      var yearEl = document.getElementById(yearId);
      if (!monthEl || !yearEl) {
        reject(new Error("ไม่พบช่องกรอกข้อมูลบัตร"));
        return;
      }
      var month = parseInt(monthEl.value, 10);
      var year = parseInt(yearEl.value, 10);
      Omise.createToken({
        card: {
          name: document.getElementById(nameId).value.trim(),
          number: document.getElementById(numberId).value.replace(/\s/g, ""),
          expiration_month: month,
          expiration_year: year,
          security_code: document.getElementById(secId).value.trim()
        }
      }, function (statusCode, response) {
        if (statusCode !== 200) {
          var msg = (response && response.message) ? response.message : "สร้างโทเค็นบัตรไม่สำเร็จ";
          reject(new Error(msg));
          return;
        }
        resolve(response.id);
      });
    });
  }

  global.RegisterPaymentUI = {
    getOpts: function () {
      return registrationPayOpts;
    },

    loadRegistrationPaymentOptions: function (apiFetch, appBasePath) {
      return apiFetch("/payments/registration-options", { method: "GET" }).then(function (response) {
        registrationPayOpts = response.data;
        var block = document.getElementById("registerPaymentBlock");
        if (block) {
          block.hidden = false;
        }
        var cardOpt = document.getElementById("registerPayCardOption");
        if (cardOpt) {
          cardOpt.style.display = registrationPayOpts.card_enabled ? "" : "none";
        }
        var premiumCardOpt = document.getElementById("premiumPayCardOption");
        if (premiumCardOpt) {
          premiumCardOpt.style.display = registrationPayOpts.card_enabled ? "" : "none";
        }
        if (registrationPayOpts.omise_public_key && typeof Omise !== "undefined") {
          Omise.setPublicKey(registrationPayOpts.omise_public_key);
        }
        bindPayMethodOnce();
        var box = document.getElementById("registerPlanChoices");
        if (box && registrationPayOpts.plans && registrationPayOpts.plans.length) {
          box.innerHTML = "";
          registrationPayOpts.plans.forEach(function (plan, idx) {
            var label = document.createElement("label");
            label.className = "register-plan-choice";
            var priceLabel = plan.price_thb <= 0 ? "ฟรี" : "฿" + Number(plan.price_thb).toLocaleString("th-TH") + "/เดือน";
            label.innerHTML = "<input type=\"radio\" name=\"registerPlanId\" value=\"" + plan.id + "\" " + (idx === 0 ? "checked" : "") + ">" +
              "<span class=\"register-plan-choice-body\"><strong>" + plan.name + "</strong>" +
              "<span class=\"register-plan-price\">" + priceLabel + "</span></span>";
            box.appendChild(label);
          });
          box.onclick = function (event) {
            if (event.target && event.target.name === "registerPlanId") {
              syncRegisterPlanAndPaymentUI(appBasePath);
            }
          };
        }
        syncRegisterPlanAndPaymentUI(appBasePath);
        return registrationPayOpts;
      });
    },

    syncRegisterPlanAndPaymentUI: function (appBasePath) {
      syncRegisterPlanAndPaymentUI(appBasePath);
    },

    getSelectedRegisterPlan: getSelectedRegisterPlan,
    getRegisterPaymentMethod: getRegisterPaymentMethod,
    syncRegisterPayMethodUI: syncRegisterPayMethodUI,
    createOmiseToken: createOmiseToken,
    createOmiseTokenWithFields: createOmiseTokenWithFields,
    renderBankPreviewElement: renderBankPreviewElement,

    /**
     * @param {Event} event
     * @param {object} ctx
     * @param {function} ctx.apiFetch
     * @param {string} ctx.appBasePath
     * @param {HTMLFormElement} ctx.registerForm
     * @param {HTMLElement} ctx.registerNotice
     * @param {HTMLButtonElement} ctx.registerSubmitButton
     * @param {function} ctx.clearNotice
     * @param {function} ctx.showNotice
     * @param {function} ctx.fieldIds - optional { firstName, lastName, email, password, gender, interestedIn }
     * @param {function} ctx.onSuccess - async (response) => void
     */
    handleRegisterSubmit: function (event, ctx) {
      event.preventDefault();
      ctx.clearNotice(ctx.registerNotice);
      if (!ctx.registerForm.checkValidity()) {
        ctx.registerForm.reportValidity();
        return;
      }

      var selPlan = getSelectedRegisterPlan();
      if (!selPlan) {
        ctx.showNotice(ctx.registerNotice, "ไม่พบแพ็กเกจ โปรดรีเฟรชหน้าแล้วลองใหม่", "error");
        return;
      }

      var ids = ctx.fieldIds || {};
      var firstId = ids.firstName || "registerFirstName";
      var lastId = ids.lastName || "registerLastName";
      var emailId = ids.email || "registerEmail";
      var passId = ids.password || "registerPassword";
      var genderId = ids.gender || "registerGender";
      var interestedId = ids.interestedIn || "registerInterestedIn";

      var fee = Number(selPlan.price_thb) > 0;
      var method = fee ? getRegisterPaymentMethod() : "bank_transfer";
      var omiseToken = "";

      if (fee && method === "credit_card") {
        if (!registrationPayOpts || !registrationPayOpts.card_enabled) {
          ctx.showNotice(ctx.registerNotice, "ไม่สามารถชำระด้วยบัตรได้ในขณะนี้ กรุณาเลือกโอนธนาคาร", "error");
          return;
        }
        return createOmiseToken().then(function (token) {
          return runRegister(ctx, selPlan, fee, method, token, firstId, lastId, emailId, passId, genderId, interestedId);
        }).catch(function (tokenErr) {
          ctx.showNotice(ctx.registerNotice, tokenErr.message || "ข้อมูลบัตรไม่ถูกต้อง", "error");
          ctx.registerSubmitButton.disabled = false;
        });
      }

      return runRegister(ctx, selPlan, fee, method, omiseToken, firstId, lastId, emailId, passId, genderId, interestedId);
    }
  };

  function runRegister(ctx, selPlan, fee, method, omiseToken, firstId, lastId, emailId, passId, genderId, interestedId) {
    ctx.registerSubmitButton.disabled = true;
    var payload = {
      first_name: document.getElementById(firstId).value,
      last_name: document.getElementById(lastId).value,
      email: document.getElementById(emailId).value,
      password: document.getElementById(passId).value,
      gender: document.getElementById(genderId).value,
      interested_in: document.getElementById(interestedId).value,
      plan_id: selPlan.id
    };
    if (fee) {
      payload.payment_method = method;
      if (method === "credit_card") {
        payload.omise_token = omiseToken;
      }
    }

    return ctx.apiFetch("/auth/register", {
      method: "POST",
      body: JSON.stringify(payload)
    }).then(function (response) {
      return Promise.resolve(ctx.onSuccess(response, { fee: fee, method: method, selPlan: selPlan })).then(function () {
        var msg = response.message || "สมัครสมาชิกสำเร็จ";
        if (response.data && response.data.registration_payment && response.data.registration_payment.status === "pending") {
          var p = response.data.registration_payment;
          msg += " โอน ฿" + Number(p.amount_thb).toLocaleString("th-TH") + " (" + (p.plan_name || selPlan.name) + ")";
          if (p.bank_accounts && p.bank_accounts.length) {
            msg += " — โอนเข้าบัญชีใดก็ได้ด้านล่าง (ชื่อบัญชี " + (p.bank_account_name || "") + ")";
          } else {
            msg += " ไปที่ " + p.bank_name + " เลข " + p.bank_account_number + " (" + p.bank_account_name + ") — " + (p.transfer_reference_note || "");
          }
        }
        ctx.showNotice(ctx.registerNotice, msg, "success");
        ctx.registerForm.reset();
        var bankRadioReset = document.querySelector('input[name="registerPaymentMethod"][value="bank_transfer"]');
        if (bankRadioReset) {
          bankRadioReset.checked = true;
        }
        return global.RegisterPaymentUI.loadRegistrationPaymentOptions(ctx.apiFetch, ctx.appBasePath).catch(function (e) {
          console.error(e);
        }).then(function () {
          window.setTimeout(function () {
            if (ctx.closeRegisterModal) {
              ctx.closeRegisterModal();
            }
            ctx.clearNotice(ctx.registerNotice);
          }, fee && method === "bank_transfer" ? 1400 : 700);
        });
      });
    }).catch(function (error) {
      ctx.showNotice(ctx.registerNotice, error.message, "error");
    }).finally(function () {
      ctx.registerSubmitButton.disabled = false;
    });
  }
})(window);
