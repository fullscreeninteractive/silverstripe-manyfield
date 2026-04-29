(() => {
  ("use strict");

  // ------- Utilities ----------------------------------------------------

  /** @param {() => void} callback */
  const onReady = (callback) => {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", callback, { once: true });
    } else {
      callback();
    }
  };

  /**
   * @param {EventTarget} target
   * @param {string} name
   * @param {unknown} [detail]
   */
  const dispatch = (target, name, detail) => {
    target.dispatchEvent(new CustomEvent(name, { detail, bubbles: true }));
  };

  /** @param {string | null | undefined} html */
  const parseHTML = (html) =>
    document.createRange().createContextualFragment(html ?? "");

  /**
   * @param {Element} target
   * @param {string | null | undefined} html
   */
  const setHTML = (target, html) => {
    target.replaceChildren();
    target.appendChild(parseHTML(html));
  };

  /**
   * @param {Element} target
   * @param {"beforebegin" | "afterbegin" | "beforeend" | "afterend"} position
   * @param {string} html
   */
  const insertHTML = (target, position, html) => {
    const fragment = parseHTML(html);
    switch (position) {
      case "beforebegin":
        target.parentNode?.insertBefore(fragment, target);
        break;
      case "afterbegin":
        target.insertBefore(fragment, target.firstChild);
        break;
      case "beforeend":
        target.appendChild(fragment);
        break;
      case "afterend":
        target.parentNode?.insertBefore(fragment, target.nextSibling);
        break;
    }
  };

  /**
   * @param {URLSearchParams | string | Record<string, unknown> | null | undefined} data
   * @returns {URLSearchParams | null}
   */
  const toParams = (data) => {
    if (data == null) return null;
    if (data instanceof URLSearchParams) return data;
    if (typeof data === "string") return new URLSearchParams(data);
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(data)) {
      params.append(key, String(value ?? ""));
    }
    return params;
  };

  /** @param {HTMLFormElement} form */
  const serializeForm = (form) => {
    const params = new URLSearchParams();
    for (const el of Array.from(form.elements)) {
      if (
        !(el instanceof HTMLInputElement) &&
        !(el instanceof HTMLSelectElement) &&
        !(el instanceof HTMLTextAreaElement)
      ) {
        continue;
      }

      if (!el.name || el.disabled) continue;

      if (el instanceof HTMLInputElement) {
        const { type } = el;
        if (type === "checkbox" || type === "radio") {
          if (el.checked) params.append(el.name, el.value);
          continue;
        }
        if (
          type === "submit" ||
          type === "button" ||
          type === "reset" ||
          type === "file"
        ) {
          continue;
        }
        params.append(el.name, el.value);
      } else if (el instanceof HTMLSelectElement && el.multiple) {
        for (const option of Array.from(el.selectedOptions)) {
          params.append(el.name, option.value);
        }
      } else {
        params.append(el.name, el.value);
      }
    }
    return params;
  };

  /**
   * @param {string} url
   * @param {{ method?: string, data?: URLSearchParams | string | Record<string, unknown> | null }} [options]
   * @returns {Promise<string>}
   */
  const request = async (url, { method = "GET", data = null } = {}) => {
    const upper = method.toUpperCase();
    const params = toParams(data);
    const headers = { "X-Requested-With": "XMLHttpRequest" };
    let target = url;
    let body = null;

    if (upper === "GET" || upper === "HEAD") {
      if (params && [...params].length) {
        target = `${url}${url.includes("?") ? "&" : "?"}${params.toString()}`;
      }
    } else if (params) {
      headers["Content-Type"] =
        "application/x-www-form-urlencoded; charset=UTF-8";
      body = params.toString();
    }

    const response = await fetch(target, {
      method: upper,
      headers,
      body,
      credentials: "same-origin",
    });
    return response.text();
  };

  /** @param {HTMLElement | null} modal */
  const showModal = (modal) => {
    if (!modal) return;
    modal.style.display = "block";
    modal.removeAttribute("aria-hidden");
    modal.setAttribute("aria-modal", "true");
    requestAnimationFrame(() => modal.classList.add("show"));
    document.body.classList.add("modal-open");
    if (!document.querySelector(".modal-backdrop")) {
      const backdrop = document.createElement("div");
      backdrop.className = "modal-backdrop fade show";
      document.body.appendChild(backdrop);
    }
  };

  const hideAllModals = () => {
    document.querySelectorAll(".modal").forEach((modal) => {
      modal.classList.remove("show");
      if (modal instanceof HTMLElement) modal.style.display = "";
      modal.setAttribute("aria-hidden", "true");
      modal.removeAttribute("aria-modal");
    });
    document.body.classList.remove("modal-open");
    document
      .querySelectorAll(".modal-backdrop")
      .forEach((backdrop) => backdrop.remove());
  };

  // ------- Row decoration ----------------------------------------------

  const wrapManyFields = () => {
    document.querySelectorAll(".manyfield__holder").forEach((holder) => {
      const canSort = holder.classList.contains("manyfield__holder--cansort");
      const canRemove = holder.classList.contains(
        "manyfield__holder--canremove",
      );

      const removeIcon =
        holder.querySelector(".manyfield__icon-template--remove")?.innerHTML ||
        '<span aria-hidden="true">&times;</span>';
      const moveIcon =
        holder.querySelector(".manyfield__icon-template--move")?.innerHTML ||
        '<span aria-hidden="true">&#8645;</span>';

      holder.querySelectorAll(".manyfield__row").forEach((row) => {
        if (!(row instanceof HTMLElement)) return;
        if (canRemove) {
          if (!row.querySelector(".manyfield__remove")) {
            let href = row.dataset.inlineDelete;
            const inlineDelete = Boolean(href);

            if (href) {
              const idInput = row.querySelector(
                'input[type="hidden"][name*=ID]',
              );
              if (!(idInput instanceof HTMLInputElement)) {
                console.error("No ID hidden field in ManyField row");
                return;
              }
              href = `${href}?ID=${idInput.value}`;
            }

            const link = document.createElement("a");
            link.className = "btn btn-sm btn-danger manyfield__remove";
            link.dataset.inlineDelete = String(inlineDelete);
            link.href = href || "#";
            link.innerHTML = removeIcon;
            row.prepend(link);
          }
        } else {
          row
            .querySelectorAll(".manyfield__remove")
            .forEach((el) => el.remove());
        }

        if (canSort) {
          if (!row.querySelector(".manyfield__move")) {
            const move = document.createElement("span");
            move.className = "btn btn-sm btn-info manyfield__move";
            move.innerHTML = moveIcon;
            row.prepend(move);
          }
        } else {
          row.querySelectorAll(".manyfield__move").forEach((el) => el.remove());
        }
      });
    });
  };

  /** @param {Event} event @returns {Element | null} */
  const targetElement = (event) =>
    event.target instanceof Element ? event.target : null;

  /** @returns {string} */
  const getCsrf = () => {
    const csrfInput = document.querySelector("input[name=SecurityID]");
    return csrfInput instanceof HTMLInputElement ? csrfInput.value : "";
  };

  // ------- Inline save (change events on a row) ------------------------

  /** @param {Event} event */
  const handleInlineSaveChange = async (event) => {
    const target = targetElement(event);
    if (!target) return;

    const fieldEl = target.closest("[data-inline-save] .field");
    if (!fieldEl) return;

    const parent = target.closest("[data-inline-save]");
    const row = target.closest(".row");
    if (!(parent instanceof HTMLElement) || !row) return;

    const url = parent.dataset.inlineSave;
    if (!url) return;

    const csrf = getCsrf();
    const data = new URLSearchParams();

    row.querySelectorAll("[name]").forEach((field) => {
      if (
        !(field instanceof HTMLInputElement) &&
        !(field instanceof HTMLSelectElement) &&
        !(field instanceof HTMLTextAreaElement)
      ) {
        return;
      }

      const name = field.getAttribute("name");
      if (!name) return;

      const value =
        field instanceof HTMLInputElement && field.type === "checkbox"
          ? String(field.checked)
          : (field.value ?? "");

      let cleanName = name.substring(name.indexOf("[") + 1, name.indexOf("]"));

      if (name.split("[").length > 3) {
        cleanName += name.substring(
          name.lastIndexOf("["),
          name.lastIndexOf("]") + 1,
        );
      }

      data.append(cleanName, value);
    });

    data.append("SecurityID", csrf);

    await request(url, { method: "POST", data });
    dispatch(document.body, "manyFieldSaved");
  };

  // ------- Add row -----------------------------------------------------

  /** @param {Event} event */
  const handleAddClick = async (event) => {
    const target = targetElement(event);
    if (!target) return;
    const trigger = target.closest(".manyfield__add a");
    if (!(trigger instanceof HTMLAnchorElement)) return;
    event.preventDefault();

    const holder = trigger.closest(".manyfield__holder");
    if (!(holder instanceof HTMLElement)) return;

    const href = trigger.getAttribute("href");
    if (!href) return;

    const responseHtml = await request(href, {
      method: "GET",
      data: {
        index: holder.querySelectorAll(".manyfield__row").length,
      },
    });

    if (holder.classList.contains("manyfieldmodal")) {
      const modal = document.getElementById(`${holder.id}_modal`);
      if (!modal) return;

      // Strip namespace prefixes from input names so the modal can edit a
      // single record at a time.
      parseHTML(responseHtml)
        .querySelectorAll("input[name]")
        .forEach((field) => {
          const name = field.getAttribute("name") ?? "";
          const open = name.indexOf("[");
          const close = name.indexOf("]");
          if (open !== -1 && close !== -1) {
            field.setAttribute("name", name.substring(open + 1, close));
          }
        });

      // Detach the modal from any wrapping form so submits don't bubble.
      if (modal.closest("form")) {
        document.body.prepend(modal);
      }

      const saveURL = modal.getAttribute("data-save-url") ?? "";
      const wrapper = document.createElement("form");
      wrapper.setAttribute("action", saveURL);
      wrapper.appendChild(parseHTML(responseHtml));

      const body = modal.querySelector(".modal-body");
      if (body) body.replaceChildren(wrapper);

      showModal(modal);
    } else {
      const rows = holder.querySelectorAll(".manyfield__row");
      const lastRow = rows.item(rows.length - 1);

      if (lastRow instanceof Element) {
        insertHTML(lastRow, "afterend", responseHtml);
      } else {
        const outer = holder.querySelector(".manyfield__outer");
        if (outer) insertHTML(outer, "beforeend", responseHtml);
      }

      wrapManyFields();
    }

    dispatch(document.body, "manyFieldAdded", { parents: holder });
  };

  // ------- Remove row --------------------------------------------------

  /** @param {Event} event */
  const handleRemoveClick = (event) => {
    const target = targetElement(event);
    if (!target) return;
    const trigger = target.closest(".manyfield__remove");
    if (!(trigger instanceof HTMLAnchorElement)) return;

    event.preventDefault();
    event.stopPropagation();

    const parent =
      trigger.closest(".manyfield__row") ?? trigger.closest("[data-many-id]");

    parent?.remove();

    const csrf = getCsrf();
    const href = trigger.getAttribute("href");
    const inlineDelete = trigger.dataset.inlineDelete === "true";

    if (inlineDelete && href && href !== "#") {
      void request(href, {
        method: "POST",
        data: { SecurityID: csrf },
      });
    }

    dispatch(document.body, "manyFieldRemoved", { parent });
  };

  // ------- Modal save --------------------------------------------------

  /** @param {Event} event */
  const handleModalSaveClick = async (event) => {
    const target = targetElement(event);
    if (!target) return;
    const trigger = target.closest(".manyfield__save");
    if (!trigger) return;

    const modalContent = trigger.closest(".modal-content");
    if (!modalContent) return;
    const form = modalContent.querySelector("form");
    if (!(form instanceof HTMLFormElement)) return;

    dispatch(document.body, "manyFormModalSave", { form });

    if (!form.checkValidity()) {
      event.preventDefault();
      window.alert("You are missing one or more fields");
      return;
    }

    const body = modalContent.querySelector(".modal-body");
    body?.classList.add("loading");

    const action = form.getAttribute("action");
    if (!action) {
      console.error("No action found on form");
      return;
    }

    const reply = await request(action, {
      method: "POST",
      data: serializeForm(form),
    });

    if (reply) {
      const holder = form.closest(".manyfield__holder");
      if (holder) setHTML(holder, reply);
      body?.replaceChildren();
    }

    hideAllModals();
    dispatch(document.body, "manyFormModalSaved", { form });
  };

  // ------- Modal edit (clicking a row in manyfieldmodal mode) ----------

  /** @param {Event} event */
  const handleModalRowClick = async (event) => {
    const target = targetElement(event);
    if (!target) return;
    const direct = target.closest(".manyfieldmodal .manyfield__outer > div");
    if (!(direct instanceof HTMLElement)) return;

    const holder = direct.closest(".manyfieldmodal");
    const recordId = direct.dataset.manyId;
    if (!holder || !holder.id || !recordId) return;

    const modal = document.getElementById(`${holder.id}_modal`);
    if (!modal) return;

    const formUrl = modal.dataset.formUrl;
    if (!formUrl) return;

    const reply = await request(formUrl, {
      method: "GET",
      data: { RecordID: recordId },
    });

    const saveURL = modal.getAttribute("data-save-url") ?? "";
    const wrapper = document.createElement("form");
    wrapper.setAttribute("action", saveURL);
    wrapper.appendChild(parseHTML(reply));

    const body = modal.querySelector(".modal-body");
    if (body) {
      body.replaceChildren(wrapper);
      body.classList.remove("loading");
    }

    showModal(modal);
  };

  // ------- Modal dismiss handlers --------------------------------------

  /** @param {Event} event */
  const handleModalDismissClick = (event) => {
    const target = targetElement(event);
    if (!target) return;
    const dismiss = target.closest('[data-dismiss="modal"]');
    if (dismiss && dismiss.closest(".modal")) {
      hideAllModals();
      return;
    }
    if (
      target.classList.contains("modal") ||
      target.classList.contains("modal-backdrop")
    ) {
      hideAllModals();
    }
  };

  /** @param {KeyboardEvent} event */
  const handleModalEscape = (event) => {
    if (event.key === "Escape" && document.querySelector(".modal.show")) {
      hideAllModals();
    }
  };

  // ------- Ajax-loaded holders -----------------------------------------

  /** @param {HTMLElement} holder */
  const populateViaAjax = async (holder) => {
    const outer = holder.querySelector(".manyfield__outer");
    if (!outer) return;

    const url = holder.dataset.ajaxUrl;
    if (!url) return;

    outer.classList.add("loading");

    const form = holder.closest("form");
    const data = form ? serializeForm(form) : null;
    const reply = await request(url, { method: "GET", data });

    const incomingOuter = parseHTML(reply).querySelector(".manyfield__outer");
    if (incomingOuter) setHTML(outer, incomingOuter.innerHTML);

    outer.classList.remove("loading");
    dispatch(document.body, "manyFieldLoaded", { holder });
  };

  // ------- Bootstrap ---------------------------------------------------

  onReady(() => {
    wrapManyFields();

    document.body.addEventListener("change", handleInlineSaveChange);
    document.body.addEventListener("click", handleAddClick);
    document.body.addEventListener("click", handleRemoveClick);
    document.body.addEventListener("click", handleModalSaveClick);
    document.body.addEventListener("click", handleModalRowClick);
    document.body.addEventListener("click", handleModalDismissClick);
    document.addEventListener("keydown", handleModalEscape);

    document
      .querySelectorAll(".manyfield__holder[data-ajax-url]")
      .forEach((holder) => {
        if (!(holder instanceof HTMLElement)) return;
        populateViaAjax(holder);

        const id = holder.id;
        if (!id) return;

        const selector = `[data-updates-manyfield="${CSS.escape(id)}"]`;
        document.body.addEventListener("change", (event) => {
          const target = targetElement(event);
          if (target && target.closest(selector)) {
            populateViaAjax(holder);
          }
        });
      });
  });
})();
