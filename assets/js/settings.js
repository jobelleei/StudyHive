/* =====================================================
   STUDYHIVE - GLOBAL SETTINGS
   Shared by Notes, Calendar, Achievements and Feed.
===================================================== */
(function () {
    "use strict";

    const KEYS = {
        theme: "studyhiveTheme",
        pomodoro: "studyhivePomodoroSettings",
        notifications: "studyhiveNotificationSettings"
    };

    function readSetting(key, fallback) {
        try {
            const raw = localStorage.getItem(key);
            if (raw === null) return fallback;
            try { return JSON.parse(raw); }
            catch (_) { return raw; }
        } catch (error) {
            return fallback;
        }
    }

    function writeSetting(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
            return true;
        } catch (error) {
            console.error("Unable to save StudyHive setting:", error);
            return false;
        }
    }

    function applyTheme(theme) {
        const selected = theme === "dark" ? "dark" : "light";
        document.documentElement.setAttribute("data-theme", selected);
        if (document.body) {
            document.body.classList.toggle("dark-theme", selected === "dark");
        }
        return selected;
    }

    // Apply the saved theme on every page, even when the modal is not open.
    applyTheme(readSetting(KEYS.theme, "light"));

    // Update the theme immediately if another StudyHive tab changes it.
    window.addEventListener("storage", function (event) {
        if (event.key === KEYS.theme) {
            applyTheme(readSetting(KEYS.theme, "light"));
        }
    });

    document.addEventListener("DOMContentLoaded", function () {
        const overlay = document.getElementById("settingsOverlay");
        const closeButton = document.getElementById("closeSettings");
        const doneButton = document.getElementById("settingsDone");
        const tabs = document.querySelectorAll(".settings-tab");
        const panels = document.querySelectorAll(".settings-panel");
        const message = document.getElementById("settingsMessage");

        function showMessage(text, isError) {
            if (!message) return;
            message.textContent = text;
            message.style.color = isError ? "#d64545" : "#269b59";
        }

        function showSection(name) {
            tabs.forEach(tab => tab.classList.toggle("active", tab.dataset.section === name));
            panels.forEach(panel => panel.classList.toggle("active", panel.id === "section-" + name));
            if (message) message.textContent = "";
        }

        function openSettings(section) {
            if (!overlay) return;
            overlay.classList.add("is-open");
            overlay.setAttribute("aria-hidden", "false");
            document.body.classList.add("settings-modal-open");
            showSection(section || "account");
            syncControlsFromStorage();
        }

        function closeSettings() {
            if (!overlay) return;
            overlay.classList.remove("is-open");
            overlay.setAttribute("aria-hidden", "true");
            document.body.classList.remove("settings-modal-open");
        }

        window.openStudyHiveSettings = openSettings;

        document.querySelectorAll("[data-open-settings]").forEach(trigger => {
            trigger.addEventListener("click", function (event) {
                event.preventDefault();
                openSettings(trigger.dataset.settingsSection || "account");
            });
        });

        tabs.forEach(tab => tab.addEventListener("click", () => showSection(tab.dataset.section)));
        if (closeButton) closeButton.addEventListener("click", closeSettings);
        if (doneButton) doneButton.addEventListener("click", closeSettings);

        if (overlay) {
            overlay.addEventListener("click", event => {
                if (event.target === overlay) closeSettings();
            });
        }
        document.addEventListener("keydown", event => {
            if (event.key === "Escape" && overlay && overlay.classList.contains("is-open")) {
                closeSettings();
            }
        });

        const focusInput = document.getElementById("defaultFocus");
        const breakInput = document.getElementById("defaultBreak");
        const themeLight = document.getElementById("themeLight");
        const themeDark = document.getElementById("themeDark");
        const themePreview = document.getElementById("themePreview");
        const themePreviewLabel = document.getElementById("themePreviewLabel");
        const pomodoroNotifications = document.getElementById("pomodoroNotifications");
        const notificationSound = document.getElementById("notificationSound");

        function syncControlsFromStorage() {
            const pomodoro = readSetting(KEYS.pomodoro, {});
            if (focusInput) focusInput.value = pomodoro.focusMinutes ?? pomodoro.focus ?? 25;
            if (breakInput) breakInput.value = pomodoro.breakMinutes ?? pomodoro.break ?? 5;

            const theme = applyTheme(readSetting(KEYS.theme, "light"));
            if (themeLight) themeLight.checked = theme === "light";
            if (themeDark) themeDark.checked = theme === "dark";
            if (themePreview) themePreview.classList.toggle("dark-preview", theme === "dark");
            if (themePreviewLabel) themePreviewLabel.textContent = theme === "dark" ? "Dark Mode Preview" : "Light Mode Preview";

            const notifications = readSetting(KEYS.notifications, { pomodoro: true, sound: true });
            if (pomodoroNotifications) pomodoroNotifications.checked = notifications.pomodoro !== false;
            if (notificationSound) notificationSound.checked = notifications.sound !== false;
        }

        // Load saved preferences whenever modal is initialized.
        syncControlsFromStorage();

        document.querySelectorAll(".preset-buttons button[data-focus]").forEach(button => {
            button.addEventListener("click", function () {
                if (focusInput) focusInput.value = Number(button.dataset.focus);
                document.querySelectorAll(".preset-buttons button").forEach(p => p.classList.remove("selected"));
                button.classList.add("selected");
            });
        });

        const saveStudyButton = document.getElementById("saveStudySettings");
        if (saveStudyButton) {
            saveStudyButton.addEventListener("click", function () {
                const focus = Number(focusInput && focusInput.value);
                const breakMinutes = Number(breakInput && breakInput.value);
                if (!Number.isInteger(focus) || focus < 1 || focus > 180) {
                    showMessage("Focus duration must be between 1 and 180 minutes.", true);
                    if (focusInput) focusInput.focus();
                    return;
                }
                if (!Number.isInteger(breakMinutes) || breakMinutes < 1 || breakMinutes > 60) {
                    showMessage("Break duration must be between 1 and 60 minutes.", true);
                    if (breakInput) breakInput.focus();
                    return;
                }
                if (writeSetting(KEYS.pomodoro, { focusMinutes: focus, breakMinutes: breakMinutes })) {
                    showMessage("Study preferences saved successfully!", false);
                    window.dispatchEvent(new CustomEvent("studyhive:settings-changed", {
                        detail: { key: KEYS.pomodoro, value: { focusMinutes: focus, breakMinutes: breakMinutes } }
                    }));
                } else {
                    showMessage("Unable to save preferences in this browser.", true);
                }
            });
        }

        function previewTheme(theme) {
            const selected = applyTheme(theme);
            if (themeLight) themeLight.checked = selected === "light";
            if (themeDark) themeDark.checked = selected === "dark";
            if (themePreview) themePreview.classList.toggle("dark-preview", selected === "dark");
            if (themePreviewLabel) themePreviewLabel.textContent = selected === "dark" ? "Dark Mode Preview" : "Light Mode Preview";
        }
        if (themeLight) themeLight.addEventListener("change", () => {
            if (themeLight.checked) previewTheme("light");
        });
        if (themeDark) themeDark.addEventListener("change", () => {
            if (themeDark.checked) previewTheme("dark");
        });

        const saveThemeButton = document.getElementById("saveThemeSettings");
        if (saveThemeButton) {
            saveThemeButton.addEventListener("click", function () {
                const selected = themeDark && themeDark.checked ? "dark" : "light";
                if (writeSetting(KEYS.theme, selected)) {
                    applyTheme(selected);
                    showMessage("Appearance settings saved!", false);
                    window.dispatchEvent(new CustomEvent("studyhive:settings-changed", {
                        detail: { key: KEYS.theme, value: selected }
                    }));
                } else {
                    showMessage("Unable to save appearance settings.", true);
                }
            });
        }

        const saveNotificationButton = document.getElementById("saveNotificationSettings");
        if (saveNotificationButton) {
            saveNotificationButton.addEventListener("click", function () {
                const preferences = {
                    pomodoro: pomodoroNotifications ? pomodoroNotifications.checked : true,
                    sound: notificationSound ? notificationSound.checked : true
                };
                if (writeSetting(KEYS.notifications, preferences)) {
                    showMessage("Notification preferences saved!", false);
                    window.dispatchEvent(new CustomEvent("studyhive:settings-changed", {
                        detail: { key: KEYS.notifications, value: preferences }
                    }));
                } else {
                    showMessage("Unable to save notification preferences.", true);
                }
            });
        }

        // Account name is updated via AJAX so the user remains on the current page.
        const accountForm = document.getElementById("settingsAccountForm");
        if (accountForm) {
            accountForm.addEventListener("submit", async function (event) {
                event.preventDefault();
                const button = accountForm.querySelector('button[type="submit"]');
                if (button) button.disabled = true;
                try {
                    const response = await fetch(accountForm.action, {
                        method: "POST",
                        body: new FormData(accountForm),
                        headers: { "X-Requested-With": "XMLHttpRequest" }
                    });
                    const result = await response.json();
                    if (!response.ok || !result.success) throw new Error(result.message || "Could not update account name.");

                    const name = result.name;
                    const heading = document.getElementById("settingsProfileName");
                    const avatar = document.querySelector(".settings-overlay .profile-avatar");
                    const nameInput = document.getElementById("accountName");
                    if (heading) heading.textContent = name;
                    if (avatar) avatar.textContent = name.charAt(0).toUpperCase();
                    if (nameInput) nameInput.value = name;
                    document.querySelectorAll("[data-user-name]").forEach(el => el.textContent = name);
                    showMessage("Account name updated successfully!", false);
                } catch (error) {
                    showMessage(error.message || "Could not update account name.", true);
                } finally {
                    if (button) button.disabled = false;
                }
            });
        }
    });
})();
