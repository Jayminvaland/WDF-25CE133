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


/* ==========================================
   PRACTICAL 5 - REGISTRATION FORM VALIDATION
========================================== */

const registrationForm = document.getElementById("registration-form");

if (registrationForm) {
    const registrationFieldNames = [
        "fullname",
        "email",
        "mobile",
        "course",
        "year",
        "gender",
        "password",
        "confirm-password",
        "terms"
    ];

    const passwordInput = document.getElementById("password");
    const confirmPasswordInput =
        document.getElementById("confirm-password");

    const strengthMeter =
        document.getElementById("password-strength-meter");

    const strengthText =
        document.getElementById("password-strength-text");

    const formMessage = document.getElementById("form-message");

    let validationAttempted = false;

    /* Show or clear an error beside a field */
    function setRegistrationError(fieldName, message) {
        const errorElement =
            document.getElementById(fieldName + "-error");

        if (errorElement) {
            errorElement.textContent = message;
        }

        let controls = [];

        if (fieldName === "gender") {
            controls = Array.from(
                document.querySelectorAll('input[name="gender"]')
            );
        } else {
            const field = document.getElementById(fieldName);

            if (field) {
                controls = [field];
            }
        }

        controls.forEach(function (control) {
            if (message) {
                control.setAttribute("aria-invalid", "true");
            } else {
                control.removeAttribute("aria-invalid");
            }
        });
    }

    /* Password strength indicator */
    function updatePasswordStrength() {
        const password = passwordInput.value;

        const criteria = [
            password.length >= 8,
            /[a-z]/.test(password) && /[A-Z]/.test(password),
            /\d/.test(password),
            /[^A-Za-z0-9]/.test(password)
        ];

        const score = criteria.filter(Boolean).length;

        strengthMeter.value = score;

        if (!password) {
            strengthText.textContent = "Not entered";
        } else if (score <= 1) {
            strengthText.textContent = "Weak";
        } else if (score === 2) {
            strengthText.textContent = "Fair";
        } else if (score === 3) {
            strengthText.textContent = "Good";
        } else {
            strengthText.textContent = "Strong";
        }
    }

    /* Validate an individual field */
    function validateRegistrationField(fieldName) {
        let error = "";

        switch (fieldName) {
            case "fullname": {
                const name =
                    document.getElementById("fullname").value.trim();

                const nameRegex = /^[A-Za-z][A-Za-z .'-]{1,49}$/;

                if (!name) {
                    error = "Full name is required.";
                } else if (!nameRegex.test(name)) {
                    error =
                        "Enter a valid name using at least 2 letters. " +
                        "Spaces, apostrophes, periods and hyphens are allowed.";
                }
                break;
            }

            case "email": {
                const email =
                    document.getElementById("email").value.trim();

                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

                if (!email) {
                    error = "Email address is required.";
                } else if (!emailRegex.test(email)) {
                    error = "Enter a valid email address.";
                }
                break;
            }

            case "mobile": {
                const mobile =
                    document.getElementById("mobile").value.trim();

                const mobileRegex = /^[6-9]\d{9}$/;

                if (!mobile) {
                    error = "Mobile number is required.";
                } else if (!mobileRegex.test(mobile)) {
                    error =
                        "Enter a valid 10-digit Indian mobile number " +
                        "starting with 6, 7, 8 or 9.";
                }
                break;
            }

            case "course": {
                const course =
                    document.getElementById("course").value;

                if (!course) {
                    error = "Please select your course.";
                }
                break;
            }

            case "year": {
                const year =
                    document.getElementById("year").value;

                if (!year) {
                    error = "Please select your year of study.";
                }
                break;
            }

            case "gender": {
                const selectedGender =
                    document.querySelector('input[name="gender"]:checked');

                if (!selectedGender) {
                    error = "Please select your gender.";
                }
                break;
            }

            case "password": {
                const password = passwordInput.value;

                const passwordIsValid =
                    password.length >= 8 &&
                    /[a-z]/.test(password) &&
                    /[A-Z]/.test(password) &&
                    /\d/.test(password) &&
                    /[^A-Za-z0-9]/.test(password);

                if (!password) {
                    error = "Password is required.";
                } else if (!passwordIsValid) {
                    error =
                        "Use at least 8 characters with uppercase, " +
                        "lowercase, a number and a special character.";
                }
                break;
            }

            case "confirm-password": {
                const confirmPassword = confirmPasswordInput.value;

                if (!confirmPassword) {
                    error = "Please confirm your password.";
                } else if (
                    confirmPassword !== passwordInput.value
                ) {
                    error = "Passwords do not match.";
                }
                break;
            }

            case "terms": {
                const termsAccepted =
                    document.getElementById("terms").checked;

                if (!termsAccepted) {
                    error =
                        "You must accept the terms and conditions.";
                }
                break;
            }
        }

        setRegistrationError(fieldName, error);

        return error === "";
    }

    /* Password strength updates while typing */
    passwordInput.addEventListener("input", function () {
        updatePasswordStrength();

        if (validationAttempted) {
            validateRegistrationField("password");
            validateRegistrationField("confirm-password");
        }
    });

    /* Real-time validation for other fields */
    [
        "fullname",
        "email",
        "mobile",
        "course",
        "year",
        "confirm-password",
        "terms"
    ].forEach(function (fieldName) {
        const field = document.getElementById(fieldName);

        if (field) {
            ["input", "change"].forEach(function (eventName) {
                field.addEventListener(eventName, function () {
                    if (validationAttempted) {
                        validateRegistrationField(fieldName);
                    }
                });
            });
        }
    });

    /* Gender validation on selection */
    document
        .querySelectorAll('input[name="gender"]')
        .forEach(function (radio) {
            radio.addEventListener("change", function () {
                if (validationAttempted) {
                    validateRegistrationField("gender");
                }
            });
        });

    /* Check all fields on submission */
    registrationForm.addEventListener("submit", function (event) {
        event.preventDefault();

        validationAttempted = true;
        updatePasswordStrength();

        const results = registrationFieldNames.map(
            validateRegistrationField
        );

        const allValid = results.every(Boolean);

        formMessage.hidden = false;

        if (!allValid) {
            formMessage.className = "form-message error";
            formMessage.textContent =
                "Please correct the highlighted fields and try again.";

            const firstInvalid =
                registrationForm.querySelector('[aria-invalid="true"]');

            if (firstInvalid) {
                firstInvalid.focus();
            }

            return;
        }

        formMessage.className = "form-message success";
        formMessage.textContent =
            "Success! All fields passed frontend validation. " +
            "This practical demo does not save your data to a server.";
    });

    /* Reset error messages when Clear Form is clicked */
    registrationForm.addEventListener("reset", function () {
        window.setTimeout(function () {
            validationAttempted = false;

            registrationFieldNames.forEach(function (fieldName) {
                setRegistrationError(fieldName, "");
            });

            formMessage.hidden = true;
            formMessage.textContent = "";
            formMessage.className = "form-message";

            updatePasswordStrength();
        }, 0);
    });

    /* Initialize the password meter */
    updatePasswordStrength();
}

