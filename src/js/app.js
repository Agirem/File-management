document.addEventListener('DOMContentLoaded', () => {
    initializeFileSystem();
    initializeSearch();
    initializeDropzone();
});

function initializeFileSystem() {
    const fileItems = document.querySelectorAll('.file-item');
    
    fileItems.forEach(item => {
        item.addEventListener('click', (e) => {
            if (e.target.closest('.file-actions')) return;
            
            const link = item.querySelector('a');
            if (link && !item.classList.contains('is-uploading')) {
                if (link.getAttribute('data-type') === 'media') {
                    e.preventDefault();
                    openMediaPreview(link.href);
                }
            }
        });
    });
}

function initializeSearch() {
    const searchInput = document.querySelector('.search-input');
    if (!searchInput) return;

    searchInput.addEventListener('input', debounce((e) => {
        const query = e.target.value.toLowerCase();
        const items = document.querySelectorAll('.file-item');
        
        items.forEach(item => {
            const name = item.querySelector('.file-name').textContent.toLowerCase();
            item.style.display = name.includes(query) ? 'flex' : 'none';
        });
    }, 300));
}

function initializeDropzone() {
    const dropzone = document.querySelector('.dropzone');
    if (!dropzone) return;

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, unhighlight, false);
    });

    dropzone.addEventListener('drop', handleDrop, false);

    const fileInput = dropzone.querySelector('input[type="file"]');
    if (fileInput) {
        fileInput.addEventListener('change', (e) => {
            const files = e.target.files;
            if (files && files.length > 0) {
                handleFiles(Array.from(files));
                e.target.value = '';
            }
        });

        const label = dropzone.querySelector('label.btn');
        if (label) {
            label.addEventListener('click', (e) => {
                e.preventDefault();
                fileInput.click();
            });
        }
    }
}

function openMediaPreview(url) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <span class="close">&times;</span>
            <div class="media-player">
                ${url.includes('.mp4') 
                    ? `<video controls><source src="${url}" type="video/mp4"></video>`
                    : `<audio controls><source src="${url}" type="audio/mpeg"></audio>`
                }
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    modal.querySelector('.close').onclick = () => modal.remove();
    modal.onclick = (e) => {
        if (e.target === modal) modal.remove();
    };
}

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

function highlight(e) {
    document.querySelector('.dropzone').classList.add('dragover');
}

function unhighlight(e) {
    document.querySelector('.dropzone').classList.remove('dragover');
}

function handleDrop(e) {
    const files = e.dataTransfer.files;
    handleFiles(files);
}

function handleFiles(files) {
    [...files].forEach(uploadFile);
}

function uploadFile(file) {
    const formData = new FormData();
    formData.append('file', file);
    
    const progressBar = createProgressBar(file.name);
    const progressFill = progressBar.querySelector('.upload-progress-fill');
    
    const urlParams = new URLSearchParams(window.location.search);
    const currentView = urlParams.get('view');
    const currentPath = urlParams.get('path');
    
    let uploadUrl = '?upload=1';
    if (currentView) uploadUrl += `&view=${currentView}`;
    if (currentPath) uploadUrl += `&path=${currentPath}`;
    
    fetch(uploadUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            progressBar.classList.add('complete');
            progressFill.style.width = '100%';
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            progressBar.classList.add('error');
            progressFill.style.width = '100%';
        }
    })
    .catch(error => {
        progressBar.classList.add('error');
        progressFill.style.width = '100%';
        console.error('Upload error:', error);
    });
}

function createProgressBar(filename) {
    const progressBar = document.createElement('div');
    progressBar.className = 'upload-progress';
    progressBar.innerHTML = `
        <div class="upload-progress-filename">${filename}</div>
        <div class="upload-progress-bar">
            <div class="upload-progress-fill"></div>
        </div>
    `;
    document.querySelector('.upload-progress-container').appendChild(progressBar);
    return progressBar;
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
} 