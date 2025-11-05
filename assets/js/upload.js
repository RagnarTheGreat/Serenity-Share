/**
 * File Uploader Class
 * Handles file uploads with progress tracking and error handling
 */
class FileUploader {
    /**
     * Initialize the FileUploader with DOM elements
     */
    constructor() {
        this.form = document.getElementById("uploadForm");
        this.progressContainer = document.getElementById("uploadProgress");
        this.progressBar = document.getElementById("progressBar");
        this.progressPercent = document.getElementById("progressPercent");
        this.currentFile = document.getElementById("currentFile");
        this.uploadSpeed = document.getElementById("uploadSpeed");
        this.timeRemaining = document.getElementById("timeRemaining");
        this.startTime = 0;
        this.uploadedSize = 0;
        this.totalSize = 0;
        this.initializeEventListeners();
    }

    /**
     * Set up event listeners
     */
    initializeEventListeners() {
        this.form.addEventListener("submit", e => this.handleUpload(e));
    }

    /**
     * Format file size to human-readable format
     * @param {number} bytes - Size in bytes
     * @returns {string} Formatted size with units
     */
    formatSize(bytes) {
        if (bytes === 0) return "0 B";
        const units = ["B", "KB", "MB", "GB"];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return parseFloat((bytes / Math.pow(1024, i)).toFixed(2)) + " " + units[i];
    }

    /**
     * Format time remaining to human-readable format
     * @param {number} seconds - Time in seconds
     * @returns {string} Formatted time
     */
    formatTime(seconds) {
        if (seconds === Infinity || seconds === 0) return "Calculating...";
        if (seconds < 60) return `${Math.round(seconds)}s`;
        return `${Math.floor(seconds/60)}m ${Math.round(seconds%60)}s`;
    }

    /**
     * Update progress UI elements
     * @param {number} loaded - Bytes loaded
     * @param {number} total - Total bytes
     */
    updateProgress(loaded, total) {
        const percent = Math.round(loaded / total * 100);
        this.progressBar.style.width = `${percent}%`;
        this.progressPercent.textContent = `${percent}%`;

        const currentTime = Date.now();
        const elapsedSeconds = (currentTime - this.startTime) / 1000;
        const bytesPerSecond = loaded / elapsedSeconds;
        const secondsRemaining = (total - loaded) / bytesPerSecond;
        
        this.uploadSpeed.textContent = `${this.formatSize(bytesPerSecond)}/s`;
        this.timeRemaining.textContent = this.formatTime(secondsRemaining);
    }

    /**
     * Handle the file upload process
     * @param {Event} e - Form submit event
     */
    async handleUpload(e) {
        e.preventDefault();
        const files = document.getElementById("fileInput").files;
        
        if (files.length === 0) return;
        
        this.progressContainer.style.display = "block";
        this.startTime = Date.now();
        this.totalSize = Array.from(files).reduce((sum, file) => sum + file.size, 0);

        const formData = new FormData();
        Array.from(files).forEach(file => {
            formData.append("files[]", file);
        });

        try {
            const response = await fetch("upload.php", {
                method: "POST",
                body: formData,
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                },
                onUploadProgress: event => {
                    this.updateProgress(event.loaded, event.total);
                }
            });
            
            const result = await response.json();
            
            if (!result.status) throw new Error(result.error);
            
            this.currentFile.textContent = "Upload complete!";
            setTimeout(() => {
                this.progressContainer.style.display = "none";
            }, 2000);
        } catch (error) {
            this.currentFile.textContent = `Error: ${error.message}`;
            this.progressBar.style.backgroundColor = "var(--danger-color)";
        }
    }
}

// Initialize the FileUploader when the DOM is ready
document.addEventListener("DOMContentLoaded", () => {
    new FileUploader();
});
