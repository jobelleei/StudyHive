
document.addEventListener("DOMContentLoaded", function () {
    const editor = document.getElementById("noteEditor");

    initializeDashboard();

    if (!editor) return;

    // ==========================================
    // EDITOR SELECTION
    // ==========================================

    let savedRange = null;

    function saveSelection() {
        const selection = window.getSelection();

        if (!selection || selection.rangeCount === 0) return;

        const range = selection.getRangeAt(0);

        if (editor.contains(range.commonAncestorContainer)) {
            savedRange = range.cloneRange();
        }
    }

    function restoreSelection() {
        editor.focus();

        if (!savedRange) return false;

        try {
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(savedRange);
            return true;
        } catch (error) {
            return false;
        }
    }

    editor.addEventListener("mouseup", saveSelection);
    editor.addEventListener("keyup", saveSelection);
    editor.addEventListener("input", saveSelection);

    document.addEventListener("selectionchange", function () {
        const selection = window.getSelection();

        if (
            selection &&
            selection.rangeCount > 0 &&
            editor.contains(selection.getRangeAt(0).commonAncestorContainer)
        ) {
            saveSelection();
        }
    });

    // ==========================================
    // UNDO AND REDO
    // ==========================================

    let history = [editor.innerHTML];
    let historyIndex = 0;
    let historyTimer;

    function updateHistoryButtons() {
        const undo = document.querySelector('[data-command="undo"]');
        const redo = document.querySelector('[data-command="redo"]');

        if (undo) undo.disabled = historyIndex <= 0;
        if (redo) redo.disabled = historyIndex >= history.length - 1;
    }

    function saveHistory() {
        clearTimeout(historyTimer);

        historyTimer = setTimeout(function () {
            const currentContent = editor.innerHTML;

            if (history[historyIndex] === currentContent) return;

            history = history.slice(0, historyIndex + 1);
            history.push(currentContent);

            if (history.length > 100) {
                history.shift();
            }

            historyIndex = history.length - 1;
            updateHistoryButtons();

        }, 200);
    }

    function applyHistory(direction) {
        clearTimeout(historyTimer);

        // Save the latest content before undoing.
        const currentContent = editor.innerHTML;

        if (history[historyIndex] !== currentContent) {
            history = history.slice(0, historyIndex + 1);
            history.push(currentContent);

            if (history.length > 100) history.shift();

            historyIndex = history.length - 1;
        }

        const newIndex = historyIndex + direction;

        if (newIndex < 0 || newIndex >= history.length) return;

        historyIndex = newIndex;
        editor.innerHTML = history[historyIndex];

        editor.focus();

        const range = document.createRange();
        range.selectNodeContents(editor);
        range.collapse(false);

        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);

        savedRange = range.cloneRange();

        updateHistoryButtons();
    }

    editor.addEventListener("input", saveHistory);

    // ==========================================
    // TOOLBAR COMMANDS
    // ==========================================

    function executeCommand(command, value = null) {
        restoreSelection();

        document.execCommand(command, false, value);

        saveSelection();

        clearTimeout(historyTimer);

        history.push(editor.innerHTML);
        historyIndex = history.length - 1;

        if (history.length > 100) {
            history.shift();
            historyIndex = history.length - 1;
        }

        updateHistoryButtons();
    }

    document.querySelectorAll(
        ".editor-toolbar button[data-command]"
    ).forEach(function (button) {

        button.addEventListener("mousedown", function (event) {
            event.preventDefault();
            saveSelection();
        });

        button.addEventListener("click", function () {
            const command = this.dataset.command;

            if (command === "undo") {
                applyHistory(-1);
                return;
            }

            if (command === "redo") {
                applyHistory(1);
                return;
            }

            executeCommand(command);
        });
    });


// ==========================================
// FONT FAMILY - FIXED
// ==========================================

const fontFamily = document.getElementById("fontFamily");

if (fontFamily) {

    // Save selection before opening the dropdown.
    fontFamily.addEventListener("mousedown", function () {
        saveSelection();
    });

    fontFamily.addEventListener("change", function () {

        const selectedFont = this.value;

        if (!selectedFont) return;

        restoreSelection();

        const selection = window.getSelection();

        if (!selection || selection.rangeCount === 0) {
            editor.focus();
            return;
        }

        const range = selection.getRangeAt(0);

        if (!editor.contains(range.commonAncestorContainer)) {
            return;
        }

        const span = document.createElement("span");
        span.style.fontFamily = '"' + selectedFont + '"';

        if (range.collapsed) {

            // Apply selected font to text typed next.
            span.appendChild(document.createTextNode("\u200B"));
            range.insertNode(span);

            const caret = document.createRange();
            caret.setStart(span.firstChild, 1);
            caret.collapse(true);

            selection.removeAllRanges();
            selection.addRange(caret);

            saveSelection();

        } else {

            // Apply selected font to highlighted text.
            span.appendChild(range.extractContents());
            range.insertNode(span);

            const newRange = document.createRange();
            newRange.selectNodeContents(span);

            selection.removeAllRanges();
            selection.addRange(newRange);

            saveSelection();
        }

        // Update undo history.
        clearTimeout(historyTimer);

        history.push(editor.innerHTML);
        historyIndex = history.length - 1;

        if (history.length > 100) {
            history.shift();
            historyIndex = history.length - 1;
        }

        updateHistoryButtons();

    });
}

    // ==========================================
    // FONT SIZE
    // ==========================================

    const fontSizeInput = document.getElementById("fontSize");
    const decreaseButton = document.getElementById("fontDecrease");
    const increaseButton = document.getElementById("fontIncrease");

    function getFontSize() {
        let size = parseInt(fontSizeInput.value, 10);

        if (isNaN(size)) size = 12;

        size = Math.max(1, Math.min(200, size));

        fontSizeInput.value = size;

        return size;
    }

    function applyFontSize(size) {
        restoreSelection();

        const selection = window.getSelection();

        if (!selection || selection.rangeCount === 0) return;

        const range = selection.getRangeAt(0);

        const span = document.createElement("span");
        span.style.fontSize = size + "px";

        if (range.collapsed) {

            span.textContent = "\u200B";
            range.insertNode(span);

            const newRange = document.createRange();
            newRange.setStart(span.firstChild, 1);
            newRange.collapse(true);

            selection.removeAllRanges();
            selection.addRange(newRange);

            savedRange = newRange.cloneRange();

        } else {

            span.appendChild(range.extractContents());
            range.insertNode(span);

            const newRange = document.createRange();
            newRange.selectNodeContents(span);

            selection.removeAllRanges();
            selection.addRange(newRange);

            savedRange = newRange.cloneRange();
        }

        clearTimeout(historyTimer);
        history.push(editor.innerHTML);
        historyIndex = history.length - 1;

        updateHistoryButtons();
    }

    if (fontSizeInput) {

        fontSizeInput.addEventListener("change", function () {
            applyFontSize(getFontSize());
        });

        fontSizeInput.addEventListener("keydown", function (event) {

            if (event.key === "Enter") {
                event.preventDefault();

                applyFontSize(getFontSize());

                fontSizeInput.blur();
            }
        });
    }

    if (decreaseButton) {
        decreaseButton.addEventListener("click", function () {

            const size = Math.max(1, getFontSize() - 1);

            fontSizeInput.value = size;

            applyFontSize(size);
        });
    }

    if (increaseButton) {
        increaseButton.addEventListener("click", function () {

            const size = Math.min(200, getFontSize() + 1);

            fontSizeInput.value = size;

            applyFontSize(size);
        });
    }

    // ==========================================
    // TEXT COLOR
    // ==========================================

    const textColor = document.getElementById("textColor");

    if (textColor) {

        textColor.addEventListener("mousedown", saveSelection);

        textColor.addEventListener("input", function () {
            executeCommand("foreColor", this.value);
        });
    }

    // ==========================================
    // HIGHLIGHT COLOR
    // ==========================================

    const highlightColor = document.getElementById("highlightColor");

    if (highlightColor) {

        highlightColor.addEventListener("mousedown", saveSelection);

        highlightColor.addEventListener("input", function () {
            executeCommand("hiliteColor", this.value);
        });
    }

    // ==========================================
    // SAVE NOTE
    // ==========================================

    const noteForm = document.getElementById("noteForm");

    if (noteForm) {

        noteForm.addEventListener("submit", function (event) {

            const title = noteForm.querySelector(".editor-title");
            const content = document.getElementById("savedContent");

            if (!title.value.trim()) {
                event.preventDefault();
                alert("Please enter a note title.");
                title.focus();
                return;
            }

            content.value = editor.innerHTML;
        });
    }

    // ==========================================
    // KEYBOARD UNDO / REDO
    // ==========================================

    editor.addEventListener("keydown", function (event) {

        const isMac = navigator.platform
            .toUpperCase()
            .includes("MAC");

        const modifier = isMac ? event.metaKey : event.ctrlKey;

        if (modifier && event.key.toLowerCase() === "z") {

            event.preventDefault();

            if (event.shiftKey) {
                applyHistory(1);
            } else {
                applyHistory(-1);
            }
        }

        if (modifier && event.key.toLowerCase() === "y") {

            event.preventDefault();
            applyHistory(1);
        }
    });

    // ==========================================
    // SEARCH INSIDE NOTE
    // ==========================================

    const editorSearch = document.getElementById("editorSearch");

    if (editorSearch) {

        editorSearch.addEventListener("keydown", function (event) {

            if (event.key !== "Enter") return;

            const query = this.value.trim().toLowerCase();

            if (!query) return;

            const walker = document.createTreeWalker(
                editor,
                NodeFilter.SHOW_TEXT
            );

            let node;
            let found = false;

            while ((node = walker.nextNode())) {

                const position = node.textContent
                    .toLowerCase()
                    .indexOf(query);

                if (position === -1) continue;

                const range = document.createRange();

                range.setStart(node, position);
                range.setEnd(node, position + query.length);

                const selection = window.getSelection();

                selection.removeAllRanges();
                selection.addRange(range);

                savedRange = range.cloneRange();

                node.parentElement.scrollIntoView({
                    behavior: "smooth",
                    block: "center"
                });

                editor.focus();

                found = true;
                break;
            }

            if (!found) {
                alert("No matching text found.");
            }
        });
    }

    // ==========================================
    // TO-DO LIST
    // ==========================================

    const todoInput = document.getElementById("todoInput");
    const todoList = document.getElementById("todoList");
    const addTodoButton = document.getElementById("addTodoButton");

    const progressBar = document.getElementById("todoProgressBar");
    const progressLabel = document.getElementById("todoProgressLabel");

    function updateTodoProgress() {

        if (!todoList) return;

        const checkboxes = todoList.querySelectorAll(
            'input[type="checkbox"]'
        );

        const completed = todoList.querySelectorAll(
            'input[type="checkbox"]:checked'
        ).length;

        const total = checkboxes.length;

        const percentage = total
            ? (completed / total) * 100
            : 0;

        if (progressBar) {
            progressBar.style.width = percentage + "%";
        }

        if (progressLabel) {
            progressLabel.textContent =
                completed + " of " + total + " tasks completed";
        }
    }

    function addTodo() {

        const task = todoInput.value.trim();

        if (!task) return;

        const label = document.createElement("label");
        label.className = "todo-item";

        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";

        checkbox.addEventListener("change", updateTodoProgress);

        const text = document.createElement("span");
        text.textContent = task;

        label.appendChild(checkbox);
        label.appendChild(text);

        todoList.appendChild(label);

        todoInput.value = "";
        todoInput.focus();

        updateTodoProgress();
    }

    if (addTodoButton) {
        addTodoButton.addEventListener("click", addTodo);
    }

    if (todoInput) {

        todoInput.addEventListener("keydown", function (event) {

            if (event.key === "Enter") {
                event.preventDefault();
                addTodo();
            }
        });
    }

    updateTodoProgress();
    updateHistoryButtons();
});

// ==============================================
// DASHBOARD FUNCTIONS
// ==============================================

function initializeDashboard() {

    const folderForm = document.getElementById("folderForm");
    const showFolderForm = document.getElementById("showFolderForm");
    const cancelFolderForm = document.getElementById("cancelFolderForm");

    if (showFolderForm && folderForm) {

        showFolderForm.addEventListener("click", function () {

            folderForm.hidden = !folderForm.hidden;

            if (!folderForm.hidden) {

                folderForm.scrollIntoView({
                    behavior: "smooth",
                    block: "center"
                });

                document.getElementById("folder_name")?.focus();
            }
        });
    }

    if (cancelFolderForm && folderForm) {

        cancelFolderForm.addEventListener("click", function () {
            folderForm.hidden = true;
        });
    }

    // Dashboard search

    const dashboardSearch = document.getElementById("dashboardSearch");

    if (dashboardSearch) {

        dashboardSearch.addEventListener("input", function () {

            const query = this.value.toLowerCase().trim();

            document.querySelectorAll(".searchable").forEach(function (item) {

                const matches = item.textContent
                    .toLowerCase()
                    .includes(query);

                item.style.display = matches ? "" : "none";
            });
        });
    }
}
