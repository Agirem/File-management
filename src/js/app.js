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
                const type = link.getAttribute('data-type');
                if (type === 'media') {
                    e.preventDefault();
                    openMediaPreview(link.href);
                } else if (type === 'pdf') {
                    e.preventDefault();
                    openPDFPreview(link.href);
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

function openPDFPreview(url) {
    const modal = document.createElement('div');
    modal.className = 'modal pdf-modal';
    modal.innerHTML = `
        <div class="modal-content">
            <span class="close">&times;</span>
            <iframe src="${url}" class="pdf-viewer"></iframe>
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

async function previewPDF(filePath) {
    // Créer la modal
    const modal = document.createElement('div');
    modal.className = 'modal pdf-modal';
    modal.innerHTML = `
        <div class="modal-content">
            <span class="close">&times;</span>
            <div class="pdf-controls">
                <button class="btn btn-icon" id="prev-page">◀</button>
                <span id="page-info">Page <span id="page-num"></span> / <span id="page-count"></span></span>
                <button class="btn btn-icon" id="next-page">▶</button>
                <select id="zoom-select">
                    <option value="0.5">50%</option>
                    <option value="0.75">75%</option>
                    <option value="1" selected>100%</option>
                    <option value="1.25">125%</option>
                    <option value="1.5">150%</option>
                    <option value="2">200%</option>
                </select>
            </div>
            <canvas id="pdf-canvas"></canvas>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Variables pour le PDF
    let pdfDoc = null;
    let pageNum = 1;
    let scale = 1;
    const canvas = document.getElementById('pdf-canvas');
    const ctx = canvas.getContext('2d');
    
    // Charger le PDF
    try {
        const loadingTask = pdfjsLib.getDocument('?pdf_preview=1&file=' + encodeURIComponent(filePath));
        pdfDoc = await loadingTask.promise;
        document.getElementById('page-count').textContent = pdfDoc.numPages;
        renderPage(pageNum);
    } catch (error) {
        console.error('Erreur lors du chargement du PDF:', error);
        modal.remove();
        alert('Erreur lors du chargement du PDF');
        return;
    }
    
    // Fonction pour rendre une page
    async function renderPage(num) {
        const page = await pdfDoc.getPage(num);
        const viewport = page.getViewport({ scale });
        
        canvas.height = viewport.height;
        canvas.width = viewport.width;
        
        try {
            await page.render({
                canvasContext: ctx,
                viewport: viewport
            }).promise;
            
            document.getElementById('page-num').textContent = num;
        } catch (error) {
            console.error('Erreur lors du rendu de la page:', error);
        }
    }
    
    // Gestionnaires d'événements
    document.getElementById('prev-page').onclick = () => {
        if (pageNum <= 1) return;
        pageNum--;
        renderPage(pageNum);
    };
    
    document.getElementById('next-page').onclick = () => {
        if (pageNum >= pdfDoc.numPages) return;
        pageNum++;
        renderPage(pageNum);
    };
    
    document.getElementById('zoom-select').onchange = (e) => {
        scale = parseFloat(e.target.value);
        renderPage(pageNum);
    };
    
    // Fermeture de la modal
    modal.querySelector('.close').onclick = () => modal.remove();
    modal.onclick = (e) => {
        if (e.target === modal) modal.remove();
    };
} 