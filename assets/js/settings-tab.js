(function (wp) {
  if (!wp || !wp.hooks || !wp.i18n) {
    return;
  }

  const { addFilter } = wp.hooks;
  const { __ } = wp.i18n;

  const GoogleSheetSettingsTab = {
    name: "google-sheet-settings-tab",
    props: {
      incoming: {
        type: Object,
        default() {
          return {};
        },
      },
    },
    data() {
      return {
        current: {
          credentials_json: this.incoming?.credentials_json || "",
          debug_enabled: !!this.incoming?.debug_enabled,
        },
      };
    },
    computed: {
      isConfigured() {
        return !!(
          window.GSJFBSettingsMeta && window.GSJFBSettingsMeta.isConfigured
        );
      },
      serviceAccountEmail() {
        return (
          (window.GSJFBSettingsMeta &&
            window.GSJFBSettingsMeta.serviceAccountEmail) ||
          ""
        );
      },
      hasValidJson() {
        try {
          const parsed = JSON.parse(this.current.credentials_json);
          return !!(parsed && parsed.client_email && parsed.private_key);
        } catch (e) {
          return false;
        }
      },
      parsedEmail() {
        try {
          const parsed = JSON.parse(this.current.credentials_json);
          return parsed?.client_email || "";
        } catch (e) {
          return "";
        }
      },
    },
    methods: {
      getRequestOnSave() {
        return { data: { ...this.current } };
      },
    },
    render(h) {
      // ── Connection section ──
      const statusBadge = this.isConfigured
        ? h(
            "span",
            { class: "gsjfb-pill gsjfb-pill--success" },
            __("Connected", "google-sheet-for-jetformbuilder")
          )
        : h(
            "span",
            { class: "gsjfb-pill gsjfb-pill--warning" },
            __("Not configured", "google-sheet-for-jetformbuilder")
          );

      const docLink = h(
        "a",
        {
          attrs: {
            href: "https://console.cloud.google.com/apis/credentials",
            target: "_blank",
            rel: "noopener noreferrer",
          },
          class: "gsjfb-doc-link",
        },
        [
          __("Google Cloud Console", "google-sheet-for-jetformbuilder"),
          h("span", { class: "dashicons dashicons-external" }),
        ]
      );

      // Credentials panel — always visible, like media-storage providers
      const credentialsPanel = h("cx-vui-panel", { attrs: { title: __("Credentials", "google-sheet-for-jetformbuilder") } }, [
        // Service Account email (shown when configured)
        this.isConfigured && this.serviceAccountEmail
          ? h(
              "div",
              { class: "cx-vui-component cx-vui-component--equalwidth" },
              [
                h("div", { class: "cx-vui-component__meta" }, [
                  h(
                    "label",
                    { class: "cx-vui-component__label" },
                    __("Service Account email", "google-sheet-for-jetformbuilder")
                  ),
                  h(
                    "div",
                    { class: "cx-vui-component__desc" },
                    __("Share your Google Sheets with this email address (Editor role) to grant access.", "google-sheet-for-jetformbuilder")
                  ),
                ]),
                h("div", { class: "cx-vui-component__control" }, [
                  h("code", { class: "gsjfb-email-code" }, this.serviceAccountEmail),
                ]),
              ]
            )
          : null,
        // JSON textarea
        h(
          "div",
          { class: "cx-vui-component cx-vui-component--equalwidth" },
          [
            h("div", { class: "cx-vui-component__meta" }, [
              h(
                "label",
                { class: "cx-vui-component__label" },
                __("Service Account JSON key", "google-sheet-for-jetformbuilder")
              ),
              h(
                "div",
                { class: "cx-vui-component__desc" },
                __("Paste the full JSON key file contents. Go to IAM & Admin → Service Accounts → Keys → Add Key → JSON.", "google-sheet-for-jetformbuilder")
              ),
            ]),
            h("div", { class: "cx-vui-component__control" }, [
              h("textarea", {
                class: "gsjfb-credentials-textarea",
                attrs: {
                  rows: 8,
                  placeholder:
                    '{\n  "type": "service_account",\n  "client_email": "...@...iam.gserviceaccount.com",\n  "private_key": "-----BEGIN PRIVATE KEY-----\\n...",\n  ...\n}',
                },
                domProps: {
                  value: this.current.credentials_json,
                },
                on: {
                  input: (e) => {
                    this.current.credentials_json = e.target.value;
                  },
                },
              }),
              this.hasValidJson
                ? h(
                    "p",
                    { class: "gsjfb-feedback gsjfb-feedback--success" },
                    __("Valid JSON detected:", "google-sheet-for-jetformbuilder") + " " + this.parsedEmail
                  )
                : this.current.credentials_json.trim()
                ? h(
                    "p",
                    { class: "gsjfb-feedback gsjfb-feedback--error" },
                    __("Invalid JSON or missing required fields (client_email, private_key).", "google-sheet-for-jetformbuilder")
                  )
                : null,
            ]),
          ]
        ),
        h(
          "div",
          { class: "gsjfb-provider-card__footer" },
          __("Keep your Service Account key secret. Never share it publicly.", "google-sheet-for-jetformbuilder")
        ),
      ]);

      // Instructions panel
      const instructionsPanel = h("cx-vui-panel", { attrs: { title: __("Setup guide", "google-sheet-for-jetformbuilder") } }, [
        h("div", { class: "gsjfb-instructions" }, [
          h("ol", null, [
            h("li", null, [
              __("Go to the ", "google-sheet-for-jetformbuilder"),
              h(
                "a",
                {
                  attrs: {
                    href: "https://console.cloud.google.com/",
                    target: "_blank",
                    rel: "noopener noreferrer",
                  },
                },
                "Google Cloud Console"
              ),
              __(" and create or select a project", "google-sheet-for-jetformbuilder"),
            ]),
            h("li", null, [
              __("Enable the ", "google-sheet-for-jetformbuilder"),
              h(
                "a",
                {
                  attrs: {
                    href: "https://console.cloud.google.com/apis/library/sheets.googleapis.com",
                    target: "_blank",
                    rel: "noopener noreferrer",
                  },
                },
                "Google Sheets API"
              ),
            ]),
            h(
              "li",
              null,
              __("Navigate to IAM & Admin → Service Accounts → Create Service Account", "google-sheet-for-jetformbuilder")
            ),
            h(
              "li",
              null,
              __("Open the new Service Account → Keys tab → Add Key → Create new key → JSON", "google-sheet-for-jetformbuilder")
            ),
            h(
              "li",
              null,
              __("Paste the downloaded JSON contents into the field above", "google-sheet-for-jetformbuilder")
            ),
            h(
              "li",
              null,
              [
                h("strong", null, __("Important: ", "google-sheet-for-jetformbuilder")),
                __("Share each Google Sheet with the Service Account email address (Editor permission)", "google-sheet-for-jetformbuilder"),
              ]
            ),
          ]),
        ]),
      ]);

      // Connection section
      const connectionSection = h("div", { class: "gsjfb-section gsjfb-section--provider" }, [
        h("div", { class: "gsjfb-section__headline" }, [
          h("div", { class: "gsjfb-section__title-group" }, [
            h("h3", null, __("Google Sheets Connection", "google-sheet-for-jetformbuilder")),
            h("div", { class: "gsjfb-pill-group" }, [
              statusBadge,
              h("span", { class: "gsjfb-pill" }, __("Sheets API v4", "google-sheet-for-jetformbuilder")),
              h("span", { class: "gsjfb-pill" }, __("Service Account", "google-sheet-for-jetformbuilder")),
            ]),
          ]),
          docLink,
        ]),
        h(
          "p",
          { class: "gsjfb-provider-description" },
          __("Connect to Google Sheets using a Service Account. Form submissions will be appended as new rows to your configured spreadsheets.", "google-sheet-for-jetformbuilder")
        ),
        credentialsPanel,
        instructionsPanel,
      ]);

      // ── Advanced section ──
      const debugToggle = h("cx-vui-switcher", {
        attrs: {
          label: __("Enable debug logs", "google-sheet-for-jetformbuilder"),
          description: __("Log Google Sheets API requests and errors to the PHP error log for troubleshooting.", "google-sheet-for-jetformbuilder"),
          "wrapper-css": ["equalwidth"],
        },
        model: {
          value: !!this.current.debug_enabled,
          callback: (value) =>
            this.$set(this.current, "debug_enabled", !!value),
          expression: "current.debug_enabled",
        },
      });

      const advancedSection = h("div", { class: "gsjfb-section" }, [
        h("div", { class: "gsjfb-section__headline" }, [
          h("h3", null, __("Advanced", "google-sheet-for-jetformbuilder")),
        ]),
        h(
          "p",
          { class: "gsjfb-general-note" },
          __("Diagnostic and development options.", "google-sheet-for-jetformbuilder")
        ),
        debugToggle,
      ]);

      return h("div", { class: "gsjfb-settings" }, [
        connectionSection,
        advancedSection,
      ]);
    },
  };

  const tabDefinition = {
    title: __("Google Sheet", "google-sheet-for-jetformbuilder"),
    component: GoogleSheetSettingsTab,
  };

  addFilter(
    "jet.fb.register.settings-page.tabs",
    "google-sheet-for-jetformbuilder/settings-tab",
    (tabs) => {
      tabs.push(tabDefinition);
      return tabs;
    }
  );
})(window.wp);
