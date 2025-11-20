// Gallery.js loaded - Version: 2024-11-20
console.log("Gallery.js loaded successfully");

function toggleAll(e) {
    const checkboxes = document.getElementsByClassName("file-checkbox");
    const isSelectAll = e.textContent.includes("Select All");
    for (let checkbox of checkboxes) {
        checkbox.checked = isSelectAll;
    }
    e.innerHTML = isSelectAll ? "✗ Deselect All" : "✓ Select All";
    updateDeleteButton();
}

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

function confirmDelete() {
    const selectedFiles = Array.from(document.getElementsByClassName("file-checkbox"))
        .filter(checkbox => checkbox.checked)
        .map(checkbox => checkbox.value);
    
    if (selectedFiles.length === 0) {
        showToast("No files selected", "error");
        hideDeleteDialog();
        return;
    }
    
    let deleteDialog = document.getElementById("delete-dialog");
    let deleteOverlay = document.getElementById("delete-dialog-overlay");
    
    if (!deleteDialog) {
        deleteDialog = document.querySelector("#delete-dialog");
    }
    if (!deleteOverlay) {
        deleteOverlay = document.querySelector("#delete-dialog-overlay");
    }
    
    if (!deleteDialog || !deleteOverlay) {
        console.error("Delete dialog elements not found! Document ready state:", document.readyState);
        console.log("Available elements with 'delete' in id:", Array.from(document.querySelectorAll('[id*="delete"]')).map(el => el.id));
        return;
    }
    
    deleteDialog.style.display = "block";
    deleteOverlay.style.display = "block";
}

function hideDeleteDialog() {
    document.getElementById("delete-dialog").style.display = "none";
    document.getElementById("delete-dialog-overlay").style.display = "none";
}

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
    deleteForm.onsubmit = null;
    
    const urlParams = new URLSearchParams(window.location.search);
    const currentPage = urlParams.get('page') || '1';
    const pageInput = document.createElement('input');
    pageInput.type = 'hidden';
    pageInput.name = 'return_page';
    pageInput.value = currentPage;
    deleteForm.appendChild(pageInput);
    deleteForm.submit();
    hideDeleteDialog();
}

function showToast(message, type = "info", duration = 3000) {
    const toastContainer = document.getElementById("toast-container");
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
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = "slideOut 0.3s ease-out forwards";
        setTimeout(() => {
            if (toastContainer.contains(toast)) {
                toastContainer.removeChild(toast);
            }
        }, 300);
    }, duration);
}

function sortGallery(sortBy) {
    const gallery = document.querySelector(".gallery-grid, .gallery-list");
    const items = Array.from(gallery.children);
    
    items.sort((a, b) => {
        const [sortType, direction] = sortBy.split("-");
        const multiplier = direction === "asc" ? 1 : -1;
        
        if (sortType === "date") {
            const dateA = a.querySelector(".filedate").textContent.replace("Date: ", "");
            const dateB = b.querySelector(".filedate").textContent.replace("Date: ", "");
            return multiplier * (new Date(dateA).getTime() - new Date(dateB).getTime());
        }
        
        if (sortType === "name") {
            const nameA = a.querySelector(".filename").textContent.toLowerCase();
            const nameB = b.querySelector(".filename").textContent.toLowerCase();
            return multiplier * nameA.localeCompare(nameB);
        }
        
        return 0;
    });
    
    items.forEach(item => gallery.appendChild(item));
}

function setView(view) {
    const gallery = document.querySelector(".gallery-grid, .gallery-list");
    const gridView = document.getElementById("grid-view");
    const listView = document.getElementById("list-view");
    
    if (view === "grid") {
        gallery.className = "gallery-grid";
    } else {
        gallery.className = "gallery-list";
    }
    
    gridView.classList.toggle("button-primary", view === "grid");
    listView.classList.toggle("button-primary", view === "list");
    localStorage.setItem("gallery-view", view);
}

function showUploadDialog() {
    let uploadDialog = document.getElementById("upload-dialog");
    if (!uploadDialog) {
        // Try querySelector as fallback
        uploadDialog = document.querySelector("#upload-dialog");
    }
    if (!uploadDialog) {
        console.error("Upload dialog element not found! Document ready state:", document.readyState);
        console.log("Available elements with 'upload' in id:", Array.from(document.querySelectorAll('[id*="upload"]')).map(el => el.id));
        return;
    }
    uploadDialog.style.display = "flex";
    initDragDrop();
}

function hideUploadDialog() {
    document.getElementById("upload-dialog").style.display = "none";
}

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
        .then(data => {
            let result;
            try {
                result = JSON.parse(data);
            } catch (e) {
                console.error("JSON parse error:", e);
                throw new Error("Invalid JSON response from server");
            }
            
            if (result.success) {
                showToast("Files uploaded successfully", "success");
                hideUploadDialog();
                location.reload();
            } else {
                showToast(result.error || "Upload failed", "error");
            }
        })
        .catch(error => {
            showToast("Upload failed: " + error.message, "error");
        });
    }
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });
    
    dropZone.addEventListener('click', () => {
        const input = document.createElement('input');
        input.type = 'file';
        input.multiple = true;
        input.accept = '.jpg,.jpeg,.png,.gif,.mp4,.webm';
        input.onchange = (e) => handleFiles(e.target.files);
        input.click();
    });
    
    dropZone.addEventListener('drop', (e) => {
        handleFiles(e.dataTransfer.files);
    });
    
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, unhighlight, false);
    });
}

function handlePaginationClick(e) {
    e.preventDefault();
    const url = e.target.href;
    if (url) {
        showToast("Loading page...", "info", 1000);
        window.location.href = url;
    }
}

function initPagination() {
    const paginationContainers = document.querySelectorAll('.pagination-bottom');
    paginationContainers.forEach(container => {
        container.addEventListener('click', (e) => {
            if (e.target.classList.contains('pagination-link') && 
                !e.target.classList.contains('pagination-current')) {
                handlePaginationClick(e);
            }
        });
    });
}

// QR Code functions
function showQRCode(url, title) {
    let modal = document.getElementById("qr-modal");
    let overlay = document.getElementById("qr-modal-overlay");
    let img = document.getElementById("qr-code-image");
    let titleEl = document.getElementById("qr-modal-title");
    let urlInput = document.getElementById("qr-url-display");
    let downloadLink = document.getElementById("qr-download-link");
    
    if (!modal) modal = document.querySelector("#qr-modal");
    if (!overlay) overlay = document.querySelector("#qr-modal-overlay");
    if (!img) img = document.querySelector("#qr-code-image");
    if (!titleEl) titleEl = document.querySelector("#qr-modal-title");
    if (!urlInput) urlInput = document.querySelector("#qr-url-display");
    if (!downloadLink) downloadLink = document.querySelector("#qr-download-link");
    
    if (!modal || !overlay || !img || !titleEl || !urlInput || !downloadLink) {
        console.error("QR modal elements not found! Document ready state:", document.readyState);
        console.log("Modal:", modal, "Overlay:", overlay, "Img:", img, "TitleEl:", titleEl, "UrlInput:", urlInput, "DownloadLink:", downloadLink);
        console.log("Available elements with 'qr' in id:", Array.from(document.querySelectorAll('[id*="qr"]')).map(el => el.id));
        return;
    }
    
    const qrUrl = "qr_code.php?url=" + encodeURIComponent(url) + "&size=400";
    
    titleEl.textContent = title || "QR Code";
    urlInput.value = url;
    downloadLink.href = qrUrl;
    
    // Show modal immediately
    modal.style.display = "block";
    overlay.style.display = "block";
    
    // Load the QR code image using fetch to handle binary data properly
    fetch(qrUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('QR code generation failed: ' + response.status);
            }
            // Get the blob data (binary PNG)
            return response.blob();
        })
        .then(blob => {
            // Create a blob URL and set it as the image source
            const blobUrl = URL.createObjectURL(blob);
            img.src = blobUrl;
            
            // Store blob URL for cleanup
            img.dataset.blobUrl = blobUrl;
            
            img.onload = function() {
                console.log("QR code image loaded successfully");
            };
            img.onerror = function() {
                console.error("Failed to load QR code image from blob URL");
                if (blobUrl) {
                    URL.revokeObjectURL(blobUrl);
                }
            };
        })
        .catch(error => {
            console.error("Failed to fetch QR code:", error);
            // Try fallback - direct URL (might work)
            img.src = qrUrl;
        });
}

function hideQRCode() {
    const img = document.getElementById("qr-code-image");
    // Clean up blob URL if it exists
    if (img && img.dataset.blobUrl) {
        URL.revokeObjectURL(img.dataset.blobUrl);
        delete img.dataset.blobUrl;
    }
    document.getElementById("qr-modal").style.display = "none";
    document.getElementById("qr-modal-overlay").style.display = "none";
}

function copyQRUrl() {
    const urlInput = document.getElementById("qr-url-display");
    urlInput.select();
    urlInput.setSelectionRange(0, 99999);
    
    try {
        // Try modern clipboard API first
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(urlInput.value).then(() => {
                showToast("URL copied to clipboard!", "success");
            }).catch(() => {
                // Fallback to execCommand
                document.execCommand("copy");
                showToast("URL copied to clipboard!", "success");
            });
        } else {
            // Fallback to execCommand
            document.execCommand("copy");
            showToast("URL copied to clipboard!", "success");
        }
    } catch (err) {
        showToast("Failed to copy URL", "error");
    }
}

// Explicitly attach all functions to window object to ensure global access
window.toggleAll = toggleAll;
window.updateDeleteButton = updateDeleteButton;
window.confirmDelete = confirmDelete;
window.hideDeleteDialog = hideDeleteDialog;
window.proceedWithDelete = proceedWithDelete;
window.showToast = showToast;
window.sortGallery = sortGallery;
window.setView = setView;
window.showUploadDialog = showUploadDialog;
window.hideUploadDialog = hideUploadDialog;
window.initDragDrop = initDragDrop;
window.handlePaginationClick = handlePaginationClick;
window.initPagination = initPagination;
window.showQRCode = showQRCode;
window.hideQRCode = hideQRCode;
window.copyQRUrl = copyQRUrl;

// Initialize on page load - wait for everything to be fully loaded
(function() {
    function init() {
        // Check if we're already initialized
        if (window.galleryInitialized) {
            return;
        }
        window.galleryInitialized = true;
        
        // Wait a bit to ensure all elements are in DOM
        setTimeout(function() {
            // Debug: Check if elements exist
            const uploadDialog = document.getElementById("upload-dialog");
            const deleteDialog = document.getElementById("delete-dialog");
            const deleteOverlay = document.getElementById("delete-dialog-overlay");
            const qrModal = document.getElementById("qr-modal");
            const qrOverlay = document.getElementById("qr-modal-overlay");
            const toastContainer = document.getElementById("toast-container");
            
            console.log("Gallery initialization - Elements check:", {
                uploadDialog: !!uploadDialog,
                deleteDialog: !!deleteDialog,
                deleteOverlay: !!deleteOverlay,
                qrModal: !!qrModal,
                qrOverlay: !!qrOverlay,
                toastContainer: !!toastContainer,
                documentReady: document.readyState,
                body: !!document.body
            });
            
            if (!uploadDialog || !deleteDialog || !qrModal) {
                console.error("Critical elements not found! Checking DOM...");
                console.log("All divs with ids:", Array.from(document.querySelectorAll('[id]')).map(el => el.id));
            }
            
            try {
                const deleteForm = document.getElementById("delete-form");
                if (deleteForm) {
                    deleteForm.onsubmit = function(e) {
                        e.preventDefault();
                        confirmDelete();
                    };
                }
                
                const view = localStorage.getItem("gallery-view") || "grid";
                if (typeof setView === 'function') {
                    setView(view);
                }
                
                if (typeof initPagination === 'function') {
                    initPagination();
                }
            } catch (error) {
                console.error('Error initializing gallery:', error);
            }
        }, 100);
    }
    
    // Wait for window load event to ensure everything is ready
    if (document.readyState === 'loading') {
        window.addEventListener('load', init);
    } else if (document.readyState === 'interactive') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(init, 100);
        });
    } else {
        // Already loaded, but wait a bit anyway
        setTimeout(init, 100);
    }
})();
