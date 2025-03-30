/**
 * Toggles selection of all files in the gallery
 * @param {HTMLElement} button - The select all button
 */
function toggleAll(button) {
    const checkboxes = document.getElementsByClassName("file-checkbox");
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
    const checkboxes = document.getElementsByClassName("file-checkbox");
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
 * Shows the delete confirmation dialog
 */
function confirmDelete() {
    const selectedFiles = Array.from(document.getElementsByClassName("file-checkbox"))
        .filter(checkbox => checkbox.checked)
        .map(checkbox => checkbox.value);
    
    if (selectedFiles.length === 0) {
        showToast("No files selected", "error");
        hideDeleteDialog();
        return;
    }
    
    document.getElementById("delete-dialog").style.display = "block";
    document.getElementById("delete-dialog-overlay").style.display = "block";
}

/**
 * Hides the delete confirmation dialog
 */
function hideDeleteDialog() {
    document.getElementById("delete-dialog").style.display = "none";
    document.getElementById("delete-dialog-overlay").style.display = "none";
}

/**
 * Proceeds with deletion after confirmation
 */
function proceedWithDelete() {
    const selectedFiles = Array.from(document.getElementsByClassName("file-checkbox"))
        .filter(checkbox => checkbox.checked)
        .map(checkbox => checkbox.value);
    
    if (selectedFiles.length === 0) {
        showToast("No files selected", "error");
        hideDeleteDialog();
        return;
    }
    
    document.getElementById("selected-files").value = JSON.stringify(selectedFiles);
    
    const deleteForm = document.getElementById("delete-form");
    deleteForm.onsubmit = null; // Prevent the confirm dialog from showing again
    deleteForm.submit();
    
    hideDeleteDialog();
}

/**
 * Shows a toast notification
 * @param {string} message - The message to display
 * @param {string} type - The type of toast (info, success, error, warning)
 * @param {number} duration - How long to display the toast in ms
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
            icon = "⚠";
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
 * Sorts the gallery by the specified criteria
 * @param {string} criteria - The sort criteria and direction, e.g., "date-desc"
 */
function sortGallery(criteria) {
    const gallery = document.querySelector(".gallery-grid, .gallery-list");
    const items = Array.from(gallery.children);
    
    items.sort((itemA, itemB) => {
        const [field, direction] = criteria.split("-");
        const multiplier = direction === "asc" ? 1 : -1;
        
        if (field === "date") {
            const dateA = itemA.querySelector(".filedate").textContent.replace("Date: ", "");
            const dateB = itemB.querySelector(".filedate").textContent.replace("Date: ", "");
            return multiplier * (new Date(dateA).getTime() - new Date(dateB).getTime());
        }
        
        if (field === "name") {
            const nameA = itemA.querySelector(".filename").textContent.toLowerCase();
            const nameB = itemB.querySelector(".filename").textContent.toLowerCase();
            return multiplier * nameA.localeCompare(nameB);
        }
        
        return 0;
    });
    
    items.forEach(item => gallery.appendChild(item));
}

/**
 * Sets the gallery view (grid or list)
 * @param {string} view - The view to set ("grid" or "list")
 */
function setView(view) {
    const gallery = document.querySelector(".gallery-grid, .gallery-list");
    const gridButton = document.getElementById("grid-view");
    const listButton = document.getElementById("list-view");
    
    gallery.className = view === "grid" ? "gallery-grid" : "gallery-list";
    
    gridButton.classList.toggle("button-primary", view === "grid");
    listButton.classList.toggle("button-primary", view === "list");
    
    localStorage.setItem("gallery-view", view);
}

/**
 * Shows the upload dialog
 */
function showUploadDialog() {
    document.getElementById("upload-dialog").style.display = "flex";
    initDragDrop();
}

/**
 * Hides the upload dialog
 */
function hideUploadDialog() {
    document.getElementById("upload-dialog").style.display = "none";
}

/**
 * Initializes drag and drop functionality
 */
function initDragDrop() {
    const dropZone = document.getElementById("drop-zone");
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    function highlight(e) {
        dropZone.classList.add("upload-zone-active");
    }
    
    function unhighlight(e) {
        dropZone.classList.remove("upload-zone-active");
    }
    
    function handleFiles(files) {
        const formData = new FormData();
        
        Array.from(files).forEach(file => {
            formData.append("files[]", file);
        });
        
        const secretKey = document.querySelector('meta[name="secret-key"]').getAttribute("content");
        formData.append("secret_key", secretKey);
        
        fetch("upload.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.text())
        .then(text => {
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error("JSON parse error:", e);
                throw new Error("Invalid JSON response from server");
            }
            
            if (data.success) {
                showToast("Files uploaded successfully", "success");
                hideUploadDialog();
                location.reload();
            } else {
                showToast(data.error || "Upload failed", "error");
            }
        })
        .catch(error => {
            showToast("Upload failed: " + error.message, "error");
        });
    }
    
    // Prevent default drag behaviors
    ["dragenter", "dragover", "dragleave", "drop"].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });
    
    // Handle click to select files
    dropZone.addEventListener("click", () => {
        const fileInput = document.createElement("input");
        fileInput.type = "file";
        fileInput.multiple = true;
        fileInput.accept = ".jpg,.jpeg,.png,.gif,.mp4,.webm";
        fileInput.onchange = e => handleFiles(e.target.files);
        fileInput.click();
    });
    
    // Handle dropped files
    dropZone.addEventListener("drop", e => {
        handleFiles(e.dataTransfer.files);
    });
    
    // Highlight drop area when item is dragged over it
    ["dragenter", "dragover"].forEach(eventName => {
        dropZone.addEventListener(eventName, highlight, false);
    });
    
    // Unhighlight when item is dragged away or dropped
    ["dragleave", "drop"].forEach(eventName => {
        dropZone.addEventListener(eventName, unhighlight, false);
    });
}

/**
 * Initialize the gallery when the page loads
 */
document.addEventListener("DOMContentLoaded", function() {
    const deleteForm = document.getElementById("delete-form");
    if (deleteForm) {
        deleteForm.onsubmit = function(e) {
            e.preventDefault();
            confirmDelete();
        };
    }
    
    const savedView = localStorage.getItem("gallery-view") || "grid";
    setView(savedView);
});
