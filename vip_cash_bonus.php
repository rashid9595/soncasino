<?php
session_start();
require_once 'config/database.php';

// Set page title
$pageTitle = "VIP Nakit Bonus Ayarları";

// Check if user is logged in and 2FA is verified
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['2fa_verified'])) {
    header("Location: 2fa.php");
    exit();
}

// Check user role permissions
if (!in_array($_SESSION['role_id'], [1])) { // 1 = admin
    $_SESSION['error'] = 'Bu sayfaya erişim yetkiniz bulunmamaktadır.';
    header("Location: index.php");
    exit();
}

// Get bonus settings
$stmt = $db->query("
    SELECT * FROM vip_nakit_bonus_ayarlar
    ORDER BY CASE 
        WHEN vip_level = 'Bronz' THEN 1
        WHEN vip_level = 'Gümüş' THEN 2
        WHEN vip_level = 'Altın' THEN 3
        WHEN vip_level = 'Platin' THEN 4
        WHEN vip_level = 'Elmas' THEN 5
        ELSE 6
    END
");
$bonusSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="container-fluid p-0">
    <!-- Content Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-cog me-2 text-primary"></i>VIP Nakit Bonus Ayarları</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb m-0 p-0 bg-transparent">
                <li class="breadcrumb-item"><a href="index.php">Ana Sayfa</a></li>
                <li class="breadcrumb-item"><a href="#">VIP Ayarları</a></li>
                <li class="breadcrumb-item active" aria-current="page">Nakit Bonus Ayarları</li>
            </ol>
        </nav>
    </div>

    <?php
    // Display success or error message
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
                <i class="fas fa-check-circle me-2"></i>' . $_SESSION['success_message'] . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        unset($_SESSION['success_message']);
    }

    if (isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>' . $_SESSION['error_message'] . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        unset($_SESSION['error_message']);
    }
    ?>

    <!-- Alert Container -->
    <div id="alertContainer" class="mt-2 mb-4"></div>
    
    <div class="card shadow-lg border-0 rounded-lg mb-4 bg-dark-card">
        <div class="card-header bg-gradient-primary py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-white"><i class="fas fa-cog me-2"></i>VIP Nakit Bonus Ayarları</h6>
        </div>
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-bordered table-hover dark-table" id="settingsTable">
                    <thead class="bg-medium-blue">
                        <tr>
                            <th class="text-center text-light">VIP Seviyesi</th>
                            <th class="text-center text-light">Bonus Miktarı</th>
                            <th class="text-center text-light">Haftalık Limit</th>
                            <th class="text-center text-light">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bonusSettings as $setting): ?>
                        <?php 
                            $vipLevel = strtolower($setting['vip_level']);
                            $badgeClass = "badge-" . $vipLevel;
                        ?>
                        <tr>
                            <td class="text-center align-middle">
                                <span class="badge <?php echo $badgeClass; ?> py-2 px-3">
                                    <i class="fas fa-crown me-1"></i> <?php echo $setting['vip_level']; ?>
                                </span>
                            </td>
                            <td class="text-success fw-bold text-center align-middle"><?php echo number_format($setting['bonus_amount'], 2); ?> ₺</td>
                            <td class="text-center align-middle">
                                <span class="badge bg-medium-blue text-light py-2 px-3">
                                    <i class="fas fa-repeat me-1"></i> <?php echo $setting['weekly_limit']; ?> kez
                                </span>
                            </td>
                            <td class="text-center align-middle">
                                <button type="button" class="btn btn-primary btn-sm rounded-pill px-3 edit-setting-btn" 
                                        data-level="<?php echo $setting['vip_level']; ?>" 
                                        data-amount="<?php echo $setting['bonus_amount']; ?>" 
                                        data-limit="<?php echo $setting['weekly_limit']; ?>">
                                    <i class="fas fa-edit me-1"></i> Düzenle
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Settings Modal -->
<div class="modal fade" id="editSettingsModal" tabindex="-1" aria-labelledby="editSettingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg bg-dark-blue">
            <div class="modal-header bg-gradient-primary text-white border-0">
                <h5 class="modal-title fw-bold" id="editSettingsModalLabel">
                    <i class="fas fa-edit me-2"></i>Bonus Ayarlarını Düzenle
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-dark-blue">
                <form id="editSettingsForm">
                    <input type="hidden" name="action" value="update_vip_bonus_settings">
                    <input type="hidden" name="vip_level" id="settings_vip_level">
                    
                    <div class="mb-4">
                        <label for="settings_level_display" class="form-label fw-bold mb-2 text-light">VIP Seviyesi:</label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text bg-medium-blue border-0">
                                <i class="fas fa-gem text-primary"></i>
                            </span>
                            <input type="text" class="form-control form-control-lg bg-medium-blue border-0 fw-bold text-primary" id="settings_level_display" readonly>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="settings_bonus_amount" class="form-label fw-bold mb-2 text-light">Bonus Miktarı (₺):</label>
                        <div class="input-group shadow-sm form-floating">
                            <span class="input-group-text bg-medium-blue border-0">
                                <i class="fas fa-money-bill-wave text-success"></i>
                            </span>
                            <input type="number" step="0.01" min="0" class="form-control form-control-lg bg-medium-blue border-0 text-light" id="settings_bonus_amount" name="bonus_amount" required placeholder="0.00">
                            <span class="input-group-text bg-medium-blue border-0 text-success fw-bold">₺</span>
                        </div>
                        <div class="form-text text-light-blue small">Kullanıcılara verilebilecek bonus miktarını belirleyin</div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="settings_weekly_limit" class="form-label fw-bold mb-2 text-light">Haftalık Limit:</label>
                        <div class="input-group shadow-sm form-floating">
                            <span class="input-group-text bg-medium-blue border-0">
                                <i class="fas fa-calendar-week text-warning"></i>
                            </span>
                            <input type="number" min="1" class="form-control form-control-lg bg-medium-blue border-0 text-light" id="settings_weekly_limit" name="weekly_limit" required placeholder="1">
                            <span class="input-group-text bg-medium-blue border-0 text-warning fw-bold">kez</span>
                        </div>
                        <div class="form-text text-light-blue small">Kullanıcıların haftada kaç kez bonus alabileceğini belirleyin</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 d-flex justify-content-between bg-dark-blue">
                <button type="button" class="btn btn-outline-light btn-lg px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>İptal
                </button>
                <button type="button" class="btn btn-primary btn-lg px-4" id="updateSettingsBtn">
                    <i class="fas fa-save me-2"></i>Güncelle
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom modal styles */
.modal-content {
    border-radius: 15px;
    overflow: hidden;
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
}

.bg-soft {
    background-color: #f1f5f9;
}

.bg-dark-blue {
    background-color: #0f172a;
}

.bg-dark-card {
    background-color: #1e293b;
}

.bg-medium-blue {
    background-color: #1e293b;
}

.text-light-blue {
    color: #94a3b8;
}

.form-control:focus, .input-group-text:focus-within {
    box-shadow: none;
    border-color: #3b82f6;
}

.form-control-lg {
    font-size: 1rem;
    padding: 0.7rem 1rem;
}

.input-group {
    border-radius: 10px;
    overflow: hidden;
}

.input-group-text {
    padding-left: 1rem;
    padding-right: 1rem;
}

/* Fix for input field text visibility */
input[type="number"] {
    color: #e2e8f0 !important;
}

input[type="number"]::placeholder {
    color: #64748b;
    opacity: 1;
}

.input-group input:focus {
    box-shadow: inset 0 0 0 2px #3b82f6 !important;
    outline: none;
}

.btn-primary {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    border: none;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(37, 99, 235, 0.3);
}

.btn-outline-light {
    color: #e2e8f0;
    border: 1px solid #334155;
    background: transparent;
    transition: all 0.3s ease;
}

.btn-outline-light:hover {
    background: #1e293b;
    color: #e2e8f0;
}

/* Badge styles for VIP levels */
.badge-bronz {
    background: linear-gradient(135deg, #d97706, #f59e0b);
    color: white;
}

.badge-gümüş {
    background: linear-gradient(135deg, #9ca3af, #d1d5db);
    color: #1f2937;
}

.badge-altın {
    background: linear-gradient(135deg, #fbbf24, #f59e0b);
    color: #7c2d12;
}

.badge-platin {
    background: linear-gradient(135deg, #38bdf8, #0ea5e9);
    color: white;
}

.badge-elmas {
    background: linear-gradient(135deg, #818cf8, #6366f1);
    color: white;
}

/* Table styling */
.table {
    border-collapse: separate;
    border-spacing: 0;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid #334155;
}

.dark-table {
    background-color: #0f172a;
    color: #e2e8f0;
}

.dark-table th {
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.8rem;
    letter-spacing: 0.05em;
    padding: 1rem;
    border-bottom: 2px solid #334155;
}

.dark-table td {
    padding: 1rem;
    border-bottom: 1px solid #334155;
    vertical-align: middle;
}

.dark-table tbody tr:last-child td {
    border-bottom: none;
}

.dark-table tbody tr {
    background-color: #0f172a;
}

.dark-table tbody tr:nth-child(odd) {
    background-color: #1e293b;
}

.dark-table tbody tr:hover {
    background-color: #2d3748;
}

.rounded-pill {
    border-radius: 50rem;
}

.card {
    overflow: hidden;
    border-radius: 15px;
}

.card-header {
    padding: 1.25rem 1.5rem;
}

/* DataTable styling */
.dataTables_wrapper .dataTables_length select {
    border-radius: 8px;
    padding: 0.375rem 2rem 0.375rem 1rem;
    border: 1px solid #334155;
    background-color: #1e293b;
    color: #e2e8f0;
}

.dataTables_wrapper .dataTables_filter input {
    border-radius: 8px;
    padding: 0.375rem 1rem;
    border: 1px solid #334155;
    background-color: #1e293b;
    color: #e2e8f0;
}

.dataTables_wrapper .dataTables_info {
    color: #94a3b8;
    margin-top: 1rem;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    border-radius: 8px;
    padding: 0.375rem 0.75rem;
    margin: 0 0.25rem;
    color: #e2e8f0 !important;
    border: 1px solid #334155 !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: linear-gradient(135deg, #3b82f6, #2563eb) !important;
    border: none !important;
    color: white !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: #1e293b !important;
    border: 1px solid #3b82f6 !important;
    color: #3b82f6 !important;
}
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Set VIP Ayarları menu to be active in sidebar
        if (document.getElementById('vipMenu')) {
            document.getElementById('vipMenu').classList.remove('show');
        }
        if (document.getElementById('vipConfigMenu')) {
            document.getElementById('vipConfigMenu').classList.add('show');
        }
        
        // Initialize DataTable
        if ($.fn.DataTable) {
            $('#settingsTable').DataTable({
                responsive: true,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/tr.json'
                },
                paging: false,
                searching: false,
                info: false,
                columnDefs: [
                    { targets: -1, orderable: false } // Disable sorting for actions column
                ]
            });
        }
        
        // Helper function to show alerts
        function showAlert(type, message) {
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            
            const alertHTML = `
                <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                    <i class="fas ${icon} me-2"></i>${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            
            $("#alertContainer").html(alertHTML);
            
            // Auto dismiss after 3 seconds
            setTimeout(() => {
                $(".alert").alert('close');
            }, 3000);
        }
        
        // Find all edit buttons and attach click handler
        document.querySelectorAll('.edit-setting-btn').forEach(button => {
            button.addEventListener('click', function() {
                // Get data attributes
                const level = this.getAttribute('data-level');
                const amount = this.getAttribute('data-amount');
                const limit = this.getAttribute('data-limit');
                
                console.log("Edit button clicked:", level, amount, limit);
                
                // Set values in form
                document.getElementById('settings_vip_level').value = level;
                document.getElementById('settings_level_display').value = level;
                document.getElementById('settings_bonus_amount').value = amount;
                document.getElementById('settings_weekly_limit').value = limit;
                
                // Show modal
                const editModal = new bootstrap.Modal(document.getElementById('editSettingsModal'));
                editModal.show();
            });
        });
        
        // Handle Update button click
        document.getElementById('updateSettingsBtn').addEventListener('click', function() {
            const vip_level = document.getElementById('settings_vip_level').value;
            const bonus_amount = document.getElementById('settings_bonus_amount').value;
            const weekly_limit = document.getElementById('settings_weekly_limit').value;
            
            if (!bonus_amount || !weekly_limit) {
                showAlert("error", "Lütfen tüm alanları doldurun!");
                return;
            }
            
            // AJAX request to update settings
            $.ajax({
                url: 'process.php',
                type: 'POST',
                data: {
                    action: 'update_vip_bonus_settings',
                    vip_level: vip_level,
                    bonus_amount: bonus_amount,
                    weekly_limit: weekly_limit
                },
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (result.status === 'success') {
                            showAlert("success", result.message || "Bonus ayarları başarıyla güncellendi.");
                            bootstrap.Modal.getInstance(document.getElementById('editSettingsModal')).hide();
                            setTimeout(() => { location.reload(); }, 1500);
                        } else {
                            showAlert("error", result.message || "İşlem sırasında bir hata oluştu!");
                        }
                    } catch (e) {
                        showAlert("error", "İşlem sırasında bir hata oluştu!");
                        console.error(e);
                    }
                },
                error: function() {
                    showAlert("error", "Sunucu hatası! Lütfen daha sonra tekrar deneyin.");
                }
            });
        });
    });
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 