/**
 * Share Management JavaScript
 * Handles file uploads, share creation, and management
 */

let currentUpload = null;

// Initialize file input event listener
document.addEventListener("DOMContentLoaded", function() {
    document.getElementById("uploadProgress").classList.add("hidden");
    document.getElementById("progressBar").style.width = "0%";
    document.getElementById("progressPercent").textContent = "0%";
    document.getElementById("currentFile").textContent = "Preparing upload...";
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
            
            // Reload the page to show the updated share list instead of redirecting
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

// Handle file selection
function handleFileSelect(input) {
    const selectedFiles = document.getElementById('selected-files');
    selectedFiles.innerHTML = '';
    
    Array.from(input.files).forEach(file => {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        fileItem.innerHTML = `
            <span class="file-icon">📄</span>
            <span class="file-name">${file.name}</span>
            <span class="file-size">${formatFileSize(file.size)}</span>
        `;
        selectedFiles.appendChild(fileItem);
    });
}

// Handle folder selection
function handleFolderSelect(input) {
    const selectedFiles = document.getElementById('selected-files');
    selectedFiles.innerHTML = '';
    
    // Create a tree structure for the folder
    const folderTree = document.createElement('div');
    folderTree.className = 'folder-tree';
    
    // Process all files in the folder
    Array.from(input.files).forEach(file => {
        const path = file.webkitRelativePath;
        const parts = path.split('/');
        
        // Create folder structure
        let currentElement = folderTree;
        for (let i = 0; i < parts.length - 1; i++) {
            const folderName = parts[i];
            let folderElement = currentElement.querySelector(`[data-folder="${folderName}"]`);
            
            if (!folderElement) {
                folderElement = document.createElement('div');
                folderElement.className = 'folder-item';
                folderElement.setAttribute('data-folder', folderName);
                folderElement.innerHTML = `
                    <div class="folder-header">
                        <span class="folder-icon">📁</span>
                        <span class="folder-name">${folderName}</span>
                        <span class="folder-toggle">▼</span>
                    </div>
                    <div class="folder-content"></div>
                `;
                currentElement.appendChild(folderElement);
            }
            currentElement = folderElement.querySelector('.folder-content');
        }
        
        // Add file to the last folder
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        fileItem.innerHTML = `
            <span class="file-icon">📄</span>
            <span class="file-name">${parts[parts.length - 1]}</span>
            <span class="file-size">${formatFileSize(file.size)}</span>
        `;
        currentElement.appendChild(fileItem);
    });
    
    selectedFiles.appendChild(folderTree);
    
    // Add click handlers for folder toggles
    folderTree.querySelectorAll('.folder-toggle').forEach(toggle => {
        toggle.addEventListener('click', function() {
            const content = this.parentElement.nextElementSibling;
            content.style.display = content.style.display === 'none' ? 'block' : 'none';
            this.textContent = content.style.display === 'none' ? '▶' : '▼';
        });
    });
}

// Format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Handle form submission
document.getElementById('shareForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData();
    const submitButton = this.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    submitButton.textContent = 'Creating Share...';
    
    // Add form fields
    formData.append('create_share', '1');
    
    const expiration = document.getElementById('expiration').value;
    if (expiration) {
        formData.append('expiration', expiration);
    }
    
    const password = document.getElementById('password').value;
    if (password) {
        formData.append('password', password);
    }
    
    // Handle file uploads with relative paths for folders
    const fileInput = document.getElementById('file-input');
    const folderInput = document.getElementById('folder-input');
    
    // Choose which input has files
    const input = fileInput.files.length > 0 ? fileInput : folderInput;
    
    // Add files with relative paths for folder structure
    if (input && input.files.length > 0) {
        for (let i = 0; i < input.files.length; i++) {
            const file = input.files[i];
            formData.append('files[]', file);
            
            // If this is a folder upload (has webkitRelativePath)
            if (file.webkitRelativePath) {
                formData.append('relative_path[]', file.webkitRelativePath);
            } else {
                formData.append('relative_path[]', '');
            }
        }
    }
    
    // Show progress bar
    const progressBar = document.getElementById("progressBar");
    const progressPercent = document.getElementById("progressPercent");
    const currentFile = document.getElementById("currentFile");
    const uploadProgress = document.getElementById("uploadProgress");
    
    uploadProgress.classList.remove("hidden");
    progressBar.style.width = "0%";
    progressPercent.textContent = "0%";
    currentFile.textContent = "Preparing upload...";
    
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "share.php", true);
    xhr.timeout = 36e5; // 1 hour timeout
    
    // Progress event
    xhr.upload.addEventListener("progress", (event) => {
        if (event.lengthComputable) {
            const percent = Math.round(event.loaded / event.total * 100);
            progressBar.style.width = percent + "%";
            progressPercent.textContent = percent + "%";
            currentFile.textContent = `Uploading ${formatFileSize(event.loaded)} of ${formatFileSize(event.total)}`;
            document.title = `(${percent}%) Uploading...`;
        }
    });
    
    // Upload complete
    xhr.onload = function() {
        document.title = "Share Management";
        
        try {
            let response;
            try {
                console.log("Server response status:", xhr.status);
                console.log("Server response headers:", xhr.getAllResponseHeaders());
                console.log("Server response text:", xhr.responseText);
                
                if (xhr.status >= 400) {
                    throw new Error(`Server error: ${xhr.status} ${xhr.statusText}`);
                }
                
                if (!xhr.responseText) {
                    throw new Error("Empty server response");
                }
                
                response = JSON.parse(xhr.responseText);
            } catch (e) {
                console.error("Failed to parse server response:", e);
                console.error("Raw response:", xhr.responseText);
                throw new Error("Invalid server response: " + e.message);
            }
            
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
            
            // Reload the page to show the updated share list instead of redirecting
            location.reload();
        } catch (error) {
            console.error("Upload error:", error);
            handleUploadError(error);
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'Create Share';
        }
    };
    
    // Error handling
    xhr.onerror = function() {
        document.title = "Share Management";
        console.error("Network error occurred");
        handleUploadError(new Error("Network error occurred"));
        submitButton.disabled = false;
        submitButton.textContent = 'Create Share';
    };
    
    xhr.send(formData);
});

// Drag and drop handling
const dropZone = document.getElementById('drop-zone');

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, highlight, false);
});

['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, unhighlight, false);
});

function highlight(e) {
    dropZone.classList.add('highlight');
}

function unhighlight(e) {
    dropZone.classList.remove('highlight');
}

dropZone.addEventListener('drop', handleDrop, false);

function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    
    // Check if the dropped item is a folder
    const items = dt.items;
    if (items && items.length > 0) {
        const item = items[0];
        if (item.kind === 'file') {
            const entry = item.webkitGetAsEntry();
            if (entry && entry.isDirectory) {
                // Handle folder drop
                const folderInput = document.getElementById('folder-input');
                folderInput.files = files;
                handleFolderSelect(folderInput);
            } else {
                // Handle file drop
                const fileInput = document.getElementById('file-input');
                fileInput.files = files;
                handleFileSelect(fileInput);
            }
        }
    }
}
