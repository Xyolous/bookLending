const hamburger = document.querySelector(".hamburger-icon");
const sidebar = document.querySelector(".sidebar");

function openSidebar() {
    sidebar.classList.add("open");
}

function closeSidebar() {
    sidebar.classList.remove("open");
}

hamburger.addEventListener("click", (event) => {
    event.stopPropagation(); // Prevent click from closing sidebar immediately
    if (sidebar.classList.contains("open")) {
        closeSidebar();
    } else {
        openSidebar();
    }
});

document.addEventListener("click", (event) => {
    // Close sidebar if clicked outside sidebar and hamburger
    if (
        sidebar.classList.contains("open") &&
        !sidebar.contains(event.target) &&
        !hamburger.contains(event.target)
    ) {
        closeSidebar();
    }
});

window.addEventListener("resize", () => {
    // Close sidebar on resize if it's open and width goes beyond mobile threshold
    if (window.innerWidth > 768 && sidebar.classList.contains("open")) {
        closeSidebar();
    }
});
