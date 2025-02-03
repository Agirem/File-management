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
    const fileInput = document.getElementById('file-input');
    if (!fileInput) return;

    fileInput.addEventListener('change', function(e) {
        handleFiles(this.files);
    });
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
    const dropzone = document.querySelector('.upload-zone');
    if (dropzone) {
        dropzone.classList.add('dragover');
    }
}

function unhighlight(e) {
    const dropzone = document.querySelector('.upload-zone');
    if (dropzone) {
        dropzone.classList.remove('dragover');
    }
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

    // Ajout du paramètre upload=1 dans l'URL
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('upload', '1');

    fetch(currentUrl.toString(), {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error(text || `Erreur HTTP: ${response.status}`);
            });
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

function deleteFile(filePath, fileName) {
    // Création du modal
    const modal = document.createElement('div');
    modal.className = 'confirm-modal';
    modal.innerHTML = `
        <div class="confirm-modal-content">
            <div class="confirm-modal-icon">
                <i class="fas fa-trash-alt"></i>
            </div>
            <h3 class="confirm-modal-title">Confirmer la suppression</h3>
            <p class="confirm-modal-message">
                Êtes-vous sûr de vouloir supprimer le fichier "${fileName}" ?<br>
                Cette action est irréversible.
            </p>
            <div class="confirm-modal-buttons">
                <button class="confirm-btn confirm-btn-cancel">
                    <i class="fas fa-times"></i>
                    Annuler
                </button>
                <button class="confirm-btn confirm-btn-delete">
                    <i class="fas fa-trash-alt"></i>
                    Supprimer
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    
    // Animation d'entrée
    setTimeout(() => modal.classList.add('active'), 10);

    // Gestionnaires d'événements
    const closeModal = () => {
        modal.classList.remove('active');
        setTimeout(() => modal.remove(), 300);
    };

    const handleDelete = () => {
        // Désactiver les boutons pendant la suppression
        const buttons = modal.querySelectorAll('.confirm-btn');
        buttons.forEach(btn => {
            btn.disabled = true;
            btn.style.opacity = '0.7';
        });

        // Modifier le message
        const message = modal.querySelector('.confirm-modal-message');
        message.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Suppression en cours...';

        const formData = new FormData();
        formData.append('delete', '1');
        formData.append('file', filePath);

        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Animation de succès avant le rechargement
                message.innerHTML = '<i class="fas fa-check-circle" style="color: var(--success);"></i> Fichier supprimé avec succès!';
                setTimeout(() => {
                    closeModal();
                    window.location.reload();
                }, 1000);
            } else {
                throw new Error(data.error || 'Erreur lors de la suppression');
            }
        })
        .catch(error => {
            console.error('Delete error:', error);
            message.innerHTML = `<i class="fas fa-exclamation-circle" style="color: var(--error);"></i> ${error.message}`;
            // Réactiver le bouton d'annulation
            buttons[0].disabled = false;
            buttons[0].style.opacity = '1';
        });
    };

    // Événements des boutons
    modal.querySelector('.confirm-btn-cancel').addEventListener('click', closeModal);
    modal.querySelector('.confirm-btn-delete').addEventListener('click', handleDelete);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
}