/* =====================================================
   POMODORO TIMER
===================================================== */

document.addEventListener("DOMContentLoaded", function () {

    // Folder form controls must initialize even on the dashboard,
    // where the Pomodoro timer is not rendered.
    const showFolderFormButton = document.getElementById("showFolderForm");
    const folderForm = document.getElementById("folderForm");
    const cancelFolderFormButton = document.getElementById("cancelFolderForm");

    if (showFolderFormButton && folderForm) {
        showFolderFormButton.addEventListener("click", function () {
            folderForm.hidden = !folderForm.hidden;
            if (!folderForm.hidden) {
                const nameInput = document.getElementById("folder_name");
                if (nameInput) nameInput.focus();
            }
        });
    }

    if (cancelFolderFormButton && folderForm) {
        cancelFolderFormButton.addEventListener("click", function () {
            folderForm.hidden = true;
            folderForm.reset();
        });
    }

    const toggle = document.getElementById("pomodoroToggle");
    const panel = document.getElementById("pomodoroPanel");

    if (!toggle || !panel) return;

    const closeButton = document.getElementById("pomodoroClose");
    const display = document.getElementById("pomodoroDisplay");
    const modeLabel = document.getElementById("pomodoroModeLabel");
    const statusLabel = document.getElementById("pomodoroStatus");
    const startButton = document.getElementById("pomodoroStart");
    const resetButton = document.getElementById("pomodoroReset");
    const skipButton = document.getElementById("pomodoroSkip");
    const focusInput = document.getElementById("focusDuration");
    const breakInput = document.getElementById("breakDuration");
    const saveSettingsButton = document.getElementById("pomodoroSaveSettings");
    const sessionsDisplay = document.getElementById("pomodoroSessions");
    const circle = document.getElementById("pomodoroCircle");
    const tabs = document.querySelectorAll(".pomodoro-tab");

    const STORAGE_KEY = "studyhivePomodoroSettings";
    const STATE_KEY = "studyhivePomodoroState";

    function readStorage(key, fallback) {
        try {
            const value = localStorage.getItem(key);
            return value ? JSON.parse(value) : fallback;
        } catch (error) {
            return fallback;
        }
    }

    function saveStorage(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
        } catch (error) {
            // Timer remains usable if storage is unavailable.
        }
    }

    const savedSettings = readStorage(STORAGE_KEY, {});
    const savedState = readStorage(STATE_KEY, {});

    let focusMinutes = Number(savedSettings.focusMinutes) || 25;
    let breakMinutes = Number(savedSettings.breakMinutes) || 5;
    let completedSessions = Number(savedState.completedSessions) || 0;

    let mode = savedState.mode === "break" ? "break" : "focus";

    let remainingSeconds = Number.isFinite(Number(savedState.remainingSeconds))
        ? Math.max(0, Number(savedState.remainingSeconds))
        : focusMinutes * 60;

    let isRunning = Boolean(savedState.isRunning);
    let endTime = Number(savedState.endTime) || null;
    let intervalId = null;

    focusInput.value = focusMinutes;
    breakInput.value = breakMinutes;

    function getDurationSeconds() {
        return (mode === "focus" ? focusMinutes : breakMinutes) * 60;
    }

    function formatTime(seconds) {
        const safeSeconds = Math.max(0, Math.ceil(seconds));
        const minutes = Math.floor(safeSeconds / 60);
        const secondsPart = safeSeconds % 60;

        return String(minutes).padStart(2, "0") + ":" +
            String(secondsPart).padStart(2, "0");
    }

    function saveTimerState() {
        saveStorage(STATE_KEY, {
            mode: mode,
            remainingSeconds: remainingSeconds,
            isRunning: isRunning,
            endTime: endTime,
            completedSessions: completedSessions
        });
    }

    function renderTimer() {
        display.textContent = formatTime(remainingSeconds);
        modeLabel.textContent = mode === "focus" ? "FOCUS TIME" : "BREAK TIME";
        sessionsDisplay.textContent = completedSessions;
        startButton.textContent = isRunning ? "PAUSE" : "START";

        tabs.forEach(function (tab) {
            tab.classList.toggle("active", tab.dataset.mode === mode);
        });

        circle.setAttribute(
            "aria-label",
            (mode === "focus" ? "Focus time " : "Break time ") +
            formatTime(remainingSeconds)
        );

        document.title = isRunning
            ? formatTime(remainingSeconds) + " - StudyHive"
            : "My Notes | StudyHive";

        saveTimerState();
    }

    function setStatus(message) {
        statusLabel.textContent = message;
    }

    function stopInterval() {
        if (intervalId !== null) {
            clearInterval(intervalId);
            intervalId = null;
        }
    }

    function switchMode(nextMode, message) {
        stopInterval();

        mode = nextMode;
        isRunning = false;
        endTime = null;
        remainingSeconds = getDurationSeconds();

        setStatus(message || (
            mode === "focus"
                ? "Ready to focus?"
                : "Take a short break."
        ));

        renderTimer();
    }

    function showTimerAlert(title, message, completedMode) {
        // Always show an in-page alert, even if browser notifications are blocked.
        let alertBox = document.getElementById("pomodoroCompletionAlert");

        if (!alertBox) {
            alertBox = document.createElement("div");
            alertBox.id = "pomodoroCompletionAlert";
            alertBox.className = "pomodoro-completion-alert";
            alertBox.setAttribute("role", "alert");
            alertBox.setAttribute("aria-live", "assertive");
            alertBox.innerHTML = `
                <div class="pomodoro-alert-icon" aria-hidden="true">✓</div>
                <div class="pomodoro-alert-copy">
                    <strong class="pomodoro-alert-title"></strong>
                    <span class="pomodoro-alert-message"></span>
                </div>
                <button type="button" class="pomodoro-alert-close" aria-label="Dismiss notification">&times;</button>
            `;
            document.body.appendChild(alertBox);

            alertBox.querySelector(".pomodoro-alert-close").addEventListener("click", function () {
                alertBox.classList.remove("show");
            });
        }

        alertBox.querySelector(".pomodoro-alert-title").textContent = title;
        alertBox.querySelector(".pomodoro-alert-message").textContent = message;
        alertBox.classList.toggle("break-complete", completedMode === "break");
        alertBox.classList.add("show");

        clearTimeout(alertBox.dismissTimer);
        alertBox.dismissTimer = setTimeout(function () {
            alertBox.classList.remove("show");
        }, 9000);

        // Also send an operating-system notification when permission is available.
        if ("Notification" in window && Notification.permission === "granted") {
            try {
                new Notification(title, { body: message, tag: "studyhive-pomodoro" });
            } catch (error) {
                // The in-page alert remains available if system notifications fail.
            }
        }

        // Short sound cue; browsers may suppress it if audio has not been enabled.
        try {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (AudioContextClass) {
                const audioContext = new AudioContextClass();
                const oscillator = audioContext.createOscillator();
                const gain = audioContext.createGain();
                oscillator.connect(gain);
                gain.connect(audioContext.destination);
                oscillator.frequency.value = completedMode === "focus" ? 880 : 660;
                gain.gain.setValueAtTime(0.0001, audioContext.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.12, audioContext.currentTime + 0.03);
                gain.gain.exponentialRampToValueAtTime(0.0001, audioContext.currentTime + 0.65);
                oscillator.start();
                oscillator.stop(audioContext.currentTime + 0.7);
                oscillator.onended = function () { audioContext.close(); };
            }
        } catch (error) {
            // Sound is optional; visual alerts are the primary fallback.
        }
    }

    async function saveCompletedSession() {

        const formData = new URLSearchParams();
        formData.append("action", "save_pomodoro_session");
        formData.append("csrf_token", toggle.dataset.csrf || "");
        formData.append("focus_minutes", String(focusMinutes));
        formData.append("break_minutes", String(breakMinutes));
        formData.append("folder_id", String(toggle.dataset.folderId || 0));
        formData.append("note_id", String(toggle.dataset.noteId || 0));

        try {
            const response = await fetch("notes.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
                },
                body: formData.toString()
            });
            const result = await response.json();
            if (!response.ok || !result.success) {
                console.error("Unable to save Pomodoro session:", result.message || response.status);
            }
        } catch (error) {
            console.error("Pomodoro session recording error:", error);
        }
    }

    function finishSession() {
        stopInterval();

        const completedMode = mode;
        isRunning = false;
        endTime = null;

        if (completedMode === "focus") {
            completedSessions++;
            saveCompletedSession();

            switchMode(
                "break",
                "Focus session complete! Take a break."
            );

            showTimerAlert(
                "Focus session completed!",
                "Great work! Your focus time is finished. Time to take a break.",
                completedMode
            );

        } else {

            switchMode(
                "focus",
                "Break finished. Ready to focus?"
            );

            showTimerAlert(
                "Break time is over!",
                "Your break has finished. You're ready for another focus session.",
                completedMode
            );

        }

        renderTimer();
    }

    function tick() {
        if (!isRunning || !endTime) return;

        remainingSeconds = Math.max(
            0,
            Math.ceil((endTime - Date.now()) / 1000)
        );

        if (remainingSeconds <= 0) {
            finishSession();
            return;
        }

        display.textContent = formatTime(remainingSeconds);
        sessionsDisplay.textContent = completedSessions;

        document.title = formatTime(remainingSeconds) + " - StudyHive";

        saveTimerState();
    }

    function startTimer() {
        if (isRunning) return;

        // A focus session must belong to a saved note so Achievements
        // can display the correct note title and summed focus duration.
        if (mode === "focus" && Number(toggle.dataset.noteId || 0) < 1) {
            alert("Please save your note first. Then open it again and start the Focus Timer so this session can be linked to its note title in Achievements.");
            return;
        }

        if (remainingSeconds <= 0) {
            remainingSeconds = getDurationSeconds();
        }

        isRunning = true;
        endTime = Date.now() + remainingSeconds * 1000;

        setStatus(
            mode === "focus"
                ? "Stay focused. You've got this!"
                : "Relax and recharge."
        );

        stopInterval();
        intervalId = setInterval(tick, 250);

        if (
            "Notification" in window &&
            Notification.permission === "default"
        ) {
            Notification.requestPermission().catch(function () {});
        }

        renderTimer();
    }

    function pauseTimer() {
        if (!isRunning) return;

        remainingSeconds = Math.max(
            0,
            Math.ceil((endTime - Date.now()) / 1000)
        );

        isRunning = false;
        endTime = null;

        stopInterval();

        setStatus("Timer paused.");

        renderTimer();
    }

    function resetTimer() {
        stopInterval();

        isRunning = false;
        endTime = null;
        remainingSeconds = getDurationSeconds();

        setStatus("Timer reset.");

        renderTimer();
    }

    // OPEN AND CLOSE TIMER

    toggle.addEventListener("click", function () {
        panel.hidden = !panel.hidden;

        toggle.setAttribute(
            "aria-expanded",
            String(!panel.hidden)
        );
    });

    if (closeButton) {
        closeButton.addEventListener("click", function () {
            panel.hidden = true;
            toggle.setAttribute("aria-expanded", "false");
        });
    }

    // START AND PAUSE

    startButton.addEventListener("click", function () {
        if (isRunning) {
            pauseTimer();
        } else {
            startTimer();
        }
    });

    // RESET

    resetButton.addEventListener("click", resetTimer);

    // SKIP CURRENT SESSION

    skipButton.addEventListener("click", function () {
        switchMode(
            mode === "focus" ? "break" : "focus",
            mode === "focus"
                ? "Focus skipped. Take a break."
                : "Break skipped. Back to focus!"
        );
    });

    // SWITCH BETWEEN FOCUS AND BREAK

    tabs.forEach(function (tab) {
        tab.addEventListener("click", function () {

            const selectedMode = tab.dataset.mode;

            if (
                selectedMode === "focus" ||
                selectedMode === "break"
            ) {
                switchMode(
                    selectedMode,
                    selectedMode === "focus"
                        ? "Ready to focus?"
                        : "Take a short break."
                );
            }

        });
    });

    // SAVE TIMER SETTINGS

    saveSettingsButton.addEventListener("click", function () {

        const newFocus = Number(focusInput.value);
        const newBreak = Number(breakInput.value);

        if (
            !Number.isInteger(newFocus) ||
            newFocus < 1 ||
            newFocus > 180
        ) {
            alert("Focus length must be between 1 and 180 minutes.");
            focusInput.focus();
            return;
        }

        if (
            !Number.isInteger(newBreak) ||
            newBreak < 1 ||
            newBreak > 60
        ) {
            alert("Break length must be between 1 and 60 minutes.");
            breakInput.focus();
            return;
        }

        focusMinutes = newFocus;
        breakMinutes = newBreak;

        saveStorage(STORAGE_KEY, {
            focusMinutes: focusMinutes,
            breakMinutes: breakMinutes
        });

        resetTimer();

        setStatus("Timer settings saved.");

        renderTimer();
    });

    // RESTORE TIMER AFTER PAGE REFRESH

    if (isRunning && endTime) {

        remainingSeconds = Math.max(
            0,
            Math.ceil((endTime - Date.now()) / 1000)
        );

        if (remainingSeconds <= 0) {
            finishSession();
        } else {
            intervalId = setInterval(tick, 250);
        }

    } else {

        isRunning = false;
        endTime = null;

    }

    renderTimer();

});