/* =====================================================
   NOTE EDITOR (toolbar, fonts, sizes, search, to-dos)
===================================================== */

document.addEventListener("DOMContentLoaded", function () {

    // Dashboard search filters folder and note cards.
    const dashboardSearch = document.getElementById("dashboardSearch");

    if (dashboardSearch) {
        dashboardSearch.addEventListener("input", function () {
            const query = this.value.toLowerCase().trim();
            document.querySelectorAll(".searchable").forEach(function (item) {
                item.style.display = item.textContent.toLowerCase().includes(query) ? "" : "none";
            });
        });
    }

    const editor = document.getElementById("noteEditor");
    if (!editor) return;

    const FONT_STACKS = {
        "Arial": "Arial, Helvetica, sans-serif",
        "Times New Roman": "\"Times New Roman\", Times, serif",
        "Georgia": "Georgia, serif",
        "Verdana": "Verdana, Geneva, sans-serif",
        "Tahoma": "Tahoma, Verdana, sans-serif",
        "Courier New": "\"Courier New\", Courier, monospace"
    };

    const MIN_SIZE = 1;
    const MAX_SIZE = 200;

    const fontFamilySelect = document.getElementById("fontFamily");
    const fontSizeInput = document.getElementById("fontSize");
    const decreaseButton = document.getElementById("fontDecrease");
    const increaseButton = document.getElementById("fontIncrease");
    const textColor = document.getElementById("textColor");
    const highlightColor = document.getElementById("highlightColor");
    const commandButtons = document.querySelectorAll(".editor-toolbar button[data-command]");
    const undoButton = document.querySelector('.editor-toolbar [data-command="undo"]');
    const redoButton = document.querySelector('.editor-toolbar [data-command="redo"]');

    // ------------------------------------------
    // SELECTION HANDLING
    // ------------------------------------------

    let savedRange = null;

    function selectionInEditor() {
        const selection = window.getSelection();
        if (!selection || selection.rangeCount === 0) return null;
        const range = selection.getRangeAt(0);
        return editor.contains(range.commonAncestorContainer) ? range : null;
    }

    function saveSelection() {
        const range = selectionInEditor();
        if (range) savedRange = range.cloneRange();
    }

    function restoreSelection() {
        editor.focus({ preventScroll: true });
        const selection = window.getSelection();

        if (savedRange && editor.contains(savedRange.commonAncestorContainer)) {
            selection.removeAllRanges();
            selection.addRange(savedRange);
            return;
        }

        // No previous selection: place the caret at the end of the note.
        if (!selectionInEditor()) {
            const range = document.createRange();
            range.selectNodeContents(editor);
            range.collapse(false);
            selection.removeAllRanges();
            selection.addRange(range);
        }
    }

    function selectRange(range) {
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
        savedRange = range.cloneRange();
    }

    // ------------------------------------------
    // UNDO / REDO HISTORY
    // ------------------------------------------

    let history = [editor.innerHTML];
    let historyIndex = 0;
    let historyTimer = null;

    function updateHistoryButtons() {
        if (undoButton) undoButton.disabled = historyIndex <= 0;
        if (redoButton) redoButton.disabled = historyIndex >= history.length - 1;
    }

    function pushHistory() {
        clearTimeout(historyTimer);
        const content = editor.innerHTML;
        if (history[historyIndex] === content) return;

        history = history.slice(0, historyIndex + 1);
        history.push(content);
        if (history.length > 100) history.shift();
        historyIndex = history.length - 1;
        updateHistoryButtons();
    }

    function scheduleHistory() {
        clearTimeout(historyTimer);
        historyTimer = setTimeout(pushHistory, 300);
    }

    function applyHistory(direction) {
        pushHistory();
        const newIndex = historyIndex + direction;
        if (newIndex < 0 || newIndex >= history.length) return;

        historyIndex = newIndex;
        editor.innerHTML = history[historyIndex];

        const range = document.createRange();
        range.selectNodeContents(editor);
        range.collapse(false);
        editor.focus({ preventScroll: true });
        selectRange(range);

        updateHistoryButtons();
        updateToolbarState();
    }

    editor.addEventListener("input", scheduleHistory);

    // ------------------------------------------
    // INLINE STYLE APPLICATION (font + size)
    // ------------------------------------------

    // Wraps the current selection in spans carrying the given style.
    // Uses the browser's own <font> wrapping (which handles selections
    // across paragraphs, lists, etc.) and then converts the markers into
    // clean <span style="..."> elements.
    function applyInlineStyle(property, value) {
        restoreSelection();
        const range = selectionInEditor();
        if (!range) return;

        if (range.collapsed) {
            // Nothing selected: create a styled span so the next typed text uses it.
            const span = document.createElement("span");
            span.style[property] = value;
            span.appendChild(document.createTextNode("​"));
            range.insertNode(span);

            const caret = document.createRange();
            caret.setStart(span.firstChild, 1);
            caret.collapse(true);
            selectRange(caret);
            pushHistory();
            return;
        }

        document.execCommand("styleWithCSS", false, false);

        // Marker: font size 7 is a value we immediately replace.
        document.execCommand("fontSize", false, "7");

        const created = [];
        editor.querySelectorAll('font[size="7"]').forEach(function (font) {
            const span = document.createElement("span");

            // Keep any other attributes the browser merged into the <font>.
            if (font.getAttribute("style")) span.setAttribute("style", font.getAttribute("style"));
            if (font.getAttribute("color")) span.style.color = font.getAttribute("color");
            if (font.getAttribute("face")) span.style.fontFamily = font.getAttribute("face");
            span.style.removeProperty("font-size");
            span.style[property] = value;

            while (font.firstChild) span.appendChild(font.firstChild);
            font.replaceWith(span);
            created.push(span);
        });

        // Chrome sometimes writes the marker as a CSS keyword on an existing span.
        editor.querySelectorAll("[style]").forEach(function (el) {
            if (el.style.fontSize === "xxx-large" || el.style.fontSize === "-webkit-xxx-large") {
                el.style.removeProperty("font-size");
                el.style[property] = value;
                created.push(el);
            }
        });

        // Descendants with their own value would override the new one.
        created.forEach(function (span) {
            span.querySelectorAll("*").forEach(function (child) {
                if (child.style && child.style[property]) {
                    child.style.removeProperty(property === "fontFamily" ? "font-family" : "font-size");
                    if (!child.getAttribute("style")) child.removeAttribute("style");
                }
            });
            span.querySelectorAll("font").forEach(function (font) {
                if (property === "fontFamily") font.removeAttribute("face");
                if (property === "fontSize") font.removeAttribute("size");
            });
        });

        document.execCommand("styleWithCSS", false, true);

        if (created.length) {
            const newRange = document.createRange();
            newRange.setStartBefore(created[0]);
            newRange.setEndAfter(created[created.length - 1]);
            selectRange(newRange);
        } else {
            saveSelection();
        }

        pushHistory();
        updateToolbarState();
    }

    // ------------------------------------------
    // TOOLBAR BUTTONS
    // ------------------------------------------

    function executeCommand(command, value) {
        restoreSelection();
        document.execCommand("styleWithCSS", false, true);
        document.execCommand(command, false, value === undefined ? null : value);
        saveSelection();
        pushHistory();
        updateToolbarState();
    }

    commandButtons.forEach(function (button) {
        // Keep the editor selection when the button is pressed.
        button.addEventListener("mousedown", function (event) {
            event.preventDefault();
        });

        button.addEventListener("click", function () {
            const command = this.dataset.command;
            if (command === "undo") return applyHistory(-1);
            if (command === "redo") return applyHistory(1);
            executeCommand(command);
        });
    });

    // ------------------------------------------
    // FONT FAMILY
    // ------------------------------------------

    if (fontFamilySelect) {
        fontFamilySelect.addEventListener("change", function () {
            const stack = FONT_STACKS[this.value] || this.value;
            applyInlineStyle("fontFamily", stack);
        });
    }

    // ------------------------------------------
    // FONT SIZE
    // ------------------------------------------

    function clampSize(value) {
        let size = parseInt(value, 10);
        if (isNaN(size)) size = 16;
        return Math.max(MIN_SIZE, Math.min(MAX_SIZE, size));
    }

    function setFontSize(size) {
        size = clampSize(size);
        fontSizeInput.value = size;
        applyInlineStyle("fontSize", size + "px");
    }

    if (fontSizeInput) {
        fontSizeInput.addEventListener("keydown", function (event) {
            if (event.key === "Enter") {
                event.preventDefault();
                setFontSize(this.value);
            } else if (event.key === "ArrowUp" || event.key === "ArrowDown") {
                event.preventDefault();
                setFontSize(clampSize(this.value) + (event.key === "ArrowUp" ? 1 : -1));
                this.focus();
            }
        });

        fontSizeInput.addEventListener("change", function () {
            setFontSize(this.value);
        });
    }

    [decreaseButton, increaseButton].forEach(function (button) {
        if (!button) return;
        button.addEventListener("mousedown", function (event) {
            event.preventDefault();
        });
        button.addEventListener("click", function () {
            const step = button === increaseButton ? 1 : -1;
            setFontSize(clampSize(fontSizeInput.value) + step);
        });
    });

    // ------------------------------------------
    // TEXT AND HIGHLIGHT COLOR
    // ------------------------------------------

    if (textColor) {
        textColor.addEventListener("input", function () {
            executeCommand("foreColor", this.value);
        });
    }

    if (highlightColor) {
        highlightColor.addEventListener("input", function () {
            restoreSelection();
            document.execCommand("styleWithCSS", false, true);
            // Chrome/Safari use hiliteColor; fall back to backColor.
            if (!document.execCommand("hiliteColor", false, this.value)) {
                document.execCommand("backColor", false, this.value);
            }
            saveSelection();
            pushHistory();
        });
    }

    // ------------------------------------------
    // TOOLBAR STATE (active buttons, font, size)
    // ------------------------------------------

    function normalizeFontName(name) {
        return String(name || "").split(",")[0].replace(/["']/g, "").trim().toLowerCase();
    }

    function updateToolbarState() {
        const range = selectionInEditor();

        ["bold", "italic", "underline", "justifyLeft", "justifyCenter",
            "insertUnorderedList", "insertOrderedList"].forEach(function (command) {
            const button = document.querySelector('.editor-toolbar [data-command="' + command + '"]');
            if (!button) return;
            let active = false;
            try { active = range ? document.queryCommandState(command) : false; } catch (e) {}
            button.classList.toggle("active", active);
            button.setAttribute("aria-pressed", String(active));
        });

        if (!range) return;

        let node = range.startContainer;
        // When the range starts between elements, look at the element it starts on.
        if (node.nodeType === Node.ELEMENT_NODE && node.childNodes[range.startOffset] && !range.collapsed) {
            node = node.childNodes[range.startOffset];
        }
        if (node.nodeType !== Node.ELEMENT_NODE) node = node.parentElement;
        if (!node || !editor.contains(node)) node = editor;

        const computed = window.getComputedStyle(node);

        if (fontFamilySelect) {
            const current = normalizeFontName(computed.fontFamily);
            const match = Array.from(fontFamilySelect.options).find(function (option) {
                return normalizeFontName(option.value) === current;
            });
            if (match) fontFamilySelect.value = match.value;
        }

        if (fontSizeInput && document.activeElement !== fontSizeInput) {
            fontSizeInput.value = Math.round(parseFloat(computed.fontSize)) || 16;
        }
    }

    document.addEventListener("selectionchange", function () {
        if (selectionInEditor()) {
            saveSelection();
            updateToolbarState();
        }
    });

    // ------------------------------------------
    // KEYBOARD SHORTCUTS
    // ------------------------------------------

    editor.addEventListener("keydown", function (event) {
        const modifier = event.metaKey || event.ctrlKey;
        if (!modifier) return;
        const key = event.key.toLowerCase();

        if (key === "z") {
            event.preventDefault();
            applyHistory(event.shiftKey ? 1 : -1);
        } else if (key === "y") {
            event.preventDefault();
            applyHistory(1);
        } else if (key === "b" || key === "i" || key === "u") {
            event.preventDefault();
            executeCommand({ b: "bold", i: "italic", u: "underline" }[key]);
        }
    });

    // ------------------------------------------
    // SAVE NOTE
    // ------------------------------------------

    const noteForm = document.getElementById("noteForm");

    if (noteForm) {
        noteForm.addEventListener("submit", function (event) {
            const title = noteForm.querySelector(".editor-title");
            if (title && !title.value.trim()) {
                event.preventDefault();
                alert("Please enter a note title.");
                title.focus();
                return;
            }
            // Remove helper zero-width spaces before saving.
            document.getElementById("savedContent").value =
                editor.innerHTML.replace(/​/g, "");
        });
    }

    // ------------------------------------------
    // SEARCH INSIDE NOTE (Enter = next match)
    // ------------------------------------------

    const editorSearch = document.getElementById("editorSearch");
    let lastQuery = "";
    let matchIndex = -1;

    if (editorSearch) {
        editorSearch.addEventListener("keydown", function (event) {
            if (event.key !== "Enter") return;
            event.preventDefault();

            const query = this.value.trim().toLowerCase();
            if (!query) return;

            const matches = [];
            const walker = document.createTreeWalker(editor, NodeFilter.SHOW_TEXT);
            let node;
            while ((node = walker.nextNode())) {
                const text = node.textContent.toLowerCase();
                let position = text.indexOf(query);
                while (position !== -1) {
                    matches.push({ node: node, position: position });
                    position = text.indexOf(query, position + query.length);
                }
            }

            if (!matches.length) {
                alert("No matching text found.");
                return;
            }

            matchIndex = query === lastQuery ? (matchIndex + 1) % matches.length : 0;
            lastQuery = query;

            const match = matches[matchIndex];
            const range = document.createRange();
            range.setStart(match.node, match.position);
            range.setEnd(match.node, match.position + query.length);

            editor.focus({ preventScroll: true });
            selectRange(range);
            match.node.parentElement.scrollIntoView({ behavior: "smooth", block: "center" });
            // Return focus so Enter can jump to the next match.
            editorSearch.focus();
        });
    }

    // ------------------------------------------
    // TO-DO LIST
    // ------------------------------------------

    const todoInput = document.getElementById("todoInput");
    const todoList = document.getElementById("todoList");
    const addTodoButton = document.getElementById("addTodoButton");
    const progressBar = document.getElementById("todoProgressBar");
    const progressLabel = document.getElementById("todoProgressLabel");

    const todoStorageKey = "studyhiveTodos:" + (
        (document.querySelector('input[name="note_id"]') || {}).value ||
        "folder-" + ((document.querySelector('#noteForm input[name="folder_id"]') || {}).value || "0")
    );

    function readTodos() {
        try {
            return JSON.parse(localStorage.getItem(todoStorageKey)) || [];
        } catch (error) {
            return [];
        }
    }

    function saveTodos() {
        if (!todoList) return;
        const todos = Array.from(todoList.querySelectorAll(".todo-item")).map(function (item) {
            return {
                text: item.querySelector("span").textContent,
                done: item.querySelector("input").checked
            };
        });
        try { localStorage.setItem(todoStorageKey, JSON.stringify(todos)); } catch (error) {}
    }

    function updateTodoProgress() {
        if (!todoList) return;
        const total = todoList.querySelectorAll('input[type="checkbox"]').length;
        const completed = todoList.querySelectorAll('input[type="checkbox"]:checked').length;
        if (progressBar) progressBar.style.width = (total ? (completed / total) * 100 : 0) + "%";
        if (progressLabel) progressLabel.textContent = completed + " of " + total + " tasks completed";
    }

    function renderTodo(task, done) {
        const label = document.createElement("label");
        label.className = "todo-item";

        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.checked = Boolean(done);
        checkbox.addEventListener("change", function () {
            updateTodoProgress();
            saveTodos();
        });

        const text = document.createElement("span");
        text.textContent = task;

        const remove = document.createElement("button");
        remove.type = "button";
        remove.className = "todo-remove";
        remove.setAttribute("aria-label", "Remove task");
        remove.textContent = "×";
        remove.addEventListener("click", function (event) {
            event.preventDefault();
            label.remove();
            updateTodoProgress();
            saveTodos();
        });

        label.append(checkbox, text, remove);
        todoList.appendChild(label);
    }

    function addTodo() {
        const task = todoInput.value.trim();
        if (!task) return;
        renderTodo(task, false);
        todoInput.value = "";
        todoInput.focus();
        updateTodoProgress();
        saveTodos();
    }

    if (todoList) {
        readTodos().forEach(function (todo) { renderTodo(todo.text, todo.done); });
    }
    if (addTodoButton && todoInput) addTodoButton.addEventListener("click", addTodo);
    if (todoInput) {
        todoInput.addEventListener("keydown", function (event) {
            if (event.key === "Enter") {
                event.preventDefault();
                addTodo();
            }
        });
    }

    document.execCommand("styleWithCSS", false, true);
    updateTodoProgress();
    updateHistoryButtons();
});

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