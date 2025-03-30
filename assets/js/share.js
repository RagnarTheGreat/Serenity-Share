/**
 * Share Management JavaScript
 * Handles file uploads, share creation, and management
 */

let currentUpload = null;

// Initialize file input event listener
document.getElementById("fileInput").addEventListener("change", () => {
    if (document.getElementById("fileInput").files.length > 0) {
        document.querySelector(".upload-options").classList.remove("hidden");
    }
});

/**
 * Uploads files and creates a new share
 */
function uploadFiles() {
    const files = document.getElementById("fileInput").files;
    
    if (files.length === 0) {
        Swal.fire("Error", "Please select files to upload", "error");
        return;
    }
    
    const formData = new FormData();
    formData.append("create_share", "1");
    formData.append("secret_key", secretKey);
    
    let totalSize = 0;
    for (let i = 0; i < files.length; i++) {
        formData.append("files[]", files[i]);
        formData.append("paths[]", "");
        totalSize += files[i].size;
    }
    
    const expiration = document.getElementById("expiration").value;
    const password = document.getElementById("sharePassword").value;
    
    if (expiration !== "-1") {
        formData.append("expiration", expiration);
    } else {
        formData.append("expiration", -1);
    }
    
    if (password) {
        formData.append("password", password);
    }
    
    // Get progress elements
    const progressBar = document.getElementById("progressBar");
    const progressPercent = document.getElementById("progressPercent");
    const currentFile = document.getElementById("currentFile");
    
    document.getElementById("uploadProgress").classList.remove("hidden");
    
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "share.php", true);
    xhr.timeout = 36e5; // 1 hour timeout
    
    // Progress event
    xhr.upload.addEventListener("progress", (event) => {
        if (event.lengthComputable) {
            const percent = Math.round(event.loaded / event.total * 100);
            progressBar.style.width = percent + "%";
            progressPercent.textContent = percent + "%";
            currentFile.textContent = `Uploading ${formatSize(event.loaded)} of ${formatSize(totalSize)}`;
            document.title = `(${percent}%) Uploading...`;
        }
    });
    
    // Upload complete
    xhr.onload = function() {
        document.title = "Share Management";
        
        try {
            const response = JSON.parse(xhr.responseText);
            
            if (!response.success) {
                throw new Error(response.error || "Upload failed");
            }
            
            Toastify({
                text: "Share created successfully",
                duration: 3000,
                gravity: "bottom",
                position: "right",
                style: {
                    background: "linear-gradient(to right, #00b09b, #96c93d)"
                }
            }).showToast();
            
            location.reload();
        } catch (error) {
            handleUploadError(error);
        }
    };
    
    // Error handling
    xhr.onerror = function() {
        document.title = "Share Management";
        handleUploadError(new Error("Network error occurred"));
    };
    
    currentUpload = xhr;
    xhr.send(formData);
}

/**
 * Handles upload errors and displays a toast notification
 * @param {Error} error - The error object
 */
function handleUploadError(error) {
    document.getElementById("uploadProgress").classList.add("hidden");
    
    Toastify({
        text: "Upload failed: " + error.message,
        duration: 3000,
        gravity: "bottom",
        position: "right",
        style: {
            background: "linear-gradient(to right, #ff5f6d, #ffc371)"
        }
    }).showToast();
}

/**
 * Cancels the current upload
 */
function cancelUpload() {
    if (currentUpload) {
        currentUpload.abort();
        currentUpload = null;
    }
    
    document.getElementById("uploadProgress").classList.add("hidden");
}

/**
 * Shows the delete confirmation dialog
 * @param {string} shareId - The ID of the share to delete
 */
function deleteShare(shareId) {
    document.getElementById("delete-dialog").style.display = "block";
    document.getElementById("delete-dialog-overlay").style.display = "block";
    document.getElementById("delete-dialog").dataset.shareId = shareId;
}

/**
 * Hides the delete confirmation dialog
 */
function hideDeleteDialog() {
    document.getElementById("delete-dialog").style.display = "none";
    document.getElementById("delete-dialog-overlay").style.display = "none";
}

/**
 * Proceeds with share deletion after confirmation
 */
function proceedWithDelete() {
    const shareId = document.getElementById("delete-dialog").dataset.shareId;
    const formData = new FormData();
    
    formData.append("delete_share", "1");
    formData.append("share_id", shareId);
    
    fetch("share.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            throw new Error(data.error || "Delete failed");
        }
        
        Toastify({
            text: "Share deleted successfully",
            duration: 3000,
            gravity: "bottom",
            position: "right",
            style: {
                background: "linear-gradient(to right, #00b09b, #96c93d)"
            }
        }).showToast();
        
        location.reload();
    })
    .catch(error => {
        Toastify({
            text: "Delete failed: " + error.message,
            duration: 3000,
            gravity: "bottom",
            position: "right",
            style: {
                background: "linear-gradient(to right, #ff5f6d, #ffc371)"
            }
        }).showToast();
    });
    
    hideDeleteDialog();
}

/**
 * Copies share URL to clipboard
 * @param {HTMLElement} element - The input element containing the URL
 */
function copyToClipboard(element) {
    element.select();
    document.execCommand("copy");
    
    Toastify({
        text: "Share URL copied to clipboard",
        duration: 3000,
        gravity: "bottom",
        position: "right",
        style: {
            background: "linear-gradient(to right, #00b09b, #96c93d)"
        }
    }).showToast();
}

/**
 * Initialize UI on page load
 */
document.addEventListener("DOMContentLoaded", function() {
    document.getElementById("uploadProgress").classList.add("hidden");
    document.getElementById("progressBar").style.width = "0%";
    document.getElementById("progressPercent").textContent = "0%";
    document.getElementById("currentFile").textContent = "Preparing upload...";
});
