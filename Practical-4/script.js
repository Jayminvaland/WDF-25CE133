console.log("StudentHub Practical 4 JavaScript loaded successfully!");

/* ---------- HAMBURGER MENU ---------- */

const menuToggle = document.getElementById("menu-toggle");
const mainNavigation = document.getElementById("main-navigation");

if (menuToggle && mainNavigation) {
    menuToggle.addEventListener("click", function () {
        const isExpanded =
            menuToggle.getAttribute("aria-expanded") === "true";

        mainNavigation.classList.toggle("is-open", !isExpanded);

        menuToggle.setAttribute(
            "aria-expanded",
            String(!isExpanded)
        );

        menuToggle.textContent = isExpanded
            ? "☰ Menu"
            : "✕ Close Menu";
    });

    mainNavigation.querySelectorAll("a").forEach(function (link) {
        link.addEventListener("click", function () {
            if (window.matchMedia("(max-width: 767px)").matches) {
                mainNavigation.classList.remove("is-open");
                menuToggle.setAttribute("aria-expanded", "false");
                menuToggle.textContent = "☰ Menu";
            }
        });
    });
}


/* ---------- LIGHT / DARK THEME SWITCHER ---------- */

const themeToggle = document.getElementById("theme-toggle");

function applyTheme(theme) {
    const isLight = theme === "light";

    document.body.classList.toggle("light-theme", isLight);

    if (themeToggle) {
        themeToggle.textContent = isLight
            ? "🌙 Dark Mode"
            : "☀️ Light Mode";

        themeToggle.setAttribute(
            "aria-pressed",
            String(isLight)
        );
    }
}

if (themeToggle) {
    // Restore the previously selected theme
    const savedTheme = localStorage.getItem("studentHubTheme");

    applyTheme(savedTheme === "light" ? "light" : "dark");

    // Switch theme when the button is clicked
    themeToggle.addEventListener("click", function () {
        const isCurrentlyLight =
            document.body.classList.contains("light-theme");

        const newTheme = isCurrentlyLight ? "dark" : "light";

        applyTheme(newTheme);

        localStorage.setItem("studentHubTheme", newTheme);
    });
}

/* ---------- NOTIFICATION BANNER ---------- */

const notificationBanner = document.getElementById("notification-banner");
const dismissNotification = document.getElementById("dismiss-notification");

if (notificationBanner && dismissNotification) {
    dismissNotification.addEventListener("click", function () {
        notificationBanner.style.display = "none";
    });
}


/* ---------- MODAL POPUP ---------- */

const openModalButton = document.getElementById("open-modal");
const modalOverlay = document.getElementById("modal-overlay");
const closeModalButton = document.getElementById("close-modal");
const modalDoneButton = document.getElementById("modal-done");
const modalContent = document.querySelector(".modal-content");

if (
    openModalButton &&
    modalOverlay &&
    closeModalButton &&
    modalDoneButton &&
    modalContent
) {
    function openModal() {
        modalOverlay.hidden = false;
        modalOverlay.setAttribute("aria-hidden", "false");
        modalContent.focus();
    }

    function closeModal() {
        modalOverlay.hidden = true;
        modalOverlay.setAttribute("aria-hidden", "true");
        openModalButton.focus();
    }

    // Open popup
    openModalButton.addEventListener("click", openModal);

    // Close popup using either button
    closeModalButton.addEventListener("click", closeModal);
    modalDoneButton.addEventListener("click", closeModal);

    // Close when clicking outside the popup
    modalOverlay.addEventListener("click", function (event) {
        if (event.target === modalOverlay) {
            closeModal();
        }
    });

    // Close popup with the Escape key
    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && !modalOverlay.hidden) {
            closeModal();
        }
    });
}


/* ---------- CONTENT SLIDER ---------- */

const sliderSlides = document.querySelectorAll("#content-slider .slide");
const sliderPrevButton = document.getElementById("slider-prev");
const sliderNextButton = document.getElementById("slider-next");
const sliderIndicator = document.getElementById("slider-indicator");

if (
    sliderSlides.length > 0 &&
    sliderPrevButton &&
    sliderNextButton &&
    sliderIndicator
) {
    let currentSlideIndex = 0;

    function showSlide(index) {
        // Keep the index within the available slides
        currentSlideIndex =
            (index + sliderSlides.length) % sliderSlides.length;

        sliderSlides.forEach(function (slide, i) {
            const isActive = i === currentSlideIndex;

            slide.hidden = !isActive;
            slide.classList.toggle("active", isActive);
        });

        sliderIndicator.textContent =
            `Slide ${currentSlideIndex + 1} of ${sliderSlides.length}`;
    }

    // Show the first slide
    showSlide(0);

    // Show the previous slide
    sliderPrevButton.addEventListener("click", function () {
        showSlide(currentSlideIndex - 1);
    });

    // Show the next slide
    sliderNextButton.addEventListener("click", function () {
        showSlide(currentSlideIndex + 1);
    });
}


/* ---------- COLLAPSIBLE FAQ ---------- */

const faqQuestions = document.querySelectorAll(".faq-question");

faqQuestions.forEach(function (question) {
    question.addEventListener("click", function () {
        const answerId = question.getAttribute("aria-controls");
        const answer = document.getElementById(answerId);

        if (!answer) {
            return;
        }

        // Toggle the answer open or closed
        const isExpanded =
            question.getAttribute("aria-expanded") === "true";

        answer.hidden = isExpanded;

        question.setAttribute(
            "aria-expanded",
            String(!isExpanded)
        );

        // Update the plus/minus icon
        const icon = question.querySelector("span");

        if (icon) {
            icon.textContent = isExpanded ? "＋" : "−";
        }
    });
});
