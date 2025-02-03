document.addEventListener('DOMContentLoaded', () => {
    initializeFileSystem();
    initializeSearch();
    initializeDropzone();
    initializePDFThumbnails();
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

    searchInput.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const fileCards = document.querySelectorAll('.file-card');
        
        fileCards.forEach(card => {
            const fileName = card.querySelector('.file-name').textContent.toLowerCase();
            if (fileName.includes(searchTerm)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    });
}

function initializeDropzone() {
    const dropzone = document.getElementById('dropzone');
    if (!dropzone) return;

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, preventDefaults, false);
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
        fileInput.addEventListener('change', function(e) {
            handleFiles(this.files);
        });
    }
}

function openMediaPreview(url) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <button class="modal-close">&times;</button>
            <video controls style="width: 100%; height: auto;">
                <source src="${url}" type="video/mp4">
                Votre navigateur ne supporte pas la lecture de vidéos.
            </video>
        </div>
    `;
    
    document.body.appendChild(modal);

    const closeBtn = modal.querySelector('.modal-close');
    closeBtn.addEventListener('click', () => {
        modal.remove();
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
}

function openPDFPreview(url) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <button class="modal-close">&times;</button>
            <iframe src="${url}" frameborder="0"></iframe>
        </div>
    `;
    
    document.body.appendChild(modal);

    const closeBtn = modal.querySelector('.modal-close');
    closeBtn.addEventListener('click', () => {
        modal.remove();
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
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
    const dt = e.dataTransfer;
    const files = dt.files;
    handleFiles(files);
}

function handleFiles(files) {
    const formData = new FormData();
    [...files].forEach((file, index) => {
        formData.append(`files[${index}]`, file);
    });

    const progressContainer = document.createElement('div');
    progressContainer.className = 'upload-progress';
    progressContainer.innerHTML = `
        <div class="progress-info">
            <span class="filename">Upload en cours de ${files.length} fichier(s)...</span>
            <span class="percent">0%</span>
        </div>
        <div class="progress-bar">
            <div class="progress" style="width: 0%"></div>
        </div>
    `;
    document.querySelector('.upload-progress-container').appendChild(progressContainer);

    fetch('?upload=1', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            progressContainer.classList.add('success');
            progressContainer.querySelector('.filename').textContent = 'Upload terminé avec succès!';
            setTimeout(() => {
                progressContainer.remove();
                window.location.reload();
            }, 2000);
        } else {
            throw new Error(data.error || 'Erreur lors de l\'upload');
        }
    })
    .catch(error => {
        console.error('Upload error:', error);
        progressContainer.classList.add('error');
        progressContainer.querySelector('.filename').textContent = 'Erreur: ' + error.message;
        setTimeout(() => {
            progressContainer.remove();
        }, 5000);
    });
}

async function initializePDFThumbnails() {
    const thumbnails = document.querySelectorAll('.pdf-thumbnail');
    
    for (const canvas of thumbnails) {
        const pdfUrl = canvas.dataset.pdf;
        const pageNum = parseInt(canvas.dataset.page) || 1;
        
        try {
            const loadingTask = pdfjsLib.getDocument(pdfUrl);
            const pdf = await loadingTask.promise;
            const page = await pdf.getPage(pageNum);
            
            const viewport = page.getViewport({ scale: 1.0 });
            const scale = Math.min(200 / viewport.width, 200 / viewport.height);
            const scaledViewport = page.getViewport({ scale });
            
            canvas.width = scaledViewport.width;
            canvas.height = scaledViewport.height;
            
            const context = canvas.getContext('2d');
            await page.render({
                canvasContext: context,
                viewport: scaledViewport
            }).promise;
            
        } catch (error) {
            console.error('Erreur lors de la génération de la miniature PDF:', error);
            canvas.style.display = 'none';
        }
    }
}

// Gestion du menu mobile
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.sidebar-overlay');

    if (menuToggle && sidebar && overlay) {
        menuToggle.addEventListener('click', toggleMenu);
        overlay.addEventListener('click', toggleMenu);
    }

    function toggleMenu() {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        
        // Change l'icône du menu
        const icon = menuToggle.querySelector('i');
        if (icon) {
            icon.classList.toggle('fa-bars');
            icon.classList.toggle('fa-times');
        }
    }

    // Ferme le menu si la fenêtre est redimensionnée au-dessus de 768px
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            const icon = menuToggle.querySelector('i');
            if (icon) {
                icon.classList.add('fa-bars');
                icon.classList.remove('fa-times');
            }
        }
    });
}); 