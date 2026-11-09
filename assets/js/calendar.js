document.addEventListener("DOMContentLoaded", () => {

    const modal = document.getElementById("eventModal");
    const form = document.getElementById("eventForm");

    const modalTitle = document.getElementById("modalTitle");

    const eventId = document.getElementById("eventId");
    const eventType = document.getElementById("eventType");
    const eventTitle = document.getElementById("eventTitle");
    const eventDescription = document.getElementById("eventDescription");
    const eventDate = document.getElementById("eventDate");

    const startTime = document.getElementById("startTime");
    const endTime = document.getElementById("endTime");

    const monthPicker = document.getElementById("monthPicker");
    const monthPickerButton = document.getElementById("monthPickerButton");

    if (!modal || !form) return;


    // OPEN ADD OR EDIT MODAL
    function openModal(mode = "create", data = {}) {

        form.reset();

        if (mode === "edit") {

            modalTitle.textContent = "Edit event or task";

            eventId.value = data.id || "0";
            eventType.value = data.type || "event";
            eventTitle.value = data.title || "";
            eventDescription.value = data.description || "";
            eventDate.value = data.date || window.studyHiveCalendar.selectedDate;

            startTime.value = data.start || "";
            endTime.value = data.end || "";

        } else {

            modalTitle.textContent = "Add to your day";

            eventId.value = "0";
            eventType.value = "event";

            eventDate.value = data.date || window.studyHiveCalendar.selectedDate;

            eventTitle.value = "";
            eventDescription.value = "";

            startTime.value = "";
            endTime.value = "";
        }

        modal.classList.add("is-open");
        modal.setAttribute("aria-hidden", "false");

        document.body.style.overflow = "hidden";

        setTimeout(() => {
            eventTitle.focus();
        }, 50);
    }


    // CLOSE MODAL
    function closeModal() {

        modal.classList.remove("is-open");
        modal.setAttribute("aria-hidden", "true");

        document.body.style.overflow = "";
    }


    // ADD BUTTONS
    document.getElementById("openAddModal")
        ?.addEventListener("click", () => {
            openModal();
        });

    document.getElementById("openAddModalBottom")
        ?.addEventListener("click", () => {
            openModal();
        });


    // CLOSE BUTTONS
    document.getElementById("closeModal")
        ?.addEventListener("click", closeModal);

    document.getElementById("cancelModal")
        ?.addEventListener("click", closeModal);


    // CLOSE WHEN CLICKING OUTSIDE MODAL
    modal.addEventListener("click", (event) => {

        if (event.target === modal) {
            closeModal();
        }

    });


    // CLOSE USING ESCAPE KEY
    document.addEventListener("keydown", (event) => {

        if (
            event.key === "Escape" &&
            modal.classList.contains("is-open")
        ) {
            closeModal();
        }

    });


    // EDIT EXISTING EVENT OR TASK
    document.querySelectorAll(".edit-item-button").forEach((button) => {

        button.addEventListener("click", () => {

            openModal("edit", {
                id: button.dataset.id,
                title: button.dataset.title,
                description: button.dataset.description,
                date: button.dataset.date,
                start: button.dataset.start,
                end: button.dataset.end,
                type: button.dataset.type
            });

        });

    });


    // SELECT DATE ON CALENDAR
    document.querySelectorAll(".calendar-day").forEach((button) => {

        button.addEventListener("click", () => {

            const date = button.dataset.date;
            const month = date.slice(0, 7);

            window.location.href =
                `calendar.php?month=${encodeURIComponent(month)}&date=${encodeURIComponent(date)}`;

        });

    });


    // MONTH PICKER
    monthPickerButton?.addEventListener("click", () => {

        if (typeof monthPicker.showPicker === "function") {
            monthPicker.showPicker();
        } else {
            monthPicker.click();
        }

    });


    // CHANGE MONTH
    monthPicker?.addEventListener("change", () => {

        if (!monthPicker.value) return;

        const chosenMonth = monthPicker.value;

        window.location.href =
            `calendar.php?month=${encodeURIComponent(chosenMonth)}&date=${encodeURIComponent(chosenMonth)}-01`;

    });


    // FORM VALIDATION
    form.addEventListener("submit", (event) => {

        if (!eventTitle.value.trim()) {

            event.preventDefault();

            alert("Please enter a title.");

            eventTitle.focus();

            return;
        }

        if (
            startTime.value &&
            endTime.value &&
            endTime.value <= startTime.value
        ) {

            event.preventDefault();

            alert("End time must be later than start time.");

        }

    });

});