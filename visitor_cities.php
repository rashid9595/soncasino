<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "Ziyaretçi Şehirleri";

// Check if user is logged in and 2FA is verified
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['2fa_verified'])) {
    header("Location: 2fa.php");
    exit();
}

// Check permissions
$isAdmin = ($_SESSION['role_id'] == 1);
if (!$isAdmin) {
    $_SESSION['error'] = 'Bu sayfayı görüntüleme izniniz yok.';
    header("Location: index.php");
    exit();
}

// Process form submissions
$error = "";
$success = "";

// Process city creation
if (isset($_POST['action']) && $_POST['action'] == 'create_city') {
    $city = trim($_POST['city']);
    $visitCount = (int)$_POST['visit_count'];
    
    if (empty($city)) {
        $error = "Şehir adı gereklidir";
    } else {
        try {
            // Check if city already exists
            $stmt = $db->prepare("SELECT COUNT(*) FROM ziyaretci_sehirleri WHERE city = ?");
            $stmt->execute([$city]);
            $cityExists = ($stmt->fetchColumn() > 0);
            
            if ($cityExists) {
                $error = "Bu şehir zaten eklenmiş";
            } else {
                // Insert new city
                $stmt = $db->prepare("INSERT INTO ziyaretci_sehirleri (city, visit_count, last_visited) VALUES (?, ?, NOW())");
                $stmt->execute([$city, $visitCount]);
                
                // Log activity
                $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, ip_address, details) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $_SESSION['admin_id'],
                    'create',
                    $_SERVER['REMOTE_ADDR'],
                    "Ziyaretçi şehri eklendi: $city"
                ]);
                
                $success = "Şehir başarıyla eklendi";
            }
        } catch (PDOException $e) {
            $error = "Şehir eklenirken bir hata oluştu: " . $e->getMessage();
        }
    }
}

// Process city update
if (isset($_POST['action']) && $_POST['action'] == 'update_city') {
    $id = (int)$_POST['id'];
    $city = trim($_POST['city']);
    $visitCount = (int)$_POST['visit_count'];
    
    if (empty($id)) {
        $error = "Geçersiz şehir ID'si";
    } else {
        try {
            // Update city
            $stmt = $db->prepare("UPDATE ziyaretci_sehirleri SET city = ?, visit_count = ? WHERE id = ?");
            $stmt->execute([$city, $visitCount, $id]);
            
            // Log activity
            $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, ip_address, details) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $_SESSION['admin_id'],
                'update',
                $_SERVER['REMOTE_ADDR'],
                "Ziyaretçi şehri güncellendi: $city (ID: $id)"
            ]);
            
            $success = "Şehir başarıyla güncellendi";
        } catch (PDOException $e) {
            $error = "Şehir güncellenirken bir hata oluştu: " . $e->getMessage();
        }
    }
}

// Process city deletion
if (isset($_POST['action']) && $_POST['action'] == 'delete_city') {
    $id = (int)$_POST['id'];
    
    if (empty($id)) {
        $error = "Geçersiz şehir ID'si";
    } else {
        try {
            // Get city name for logging
            $stmt = $db->prepare("SELECT city FROM ziyaretci_sehirleri WHERE id = ?");
            $stmt->execute([$id]);
            $cityName = $stmt->fetchColumn();
            
            // Delete city
            $stmt = $db->prepare("DELETE FROM ziyaretci_sehirleri WHERE id = ?");
            $stmt->execute([$id]);
            
            // Log activity
            $stmt = $db->prepare("INSERT INTO activity_logs (admin_id, action, ip_address, details) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $_SESSION['admin_id'],
                'delete',
                $_SERVER['REMOTE_ADDR'],
                "Ziyaretçi şehri silindi: $cityName (ID: $id)"
            ]);
            
            $success = "Şehir başarıyla silindi";
        } catch (PDOException $e) {
            $error = "Şehir silinirken bir hata oluştu: " . $e->getMessage();
        }
    }
}

// Get statistics for dashboard
try {
    // Total cities
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM ziyaretci_sehirleri");
    $stmt->execute();
    $totalCities = $stmt->fetch()['total'];
    
    // Total visits
    $stmt = $db->prepare("SELECT SUM(visit_count) as total FROM ziyaretci_sehirleri");
    $stmt->execute();
    $totalVisits = $stmt->fetch()['total'] ?? 0;
    
    // Today's visits
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM ziyaretci_sehirleri WHERE DATE(last_visited) = CURDATE()");
    $stmt->execute();
    $todayVisits = $stmt->fetch()['total'];
    
    // Most visited city
    $stmt = $db->prepare("SELECT city, visit_count FROM ziyaretci_sehirleri ORDER BY visit_count DESC LIMIT 1");
    $stmt->execute();
    $mostVisitedCity = $stmt->fetch();
    
    // Average visits per city
    $stmt = $db->prepare("SELECT AVG(visit_count) as avg_visits FROM ziyaretci_sehirleri");
    $stmt->execute();
    $avgVisitsPerCity = $stmt->fetch()['avg_visits'] ?? 0;
    
    // Recent cities (last 7 days)
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM ziyaretci_sehirleri WHERE last_visited >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    $recentCities = $stmt->fetch()['total'];
    
} catch (PDOException $e) {
    $error = "İstatistikler yüklenirken bir hata oluştu: " . $e->getMessage();
    $totalCities = $totalVisits = $todayVisits = $avgVisitsPerCity = $recentCities = 0;
    $mostVisitedCity = ['city' => 'N/A', 'visit_count' => 0];
}

// Get all cities
$stmt = $db->prepare("SELECT * FROM ziyaretci_sehirleri ORDER BY visit_count DESC");
$stmt->execute();
$cities = $stmt->fetchAll();

ob_start();
?>

<style>
    :root {
        --primary-blue: #1e40af;
        --primary-blue-light: #3b82f6;
        --primary-blue-dark: #1e3a8a;
        --secondary-blue: #60a5fa;
        --accent-blue: #dbeafe;
        --success-green: #10b981;
        --warning-orange: #f59e0b;
        --danger-red: #ef4444;
        --info-cyan: #06b6d4;
        --dark-gray: #1f2937;
        --medium-gray: #374151;
        --light-gray: #6b7280;
        --white: #ffffff;
        --gradient-primary: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-blue-light) 100%);
        --gradient-secondary: linear-gradient(135deg, var(--secondary-blue) 0%, var(--accent-blue) 100%);
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --border-radius: 12px;
        --transition: all 0.3s ease;
    }

    body {
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        color: var(--dark-gray);
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        line-height: 1.6;
        margin: 0;
        padding: 0;
    }

    .dashboard-header {
        background: var(--gradient-primary);
        color: var(--white);
        padding: 2rem 0;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-lg);
    }

    .greeting {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .dashboard-subtitle {
        font-size: 1.1rem;
        opacity: 0.9;
        margin: 0;
    }

    .stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: var(--white);
        border-radius: var(--border-radius);
        padding: 1.5rem;
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
        position: relative;
        overflow: hidden;
        transition: var(--transition);
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }

    .stat-card::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
    }

    .stat-card.cities::after { background: var(--gradient-primary); }
    .stat-card.visits::after { background: linear-gradient(135deg, var(--success-green) 0%, #34d399 100%); }
    .stat-card.today::after { background: linear-gradient(135deg, var(--warning-orange) 0%, #fbbf24 100%); }
    .stat-card.most::after { background: linear-gradient(135deg, var(--info-cyan) 0%, #22d3ee 100%); }
    .stat-card.avg::after { background: linear-gradient(135deg, var(--danger-red) 0%, #f87171 100%); }
    .stat-card.recent::after { background: linear-gradient(135deg, var(--primary-blue-light) 0%, var(--secondary-blue) 100%); }

    .stat-card .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
        background: var(--gradient-secondary);
        color: var(--primary-blue);
    }

    .stat-card .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--dark-gray);
        margin-bottom: 0.5rem;
    }

    .stat-card .stat-label {
        color: var(--light-gray);
        font-size: 0.9rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-card .stat-subtitle {
        color: var(--light-gray);
        font-size: 0.8rem;
        margin-top: 0.5rem;
    }

    .visitor-cities-card {
        background: var(--white);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-md);
        border: 1px solid #e5e7eb;
        overflow: hidden;
        position: relative;
    }

    .visitor-cities-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--gradient-primary);
    }

    .visitor-cities-header {
        background: var(--white);
        padding: 1.5rem 2rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .visitor-cities-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--dark-gray);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .visitor-cities-body {
        padding: 2rem;
    }

    .table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-bottom: 0;
    }

    .table th {
        background: #f8fafc;
        color: var(--dark-gray);
        font-weight: 600;
        padding: 1rem;
        border-bottom: 2px solid #e5e7eb;
        text-align: left;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .table td {
        padding: 1rem;
        border-bottom: 1px solid #f3f4f6;
        vertical-align: middle;
    }

    .table tbody tr:hover {
        background: #f8fafc;
    }

    .city-badge {
        display: inline-block;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        text-align: center;
        background: rgba(59, 130, 246, 0.1);
        color: var(--primary-blue);
    }

    .visit-count {
        font-weight: 700;
        color: var(--success-green);
    }

    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        transition: var(--transition);
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-primary {
        background: var(--gradient-primary);
        color: var(--white);
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .btn-success {
        background: linear-gradient(135deg, var(--success-green) 0%, #34d399 100%);
        color: var(--white);
    }

    .btn-danger {
        background: linear-gradient(135deg, var(--danger-red) 0%, #f87171 100%);
        color: var(--white);
    }

    .btn-warning {
        background: linear-gradient(135deg, var(--warning-orange) 0%, #fbbf24 100%);
        color: var(--white);
    }

    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }

    .text-muted {
        color: var(--light-gray) !important;
    }

    .text-success {
        color: var(--success-green) !important;
    }

    .text-danger {
        color: var(--danger-red) !important;
    }

    .text-warning {
        color: var(--warning-orange) !important;
    }

    .text-info {
        color: var(--info-cyan) !important;
    }

    .fw-bold {
        font-weight: 700;
    }

    .small {
        font-size: 0.875rem;
    }

    .animate-fade-in {
        animation: fadeIn 0.5s ease-in-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Modal styles */
    .modal-content {
        border-radius: var(--border-radius);
        border: none;
        box-shadow: var(--shadow-lg);
    }

    .modal-header {
        background: var(--gradient-primary);
        color: var(--white);
        border-bottom: none;
        border-radius: var(--border-radius) var(--border-radius) 0 0;
    }

    .modal-title {
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-close-white {
        filter: invert(1) grayscale(100%) brightness(200%);
    }

    .form-label {
        color: var(--dark-gray);
        font-weight: 600;
        margin-bottom: 0.5rem;
        display: block;
    }

    .form-control {
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
        transition: var(--transition);
        background: var(--white);
    }

    .form-control:focus {
        border-color: var(--primary-blue-light);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        outline: none;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .dashboard-header {
            padding: 1.5rem 0;
        }

        .greeting {
            font-size: 1.5rem;
        }

        .stat-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .visitor-cities-body {
            padding: 1rem;
        }

        .table-responsive {
            font-size: 0.85rem;
        }

        .table th, .table td {
            padding: 0.75rem 0.5rem;
        }
    }
</style>

<div class="dashboard-header">
    <div class="container-fluid">
        <div class="greeting">
            <i class="bi bi-geo-alt"></i>
            Ziyaretçi Şehirleri
        </div>
        <div class="dashboard-subtitle">
            Ziyaretçi şehirlerini yönetin ve istatistikleri takip edin
        </div>
    </div>
</div>

<div class="container-fluid">
    <?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <?php echo $error; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>
        <?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Statistics Grid -->
    <div class="stat-grid">
        <div class="stat-card visits">
            <div class="stat-icon">
                <i class="bi bi-people"></i>
            </div>
            <div class="stat-value"><?php echo number_format($totalVisits); ?></div>
            <div class="stat-label">Toplam Ziyaret</div>
        </div>
        
        <div class="stat-card today">
            <div class="stat-icon">
                <i class="bi bi-calendar-day"></i>
            </div>
            <div class="stat-value"><?php echo number_format($todayVisits); ?></div>
            <div class="stat-label">Bugün Ziyaret</div>
        </div>
        
        <div class="stat-card most">
            <div class="stat-icon">
                <i class="bi bi-star"></i>
            </div>
            <div class="stat-value"><?php echo number_format($mostVisitedCity['visit_count']); ?></div>
            <div class="stat-label">En Çok Ziyaret</div>
            <div class="stat-subtitle"><?php echo htmlspecialchars($mostVisitedCity['city']); ?></div>
        </div>
        
        <div class="stat-card avg">
            <div class="stat-icon">
                <i class="bi bi-graph-up"></i>
            </div>
            <div class="stat-value">%<?php echo number_format($avgVisitsPerCity, 1); ?></div>
            <div class="stat-label">Toplam Trafik</div>
        </div>
        
        <div class="stat-card recent">
            <div class="stat-icon">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="stat-value"><?php echo number_format($recentCities); ?></div>
            <div class="stat-label">Son 7 Gün</div>
        </div>
    </div>

    <!-- Visitor Cities Management -->
    <div class="visitor-cities-card">
        <div class="visitor-cities-header">
            <div class="visitor-cities-title">
                <i class="bi bi-table"></i>
                Ziyaretçi Şehirleri Yönetimi
            </div>
            <button type="button" class="btn btn-success" onclick="addCity()">
                <i class="bi bi-plus-lg"></i>
                Yeni Şehir Ekle
            </button>
        </div>
        <div class="visitor-cities-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="citiesTable">
                    <thead>
                        <tr>
                            <th><i class="bi bi-hash"></i> ID</th>
                            <th><i class="bi bi-geo-alt"></i> Şehir</th>
                            <th><i class="bi bi-people"></i> Ziyaret Sayısı</th>
                            <th><i class="bi bi-clock"></i> Son Ziyaret</th>
                            <th><i class="bi bi-gear"></i> İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cities)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                <i class="bi bi-inbox text-muted"></i>
                                <span class="text-muted">Henüz şehir kaydı yok.</span>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($cities as $city): ?>
                            <tr>
                                <td>
                                    <span class="fw-bold"><?php echo $city['id']; ?></span>
                                </td>
                                <td>
                                    <span class="city-badge">
                                        <?php echo htmlspecialchars($city['city']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="visit-count"><?php echo number_format($city['visit_count']); ?></span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('d.m.Y H:i', strtotime($city['last_visited'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-primary" onclick="editCity(<?php echo $city['id']; ?>, '<?php echo htmlspecialchars($city['city']); ?>', <?php echo $city['visit_count']; ?>)">
                                            <i class="bi bi-pencil"></i>
                                            Düzenle
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteCity(<?php echo $city['id']; ?>, '<?php echo htmlspecialchars($city['city']); ?>')">
                                            <i class="bi bi-trash"></i>
                                            Sil
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // DataTable initialization
    $('#citiesTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Turkish.json"
        },
        "order": [[2, "desc"]],
        "pageLength": 25,
        "responsive": true,
        "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
               '<"row"<"col-sm-12"tr>>' +
               '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        "lengthMenu": [[10, 25, 50, 100], [10, 25, 50, 100]],
        "columnDefs": [
            { "targets": -1, "orderable": false }
        ]
    });
});

function addCity() {
    Swal.fire({
        title: 'Yeni Şehir Ekle',
        html: `
            <div class="text-start">
                <div class="mb-3">
                    <label class="form-label">Şehir Adı</label>
                    <input type="text" class="form-control" id="add-city" placeholder="Şehir adını girin" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Ziyaret Sayısı</label>
                    <input type="number" class="form-control" id="add-visit-count" value="1" min="1" required>
                </div>
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ekle',
        cancelButtonText: 'İptal',
        preConfirm: () => {
            const city = document.getElementById('add-city').value;
            const visitCount = document.getElementById('add-visit-count').value;
            
            if (!city || !visitCount) {
                Swal.showValidationMessage('Lütfen tüm alanları doldurun');
                return false;
            }
            
            return { city: city, visitCount: visitCount };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const { city, visitCount } = result.value;
            
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="create_city">
                <input type="hidden" name="city" value="${city}">
                <input type="hidden" name="visit_count" value="${visitCount}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function editCity(id, city, visitCount) {
    Swal.fire({
        title: 'Şehir Düzenle',
        html: `
            <div class="text-start">
                <div class="mb-3">
                    <label class="form-label">Şehir Adı</label>
                    <input type="text" class="form-control" id="edit-city" value="${city}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Ziyaret Sayısı</label>
                    <input type="number" class="form-control" id="edit-visit-count" value="${visitCount}" min="1" required>
                </div>
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#3b82f6',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Kaydet',
        cancelButtonText: 'İptal',
        preConfirm: () => {
            const newCity = document.getElementById('edit-city').value;
            const newVisitCount = document.getElementById('edit-visit-count').value;
            
            if (!newCity || !newVisitCount) {
                Swal.showValidationMessage('Lütfen tüm alanları doldurun');
                return false;
            }
            
            return { city: newCity, visitCount: newVisitCount };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const { city: newCity, visitCount: newVisitCount } = result.value;
            
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="update_city">
                <input type="hidden" name="id" value="${id}">
                <input type="hidden" name="city" value="${newCity}">
                <input type="hidden" name="visit_count" value="${newVisitCount}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function deleteCity(id, city) {
    Swal.fire({
        title: 'Şehir Sil',
        html: `
            <div class="text-start">
                <p><strong>${city}</strong> şehrini silmek istediğinize emin misiniz?</p>
                <p class="text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Bu işlem geri alınamaz!</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Evet, sil!',
        cancelButtonText: 'İptal'
    }).then((result) => {
        if (result.isConfirmed) {
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_city">
                <input type="hidden" name="id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    });
}
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?> 