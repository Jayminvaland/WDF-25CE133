
/* ==========================================
   PRACTICAL 6 - FETCH API, SEARCH, FILTER,
   SORTING AND PAGINATION
========================================== */

const dataTypeSelect = document.getElementById("data-type");
const searchInput = document.getElementById("search-input");
const filterSelect = document.getElementById("filter-select");
const sortSelect = document.getElementById("sort-select");
const pageSizeSelect = document.getElementById("page-size");

const statusMessage = document.getElementById("status-message");
const errorMessage = document.getElementById("error-message");
const recordCount = document.getElementById("record-count");
const dataContainer = document.getElementById("data-container");

const prevPageButton = document.getElementById("prev-page");
const nextPageButton = document.getElementById("next-page");
const pageInfo = document.getElementById("page-info");

/* JSON files and their display names */
const dataSources = {
    events: {
        file: "events.json",
        label: "events"
    },
    students: {
        file: "students.json",
        label: "students"
    },
    faqs: {
        file: "faqs.json",
        label: "FAQs"
    }
};

let allRecords = [];
let currentPage = 1;

/* Return the main display value for a record */
function getPrimaryValue(record) {
    const dataType = dataTypeSelect.value;

    if (dataType === "events") {
        return record.title || "";
    }

    if (dataType === "students") {
        return record.name || "";
    }

    return record.question || "";
}

/* Return the category/course used for filtering */
function getFilterValue(record) {
    if (dataTypeSelect.value === "students") {
        return record.course || "";
    }

    return record.category || "";
}

/* Return the date/year used for secondary sorting */
function getSecondaryValue(record) {
    if (dataTypeSelect.value === "events") {
        return record.date || "";
    }

    if (dataTypeSelect.value === "students") {
        return record.year ?? "";
    }

    return getPrimaryValue(record);
}

/* Safely create a label/value line */
function addDetail(card, label, value) {
    if (value === undefined || value === null || value === "") {
        return;
    }

    const paragraph = document.createElement("p");
    const strong = document.createElement("strong");

    strong.textContent = label + ": ";

    paragraph.appendChild(strong);
    paragraph.appendChild(document.createTextNode(String(value)));
    card.appendChild(paragraph);
}

/* Create a card for each record */
function createRecordCard(record) {
    const card = document.createElement("article");
    card.className = "card data-card";

    const heading = document.createElement("h3");
    heading.textContent = getPrimaryValue(record) || "Untitled Record";
    card.appendChild(heading);

    if (dataTypeSelect.value === "events") {
        addDetail(card, "Category", record.category);
        addDetail(card, "Date", record.date);
        addDetail(card, "Venue", record.venue);
        addDetail(card, "Description", record.description);
    } else if (dataTypeSelect.value === "students") {
        addDetail(card, "Enrollment", record.enrollment);
        addDetail(card, "Email", record.email);
        addDetail(card, "Course", record.course);
        addDetail(card, "Year", record.year);
    } else {
        addDetail(card, "Category", record.category);
        addDetail(card, "Answer", record.answer);
    }

    return card;
}

/* Build category/course filter options */
function updateFilterOptions() {
    const values = allRecords
        .map(getFilterValue)
        .filter(function (value) {
            return value !== "";
        });

    const uniqueValues = [...new Set(values)];

    uniqueValues.sort(function (a, b) {
        return String(a).localeCompare(String(b));
    });

    filterSelect.replaceChildren();

    const allOption = document.createElement("option");
    allOption.value = "all";
    allOption.textContent =
        dataTypeSelect.value === "students"
            ? "All Courses"
            : "All Categories";

    filterSelect.appendChild(allOption);

    uniqueValues.forEach(function (value) {
        const option = document.createElement("option");
        option.value = value;
        option.textContent = value;
        filterSelect.appendChild(option);
    });
}

/* Search and filter the records */
function getFilteredRecords() {
    const keyword = searchInput.value.trim().toLowerCase();
    const selectedFilter = filterSelect.value;

    let records = allRecords.filter(function (record) {
        const matchesSearch = Object.values(record).some(function (value) {
            return String(value)
                .toLowerCase()
                .includes(keyword);
        });

        const matchesFilter =
            selectedFilter === "all" ||
            getFilterValue(record) === selectedFilter;

        return matchesSearch && matchesFilter;
    });

    /* Sorting */
    const sortOrder = sortSelect.value;

    if (sortOrder === "primary-asc") {
        records.sort(function (a, b) {
            return getPrimaryValue(a).localeCompare(getPrimaryValue(b));
        });
    } else if (sortOrder === "primary-desc") {
        records.sort(function (a, b) {
            return getPrimaryValue(b).localeCompare(getPrimaryValue(a));
        });
    } else if (
        sortOrder === "secondary-asc" ||
        sortOrder === "secondary-desc"
    ) {
        records.sort(function (a, b) {
            const valueA = getSecondaryValue(a);
            const valueB = getSecondaryValue(b);

            let comparison;

            if (
                typeof valueA === "number" ||
                typeof valueB === "number"
            ) {
                comparison = Number(valueA) - Number(valueB);
            } else {
                comparison = String(valueA).localeCompare(String(valueB));
            }

            return sortOrder === "secondary-asc"
                ? comparison
                : -comparison;
        });
    }

    return records;
}

/* Render the current page */
function renderRecords() {
    const records = getFilteredRecords();
    const pageSize = Number(pageSizeSelect.value);
    const totalPages = Math.ceil(records.length / pageSize);

    if (totalPages > 0 && currentPage > totalPages) {
        currentPage = totalPages;
    }

    if (records.length === 0) {
        currentPage = 1;
    }

    const startIndex = (currentPage - 1) * pageSize;
    const pageRecords = records.slice(
        startIndex,
        startIndex + pageSize
    );

    dataContainer.replaceChildren();

    if (records.length === 0) {
        const emptyMessage = document.createElement("p");
        emptyMessage.textContent =
            "No records found. Try changing your search or filter.";
        dataContainer.appendChild(emptyMessage);

        recordCount.textContent = "0 records found.";
        pageInfo.textContent = "Page 0 of 0";
    } else {
        pageRecords.forEach(function (record) {
            dataContainer.appendChild(createRecordCard(record));
        });

        recordCount.textContent =
            `Showing ${startIndex + 1}-${startIndex + pageRecords.length}` +
            ` of ${records.length} records.`;

        pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
    }

    prevPageButton.disabled =
        records.length === 0 || currentPage <= 1;

    nextPageButton.disabled =
        records.length === 0 || currentPage >= totalPages;
}

/* Fetch data from the selected JSON file */
async function loadSelectedDataset() {
    const selectedSource = dataSources[dataTypeSelect.value];

    currentPage = 1;
    allRecords = [];

    searchInput.value = "";
    sortSelect.value = "default";

    statusMessage.textContent =
        `Loading ${selectedSource.label}...`;

    errorMessage.hidden = true;
    errorMessage.textContent = "";

    recordCount.textContent = "Loading records...";
    dataContainer.replaceChildren();

    filterSelect.replaceChildren();

    const allOption = document.createElement("option");
    allOption.value = "all";
    allOption.textContent =
        dataTypeSelect.value === "students"
            ? "All Courses"
            : "All Categories";

    filterSelect.appendChild(allOption);

    try {
        const response = await fetch(selectedSource.file);

        if (!response.ok) {
            throw new Error(
                `HTTP error: ${response.status} ${response.statusText}`
            );
        }

        const data = await response.json();

        if (!Array.isArray(data)) {
            throw new Error("The JSON file must contain an array of records.");
        }

        allRecords = data;

        updateFilterOptions();
        renderRecords();

        statusMessage.textContent =
            `Successfully loaded ${allRecords.length} ${selectedSource.label}.`;
    } catch (error) {
        allRecords = [];
        currentPage = 1;

        renderRecords();

        statusMessage.textContent = "Unable to load data.";

        errorMessage.textContent =
            `Could not load ${selectedSource.file}. ` +
            "Check that the JSON file exists, contains valid JSON, " +
            "and that you opened this page using Live Server. " +
            `Details: ${error.message}`;

        errorMessage.hidden = false;

        console.error("Fetch API error:", error);
    }
}

/* Search updates results as the user types */
searchInput.addEventListener("input", function () {
    currentPage = 1;
    renderRecords();
});

/* Filter records by category/course */
filterSelect.addEventListener("change", function () {
    currentPage = 1;
    renderRecords();
});

/* Sort records */
sortSelect.addEventListener("change", function () {
    currentPage = 1;
    renderRecords();
});

/* Change number of records per page */
pageSizeSelect.addEventListener("change", function () {
    currentPage = 1;
    renderRecords();
});

/* Previous page */
prevPageButton.addEventListener("click", function () {
    if (currentPage > 1) {
        currentPage--;
        renderRecords();
    }
});

/* Next page */
nextPageButton.addEventListener("click", function () {
    const records = getFilteredRecords();
    const pageSize = Number(pageSizeSelect.value);
    const totalPages = Math.ceil(records.length / pageSize);

    if (currentPage < totalPages) {
        currentPage++;
        renderRecords();
    }
});

/* Load the initially selected dataset */
dataTypeSelect.addEventListener("change", loadSelectedDataset);

loadSelectedDataset();
