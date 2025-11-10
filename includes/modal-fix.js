/**
 * Admin paneli için global modal düzeltmesi
 * Bu script, modal arka planlarının (backdrop) kaldırılmaması sorununu giderir
 * ve modalların ekranda düzgün ortalanmasını sağlar
 */
document.addEventListener('DOMContentLoaded', function() {
    // Modal stillerini uygula
    applyModalStyles();
    
    // Modal arkaplanlarını temizle
    cleanupModalBackdrops();
    
    // Chat sayfalarını kontrol et ve özel işlemler uygula
    chatSayfasiKontrol();
    
    // Hızlı fix - Sayfa yüklenir yüklenmez açık modalları kapat
    document.querySelectorAll('.modal.show').forEach(modal => {
        if (modal.style.display === 'block' && document.querySelector('.modal-backdrop')) {
            setTimeout(() => {
                modal.classList.remove('show');
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
                modal.removeAttribute('aria-modal');
                modal.removeAttribute('role');
                
                // Tüm backdrop'ları temizle
                const backdrops = document.querySelectorAll('.modal-backdrop');
                backdrops.forEach(backdrop => backdrop.remove());
                
                // Body'den modal-open sınıfını kaldır
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }, 500);
        }
    });
    
    // Modal show/hide event listener'larını ekle
    document.addEventListener('show.bs.modal', function(e) {
        cleanupModalBackdrops();
        
        // Tıklanan modal dışındaki tüm modalları kapat
        const targetModal = e.target;
        document.querySelectorAll('.modal.show').forEach(modal => {
            if (modal !== targetModal) {
                modal.classList.remove('show');
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
                modal.removeAttribute('aria-modal');
                modal.removeAttribute('role');
            }
        });
    });
    
    document.addEventListener('hidden.bs.modal', function() {
        setTimeout(function() {
            cleanupModalBackdrops();
            
            // Tüm backdrop'ları temizle
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());
            
            // Eğer açık bir modal yoksa, body'den modal-open sınıfını ve stillerini kaldır
            const openModals = document.querySelectorAll('.modal.show');
            if (openModals.length === 0) {
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }
        }, 100);
    });
    
    // Yeni: Manuel modal kapatma mekanizması
    document.querySelectorAll('.btn-close, button[data-bs-dismiss="modal"]').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const modalElement = this.closest('.modal');
            if (modalElement) {
                // Show sınıfını kaldır
                modalElement.classList.remove('show');
                modalElement.style.display = 'none';
                modalElement.setAttribute('aria-hidden', 'true');
                modalElement.removeAttribute('aria-modal');
                modalElement.removeAttribute('role');
                
                // Arka planları temizle
                const backdrops = document.querySelectorAll('.modal-backdrop');
                backdrops.forEach(backdrop => backdrop.remove());
                
                // Body'den modal-open sınıfını kaldır
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }
        });
    });
    
    // Modal backdrop'a tıklandığında modal'ı kapat
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-backdrop')) {
            const openModals = document.querySelectorAll('.modal.show');
            openModals.forEach(modal => {
                modal.classList.remove('show');
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
                modal.removeAttribute('aria-modal');
                modal.removeAttribute('role');
            });
            
            // Backdrop'ları temizle
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());
            
            // Body'den modal-open sınıfını kaldır
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        }
    });
    
    // Tüm modallara tutarlı stil uygula
    modalStilleriniUygula();
    
    // Temizleme intervalini başlat
    startCleanupInterval();
    
    // Sayfa yüklendikten sonra açık kalmış modalları kontrol et
    setTimeout(function() {
        fixOpenModals();
    }, 500);
    
    // Sayfa tam yüklendikten sonra tekrar kontrol et (500ms sonra)
    setTimeout(function() {
        fixOpenModals();
    }, 1000);
    
    // Sayfa tam yüklendikten sonra 2 saniye sonra bir kez daha kontrol et
    setTimeout(function() {
        fixOpenModals();
    }, 2000);
});

// Açık kalmış modalları düzelt
function fixOpenModals() {
    // Açık modal sayısını kontrol et
    const openModals = document.querySelectorAll('.modal.show');
    
    // Birden fazla açık modal varsa sadece bir tanesini aç, diğerlerini kapat
    if (openModals.length > 1) {
        for (let i = 1; i < openModals.length; i++) {
            openModals[i].classList.remove('show');
            openModals[i].style.display = 'none';
            openModals[i].setAttribute('aria-hidden', 'true');
            openModals[i].removeAttribute('aria-modal');
            openModals[i].removeAttribute('role');
        }
    }
    
    // Backdrop sayısını kontrol et
    const backdrops = document.querySelectorAll('.modal-backdrop');
    
    // Birden fazla backdrop varsa sadece bir tanesini bırak
    if (backdrops.length > 1) {
        for (let i = 1; i < backdrops.length; i++) {
            backdrops[i].remove();
        }
    }
    
    // Hiç açık modal yoksa backdrop'ları ve modal-open sınıfını kaldır
    if (openModals.length === 0 && backdrops.length > 0) {
        backdrops.forEach(backdrop => backdrop.remove());
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }
    
    // "Açık" durumda görünen ama gerçekte açık olmayan modalları düzelt
    document.querySelectorAll('.modal[style*="display: block"]:not(.show)').forEach(modal => {
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        modal.removeAttribute('aria-modal');
        modal.removeAttribute('role');
    });
}

// Chat sayfalarını kontrol et
function chatSayfasiKontrol() {
    // Mevcut URL'yi kontrol et
    const currentPath = window.location.pathname;
    const isChatPage = currentPath.includes('chat_') || currentPath.includes('chat.php');
    
    if (isChatPage) {
        console.log('Chat sayfası tespit edildi, özel modal düzeltmeleri uygulanıyor.');
        
        // Chat sayfaları için özel stiller
        const chatModalStil = document.createElement('style');
        chatModalStil.innerHTML = `
            /* Chat sayfası için özel modal ve backdrop ayarları */
            body.modal-open .modal-backdrop {
                pointer-events: none !important; /* İçerik yazılabilmesi için tıklamaya tepki vermeyi engelle */
                opacity: 0.4 !important;
            }
            
            /* Chat sayfasında açık modal olduğunda backdrop'u hafiflet */
            body.chat-page .modal-backdrop.show {
                opacity: 0.4 !important;
                pointer-events: none !important;
            }
            
            /* Chat inputlarına odaklanıldığında backdrop'u görünmez yap */
            body.chat-page input:focus ~ .modal-backdrop,
            body.chat-page textarea:focus ~ .modal-backdrop,
            body.chat-page select:focus ~ .modal-backdrop {
                opacity: 0.2 !important;
            }
            
            /* Fazladan backdrop'ları gizle */
            body.chat-page .modal-backdrop:not(:first-child) {
                display: none !important;
            }
            
            /* Modal içeriğine sabit z-index ver ve position:relative ekleyerek fare üzerine geldiğinde içe kaçmasını engelle */
            body.chat-page .modal-content {
                position: relative;
                z-index: 2000 !important;
            }
            
            /* Modal içindeki input ve butonlar için z-index değerini arttır */
            body.chat-page .modal-body,
            body.chat-page .modal-footer,
            body.chat-page .modal-header {
                position: relative;
                z-index: 2001 !important;
            }
            
            /* Modal diyaloglarına position:relative ekleyerek daha kararlı hale getir */
            body.chat-page .modal-dialog {
                position: relative;
                z-index: 1999 !important;
            }
        `;
        document.head.appendChild(chatModalStil);
        
        // Body'ye chat-page sınıfını ekle
        document.body.classList.add('chat-page');
        
        // Yeni: Chat sayfalarındaki kapatma düğmelerini özel olarak kontrol et
        document.querySelectorAll('.modal .btn-close, .modal button[data-bs-dismiss="modal"]').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const modalElement = this.closest('.modal');
                if (modalElement) {
                    // Show sınıfını kaldır
                    modalElement.classList.remove('show');
                    modalElement.style.display = 'none';
                    modalElement.setAttribute('aria-hidden', 'true');
                    modalElement.removeAttribute('aria-modal');
                    modalElement.removeAttribute('role');
                    
                    // Arka planları temizle
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(backdrop => backdrop.remove());
                    
                    // Body'den modal-open sınıfını kaldır
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                }
            });
        });
        
        // Chat sayfası için fazladan interval kontrolü
        setInterval(function() {
            const backdrops = document.querySelectorAll('.modal-backdrop');
            
            // Backdrop'lara özel chat sınıfı ekle
            backdrops.forEach(backdrop => {
                backdrop.classList.add('chat-backdrop');
                backdrop.style.pointerEvents = 'none';
            });
            
            // Modal açık değilse backdrop'ları temizle
            const openModals = document.querySelectorAll('.modal.show');
            if (openModals.length === 0 && backdrops.length > 0) {
                backdrops.forEach(backdrop => backdrop.remove());
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }
            
            // Birden fazla modal açıksa sadece bir tanesini bırak
            if (openModals.length > 1) {
                for (let i = 1; i < openModals.length; i++) {
                    openModals[i].classList.remove('show');
                    openModals[i].style.display = 'none';
                }
            }
            
            // "Açık" durumda görünen ama gerçekte açık olmayan modalları düzelt
            document.querySelectorAll('.modal[style*="display: block"]:not(.show)').forEach(modal => {
                modal.style.display = 'none';
            });
        }, 150); // Daha sık kontrol et
    }
}

// Modal stillerini uygula
function applyModalStyles() {
    const style = document.createElement('style');
    style.innerHTML = `
        /* Modal pozisyonlama ve z-index düzenlemeleri */
        .modal {
            z-index: 1050 !important;
            position: fixed;
        }
        
        .modal-backdrop {
            z-index: 1040 !important;
            pointer-events: none !important;
            opacity: 0.5 !important;
            position: fixed;
        }
        
        .modal-dialog {
            z-index: 1055 !important;
            margin: 0 auto;
            display: flex;
            align-items: center;
            min-height: calc(100% - 1rem);
            position: relative;
        }
        
        .modal-content {
            z-index: 1056 !important;
            width: 100%;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
        }
        
        .modal-header, .modal-body, .modal-footer {
            position: relative;
            z-index: 1057 !important;
        }
        
        /* Modal içindeki elementlerin hover durumunda içe kaçmaması için */
        .modal-content .form-control,
        .modal-content .btn,
        .modal-content .input-group,
        .modal-content select,
        .modal-content label,
        .modal-content textarea {
            position: relative;
            z-index: 1058 !important;
        }
        
        /* Form alanlarının üzerine gelindiğinde içe kaçmaması için */
        .modal-content .form-control:hover,
        .modal-content .btn:hover,
        .modal-content .input-group:hover,
        .modal-content select:hover {
            z-index: 1060 !important;
        }
        
        /* Chat sayfaları için özel düzenlemeler */
        body.chat-page .modal-backdrop {
            opacity: 0.3 !important;
        }
        
        body.chat-page .modal {
            z-index: 1060 !important;
        }
        
        body.chat-page .modal-backdrop {
            z-index: 1050 !important;
        }
        
        /* Form alanlarına odaklanıldığında backdrop'u daha şeffaf yap */
        input:focus ~ .modal-backdrop,
        textarea:focus ~ .modal-backdrop,
        select:focus ~ .modal-backdrop {
            opacity: 0.1 !important;
        }
        
        /* Birden fazla backdrop olmasını engelle */
        .modal-backdrop:not(:first-child) {
            display: none !important;
        }
        
        /* Açık kalmış modalları düzelt */
        .modal[aria-modal="true"]:not(.show) {
            display: none !important;
        }
        
        /* Açılırken ve kapanırken düzgün görünmesi için */
        .modal.fade .modal-dialog {
            transition: transform 0.2s ease-out !important;
            transform: translate(0, -50px) !important;
        }
        
        .modal.fade.show .modal-dialog {
            transform: translate(0, 0) !important;
        }
        
        /* Modal içeriğinin dokümandan ayrılmasını ve üste çıkmasını sağla */
        .modal-open .modal {
            overflow-x: hidden;
            overflow-y: auto;
            pointer-events: auto !important;
        }
        
        /* Modal element'lerinin içe kaçmaması için */
        .modal-content, 
        .modal-header, 
        .modal-body, 
        .modal-footer, 
        .modal-content *, 
        .modal-content *:hover, 
        .modal-content *:focus {
            z-index: auto !important;
        }
    `;
    document.head.appendChild(style);
}

// Modal arkaplanlarını temizle
function cleanupModalBackdrops() {
    const backdrops = document.querySelectorAll('.modal-backdrop');
    const openModals = document.querySelectorAll('.modal.show');
    
    // Modal açık değilse ve backdrop kalmışsa temizle
    if (openModals.length === 0 && backdrops.length > 0) {
        backdrops.forEach(backdrop => backdrop.remove());
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }
    
    // Birden fazla backdrop varsa ilki hariç diğerlerini kaldır
    if (backdrops.length > 1) {
        for (let i = 1; i < backdrops.length; i++) {
            backdrops[i].remove();
        }
    }
}

// Temizleme intervalini başlat
function startCleanupInterval() {
    setInterval(function() {
        const backdrops = document.querySelectorAll('.modal-backdrop');
        const openModals = document.querySelectorAll('.modal.show');
        
        // Her bir backdrop'a pointer-events: none uygula
        backdrops.forEach(backdrop => {
            backdrop.style.pointerEvents = 'none';
        });
        
        // Modal açık değilse ve backdrop kalmışsa temizle
        if (openModals.length === 0 && backdrops.length > 0) {
            backdrops.forEach(backdrop => backdrop.remove());
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        }
        
        // Birden fazla backdrop varsa ilki hariç diğerlerini kaldır
        if (backdrops.length > 1) {
            for (let i = 1; i < backdrops.length; i++) {
                backdrops[i].remove();
            }
        }
        
        // Birden fazla açık modal varsa sadece bir tanesini bırak
        if (openModals.length > 1) {
            for (let i = 1; i < openModals.length; i++) {
                openModals[i].classList.remove('show');
                openModals[i].style.display = 'none';
                openModals[i].setAttribute('aria-hidden', 'true');
                openModals[i].removeAttribute('aria-modal');
                openModals[i].removeAttribute('role');
            }
        }
        
        // "show" sınıfı olmayan modalları düzelt
        document.querySelectorAll('.modal[style*="display: block"]').forEach(modal => {
            if (!modal.classList.contains('show')) {
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
                modal.removeAttribute('aria-modal');
                modal.removeAttribute('role');
            }
        });
    }, 150); // Daha sık kontrol et
}

// Tüm modallara tutarlı stil uygula
function modalStilleriniUygula() {
    const tumModallar = document.querySelectorAll('.modal');
    
    tumModallar.forEach(modal => {
        if (modal.classList.contains('stilli')) return;
        
        // Modal dialog stilini güncelle
        const modalDialog = modal.querySelector('.modal-dialog');
        if (modalDialog) {
            modalDialog.classList.add('modal-dialog-centered');
            modalDialog.style.transition = 'transform 0.25s ease-out';
            modalDialog.style.position = 'relative';
            modalDialog.style.zIndex = '1055';
        }
        
        // Modal başlık stilini güncelle
        const modalHeader = modal.querySelector('.modal-header');
        if (modalHeader) {
            modalHeader.classList.add('bg-dark', 'text-white', 'border-dark');
            modalHeader.style.position = 'relative';
            modalHeader.style.zIndex = '1056';
        }
        
        // Modal içeriğini güncelle
        const modalContent = modal.querySelector('.modal-content');
        if (modalContent) {
            modalContent.style.position = 'relative';
            modalContent.style.zIndex = '1055';
        }
        
        // Modal body ve footer stilini güncelle
        const modalBody = modal.querySelector('.modal-body');
        if (modalBody) {
            modalBody.style.position = 'relative';
            modalBody.style.zIndex = '1056';
        }
        
        const modalFooter = modal.querySelector('.modal-footer');
        if (modalFooter) {
            modalFooter.style.position = 'relative';
            modalFooter.style.zIndex = '1056';
        }
        
        // Modal içindeki tüm form elemanlarını güncelle
        const formElements = modal.querySelectorAll('input, select, textarea, button, .form-control, .input-group');
        formElements.forEach(element => {
            element.style.position = 'relative';
            element.style.zIndex = '1057';
        });
        
        // Modal başlığına icon ekle (eğer yoksa)
        const modalTitle = modal.querySelector('.modal-title');
        if (modalTitle && !modalTitle.querySelector('i')) {
            const icon = document.createElement('i');
            icon.classList.add('bi', 'bi-info-circle', 'me-2');
            modalTitle.prepend(icon);
        }
        
        // Modal içindeki input gruplarını stille
        const inputGroups = modal.querySelectorAll('.input-group-text');
        inputGroups.forEach(inputGroup => {
            if (!inputGroup.classList.contains('bg-dark')) {
                inputGroup.classList.add('bg-dark', 'border-dark', 'text-light');
            }
            inputGroup.style.position = 'relative';
            inputGroup.style.zIndex = '1057';
        });
        
        // Kapanma düğmelerine tıklanınca modalı kapat
        const closeButtons = modal.querySelectorAll('.btn-close, [data-bs-dismiss="modal"]');
        closeButtons.forEach(button => {
            button.style.position = 'relative';
            button.style.zIndex = '1060';
            
            if (!button.hasAttribute('data-modal-fixed')) {
                button.setAttribute('data-modal-fixed', 'true');
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Modalı kapat
                    modal.classList.remove('show');
                    modal.style.display = 'none';
                    modal.setAttribute('aria-hidden', 'true'); 
                    modal.removeAttribute('aria-modal');
                    modal.removeAttribute('role');
                    
                    // Backdrop'ları temizle
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(backdrop => backdrop.remove());
                    
                    // Body'den modal-open sınıfını kaldır
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                });
            }
        });
        
        // Stillendirildiğini işaretle
        modal.classList.add('stilli');
    });
}

// Modallar için ek CSS stilleri
const modalStil = document.createElement('style');
modalStil.innerHTML = `
    .modal-content {
        border: none;
        border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        will-change: transform;
        transform: translateZ(0);
        -webkit-transform: translateZ(0);
        backface-visibility: hidden;
        -webkit-backface-visibility: hidden;
        transform-style: preserve-3d;
        -webkit-transform-style: preserve-3d;
    }
    
    .modal-header {
        border-radius: 8px 8px 0 0;
        will-change: transform;
        transform: translateZ(0);
        -webkit-transform: translateZ(0);
    }
    
    .modal-backdrop.show {
        opacity: 0.7;
        backdrop-filter: blur(3px);
    }
    
    /* Her zaman tek bir backdrop olmasını sağla */
    .modal-backdrop + .modal-backdrop {
        display: none !important;
    }
    
    .modal.fade .modal-dialog {
        transition: transform 0.3s ease-out;
        will-change: transform;
    }
    
    .modal.fade.show .modal-dialog {
        transform: translate(0, 0);
    }
    
    .modal-footer {
        border-top-color: #dee2e6;
    }
    
    /* Modal arka planı üzerine tıklanabilirliği arttır */
    .modal-backdrop {
        cursor: pointer;
    }
    
    /* Gelişmiş buton stilleri */
    .modal .btn {
        border-radius: 5px;
        font-weight: 500;
        padding: 6px 15px;
        transition: all 0.2s;
        position: relative;
        z-index: 1060 !important;
    }
    
    /* Form kontrol stilleri */
    .modal .form-control {
        border: 1px solid #ced4da;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        position: relative;
        z-index: 1058 !important;
    }
    
    .modal .form-control:focus {
        border-color: #80bdff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        z-index: 1060 !important;
    }
    
    /* Modal z-index değerlerini düzelt */
    .modal {
        z-index: 1500 !important;
    }
    
    .modal-backdrop {
        z-index: 1400 !important;
    }
    
    /* Modal arka planının saydam olmasını sağla */
    .modal-backdrop {
        pointer-events: none !important;
    }
    
    /* Modal-open sınıfını düzelt */
    body.modal-open {
        overflow: hidden;
        padding-right: 0 !important;
    }
    
    /* Tüm modal bileşenlerinin içe kaçmaması için */
    .modal * {
        transform: translateZ(0);
        -webkit-transform: translateZ(0);
    }
    
    /* Tüm modalların açılırken sabit bir konumda kalmasını sağla */
    .modal,
    .modal.fade,
    .modal.fade.show {
        position: fixed !important;
        top: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        left: 0 !important;
        transform: none !important;
    }
    
    /* Chat sayfalarında modal içeriğinin üzerine gelince içe kaçma sorununu çöz */
    body.chat-page .modal-content:hover,
    body.chat-page .modal-content *:hover,
    body.chat-page .modal-dialog:hover {
        z-index: 2000 !important;
    }
    
    /* Modal içeriğini 3D uzayda öne çıkar */
    .modal-content, 
    .modal-dialog {
        transform: translateZ(0);
        -webkit-transform: translateZ(0);
    }
`;
document.head.appendChild(modalStil); 