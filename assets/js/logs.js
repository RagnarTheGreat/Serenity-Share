/**
 * Shows a toast notification
 * @param {string} message - The message to display
 * @param {string} type - The type of notification (info, success, error, warning)
 * @param {number} duration - Duration in milliseconds
 */
function showToast(message, type = "info", duration = 3000) {
    const container = document.getElementById("toast-container");
    const toast = document.createElement("div");
    
    toast.className = `toast ${type}`;
    toast.style.animation = "slideIn 0.3s ease-out";
    
    let icon = "🔔";
    switch (type) {
        case "success":
            icon = "✅";
            break;
        case "error":
            icon = "❌";
            break;
        case "warning":
            icon = "⚠️";
            break;
    }
    
    toast.innerHTML = `<span class="toast-icon">${icon}</span><span class="toast-message">${message}</span>`;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = "slideOut 0.3s ease-out forwards";
        setTimeout(() => {
            container.removeChild(toast);
        }, 300);
    }, duration);
}

/**
 * Toggles selection of all log entries
 * @param {HTMLElement} button - The button that was clicked
 */
function toggleAll(button) {
    const checkboxes = document.getElementsByClassName("log-checkbox");
    const selectAll = button.textContent.includes("Select All");
    
    for (let checkbox of checkboxes) {
        checkbox.checked = selectAll;
    }
    
    button.innerHTML = selectAll ? "✗ Deselect All" : "✓ Select All";
    updateDeleteButton();
}

/**
 * Updates the delete button state based on selection
 */
function updateDeleteButton() {
    const checkboxes = document.getElementsByClassName("log-checkbox");
    const deleteButton = document.getElementById("delete-button");
    const selectAllButton = document.getElementById("select-all-button");
    
    let checkedCount = 0;
    const totalCount = checkboxes.length;
    
    for (let checkbox of checkboxes) {
        if (checkbox.checked) checkedCount++;
    }
    
    deleteButton.disabled = checkedCount === 0;
    
    if (checkedCount === 0) {
        selectAllButton.innerHTML = "✓ Select All";
    } else if (checkedCount === totalCount) {
        selectAllButton.innerHTML = "✗ Deselect All";
    }
}

/**
 * Confirms deletion of selected logs
 * @returns {boolean} - Always returns false to prevent form submission
 */
function confirmDelete() {
    const checkboxes = document.getElementsByClassName("log-checkbox");
    const selectedTimestamps = [];
    let count = 0;
    
    for (let checkbox of checkboxes) {
        if (checkbox.checked) {
            count++;
            selectedTimestamps.push(checkbox.value);
        }
    }
    
    if (count === 0) {
        showToast("No logs selected", "error");
        return false;
    }
    
    document.getElementById("selected-timestamps").value = JSON.stringify(selectedTimestamps);
    
    confirmAction(`Delete ${count} selected log entries?`, () => {
        showToast("Deleting logs...", "info");
        document.getElementById("delete-form").submit();
    });
    
    return false;
}

/**
 * Shows a confirmation dialog
 * @param {string} message - The confirmation message to display
 * @param {Function} callback - Function to call if confirmed
 */
function confirmAction(message, callback) {
    const overlay = document.createElement("div");
    overlay.className = "overlay";
    document.body.appendChild(overlay);
    
    const dialog = document.createElement("div");
    
    function closeDialog() {
        document.body.removeChild(dialog);
        document.body.removeChild(overlay);
    }
    
    dialog.className = "dialog";
    dialog.innerHTML = `
        <div class="dialog-header">
            <h2>Confirm Action</h2>
        </div>
        <div class="dialog-body">
            <p>${message}</p>
        </div>
        <div class="dialog-footer">
            <button type="button" class="button button-danger" id="cancel-button">Cancel</button>
            <button type="button" class="button button-primary" id="confirm-button">Confirm</button>
        </div>
    `;
    
    document.body.appendChild(dialog);
    dialog.addEventListener("click", e => e.stopPropagation());
    
    const cancelButton = dialog.querySelector("#cancel-button");
    const confirmButton = dialog.querySelector("#confirm-button");
    
    confirmButton.addEventListener("click", e => {
        e.stopPropagation();
        closeDialog();
        callback();
    });
    
    cancelButton.addEventListener("click", e => {
        e.stopPropagation();
        closeDialog();
    });
    
    overlay.addEventListener("click", e => {
        e.stopPropagation();
        closeDialog();
    });
}

// Constants for auto-refresh
const REFRESH_INTERVAL = 10000; // 10 seconds
let autoRefreshInterval = null;

/**
 * Refreshes the logs content via AJAX
 */
async function refreshLogs() {
    console.log("Refresh triggered");
    
    const refreshButton = document.getElementById("refresh-button");
    if (refreshButton) {
        refreshButton.classList.add("refresh-spin");
    }
    
    try {
        const response = await fetch(window.location.href + "?_=" + Date.now());
        const html = await response.text();
        
        const tempContainer = document.createElement("div");
        tempContainer.innerHTML = html;
        
        const newLogsContainer = tempContainer.querySelector(".logs-container");
        
        if (newLogsContainer) {
            document.querySelector(".logs-container").innerHTML = newLogsContainer.innerHTML;
            updateDeleteButton();
            console.log("Logs refreshed successfully");
        }
    } catch (error) {
        console.error("Error refreshing logs:", error);
        showToast("Failed to refresh logs", "error");
    } finally {
        if (refreshButton) {
            setTimeout(() => {
                refreshButton.classList.remove("refresh-spin");
            }, 1000);
        }
    }
}

/**
 * Toggles automatic refresh of logs
 */
function toggleAutoRefresh() {
    const checkbox = document.getElementById("auto-refresh");
    console.log("Auto-refresh toggled:", checkbox.checked);
    
    if (checkbox.checked) {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }
        
        autoRefreshInterval = setInterval(refreshLogs, REFRESH_INTERVAL);
        localStorage.setItem("autoRefreshEnabled", "true");
        console.log("Auto-refresh enabled");
    } else {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
            autoRefreshInterval = null;
        }
        
        localStorage.setItem("autoRefreshEnabled", "false");
        console.log("Auto-refresh disabled");
    }
}

/**
 * Exports logs to the specified format
 * @param {string} format - The export format ("csv" or "json")
 */
function exportLogs(format) {
    const logs = Array.from(document.querySelectorAll(".log-entry")).map(entry => ({
        timestamp: entry.querySelector(".log-timestamp").textContent,
        details: entry.querySelector(".log-details").textContent
    }));
    
    let content, filename, mimeType;
    
    if (format === "csv") {
        content = "Timestamp,Details\n" + logs.map(log => `"${log.timestamp}","${log.details}"`).join("\n");
        filename = "logs.csv";
        mimeType = "text/csv";
    } else {
        content = JSON.stringify(logs, null, 2);
        filename = "logs.json";
        mimeType = "application/json";
    }
    
    const blob = new Blob([content], { type: mimeType });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    
    link.href = url;
    link.download = filename;
    link.click();
    
    URL.revokeObjectURL(url);
}

/**
 * Initialize the logs page
 */
document.addEventListener("DOMContentLoaded", () => {
    console.log("DOMContentLoaded event fired");
    
    // Set up refresh button
    const refreshButton = document.getElementById("refresh-button");
    if (refreshButton) {
        refreshButton.onclick = refreshLogs;
    }
    
    // Set up auto-refresh checkbox
    const autoRefreshCheckbox = document.getElementById("auto-refresh");
    if (autoRefreshCheckbox) {
        console.log("Found auto-refresh checkbox");
        
        const autoRefreshEnabled = localStorage.getItem("autoRefreshEnabled");
        if (autoRefreshEnabled === "true") {
            autoRefreshCheckbox.checked = true;
            toggleAutoRefresh();
        }
        
        autoRefreshCheckbox.addEventListener("change", toggleAutoRefresh);
    }
    
    // Set up select all button
    const selectAllButton = document.getElementById("select-all-button");
    if (selectAllButton) {
        selectAllButton.onclick = () => toggleAll(selectAllButton);
    }
    
    // Set up delete form
    const deleteForm = document.getElementById("delete-form");
    if (deleteForm) {
        console.log("Delete form found, adding submit handler");
        deleteForm.onsubmit = function(e) {
            console.log("Form submit triggered");
            e.preventDefault();
            confirmDelete();
        };
    } else {
        console.log("Delete form not found");
    }
});

/**
 * Clean up when leaving the page
 */
window.addEventListener("beforeunload", () => {
    clearInterval(autoRefreshInterval);
});
