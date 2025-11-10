<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "Promosyon Menü Yönetimi";

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

    // Handle AJAX requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        header('Content-Type: application/json');
        
        switch ($_POST['action']) {
            case 'add_category':
                try {
                    $stmt = $db->prepare("INSERT INTO promotion_categories (name, status) VALUES (?, ?)");
                    $stmt->execute([$_POST['name'], $_POST['status']]);
                    
                    // Log activity
                    $stmt = $db->prepare("
                        INSERT INTO activity_logs (admin_id, action, description, created_at) 
                        VALUES (?, 'add_category', ?, NOW())
                    ");
                    $stmt->execute([$_SESSION['admin_id'], "Yeni promosyon kategorisi eklendi: {$_POST['name']}"]);
                    
                    echo json_encode(['success' => true, 'message' => 'Kategori başarıyla eklendi']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
                }
                exit();

            case 'update_category':
                try {
                    $stmt = $db->prepare("UPDATE promotion_categories SET name = ?, status = ? WHERE id = ?");
                    $stmt->execute([$_POST['name'], $_POST['status'], $_POST['id']]);
                    
                    // Log activity
                    $stmt = $db->prepare("
                        INSERT INTO activity_logs (admin_id, action, description, created_at) 
                        VALUES (?, 'update_category', ?, NOW())
                    ");
                    $stmt->execute([$_SESSION['admin_id'], "Promosyon kategorisi güncellendi: {$_POST['name']}"]);
                    
                    echo json_encode(['success' => true, 'message' => 'Kategori başarıyla güncellendi']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
                }
                exit();

            case 'delete_category':
                try {
                    // Check if category is being used
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM promotions WHERE category = (SELECT name FROM promotion_categories WHERE id = ?)");
                    $stmt->execute([$_POST['id']]);
                    $result = $stmt->fetch();
                    
                    if ($result['count'] > 0) {
                        echo json_encode(['success' => false, 'message' => 'Bu kategori kullanımda olduğu için silinemez']);
                        exit();
                    }
                    
                    $stmt = $db->prepare("DELETE FROM promotion_categories WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    
                    // Log activity
                    $stmt = $db->prepare("
                        INSERT INTO activity_logs (admin_id, action, description, created_at) 
                        VALUES (?, 'delete_category', ?, NOW())
                    ");
                    $stmt->execute([$_SESSION['admin_id'], "Promosyon kategorisi silindi: ID {$_POST['id']}"]);
                    
                    echo json_encode(['success' => true, 'message' => 'Kategori başarıyla silindi']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
                }
                exit();
        }
    }

    // Get statistics for dashboard
    try {
        // Total categories
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM promotion_categories");
        $stmt->execute();
        $totalCategories = $stmt->fetch()['total'];
        
        // Active categories
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM promotion_categories WHERE status = 1");
        $stmt->execute();
        $activeCategories = $stmt->fetch()['total'];
        
        // Total promotions
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM promotions");
        $stmt->execute();
        $totalPromotions = $stmt->fetch()['total'];
        
        // Active promotions
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM promotions WHERE status = 1");
        $stmt->execute();
        $activePromotions = $stmt->fetch()['total'];
        
        // Most used category
        $stmt = $db->prepare("
            SELECT pc.name, COUNT(p.id) as count 
            FROM promotion_categories pc 
            LEFT JOIN promotions p ON pc.name = p.category 
            GROUP BY pc.id, pc.name 
            ORDER BY count DESC 
            LIMIT 1
        ");
        $stmt->execute();
        $mostUsedCategory = $stmt->fetch();
        
        // Today's category usage
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT p.category) as total 
            FROM promotions p 
            WHERE DATE(p.created_at) = CURDATE()
        ");
        $stmt->execute();
        $todayCategoryUsage = $stmt->fetch()['total'];
        
    } catch (PDOException $e) {
        $totalCategories = $activeCategories = $totalPromotions = $activePromotions = $todayCategoryUsage = 0;
        $mostUsedCategory = ['name' => 'N/A', 'count' => 0];
    }

    // Get all categories
    $stmt = $db->query("SELECT * FROM promotion_categories ORDER BY id DESC");
    $categories = $stmt->fetchAll();

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

        .stat-card.categories::after { background: var(--gradient-primary); }
        .stat-card.active-categories::after { background: linear-gradient(135deg, var(--success-green) 0%, #34d399 100%); }
        .stat-card.promotions::after { background: linear-gradient(135deg, var(--warning-orange) 0%, #fbbf24 100%); }
        .stat-card.active-promotions::after { background: linear-gradient(135deg, var(--info-cyan) 0%, #22d3ee 100%); }
        .stat-card.most-used::after { background: linear-gradient(135deg, var(--danger-red) 0%, #f87171 100%); }
        .stat-card.today-usage::after { background: linear-gradient(135deg, var(--primary-blue-light) 0%, var(--secondary-blue) 100%); }

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

        .promotion-menu-card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            border: 1px solid #e5e7eb;
            overflow: hidden;
            position: relative;
        }

        .promotion-menu-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }

        .promotion-menu-header {
            background: var(--white);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .promotion-menu-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .promotion-menu-body {
            padding: 2rem;
        }

        .form-section {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid #e5e7eb;
            margin-bottom: 2rem;
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
            background: #0f172a;
        }

        .form-control:focus {
            border-color: var(--primary-blue-light);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .badge {
            font-weight: 600;
            font-size: 0.8rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
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

            .promotion-menu-body {
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
                <i class="bi bi-tags"></i>
                Promosyon Menü Yönetimi
            </div>
            <div class="dashboard-subtitle">
                Promosyon kategorilerini yönetin ve istatistikleri takip edin
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- Statistics Grid -->
        <div class="stat-grid">

            <div class="stat-card active-categories">
                <div class="stat-icon">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo number_format($activeCategories); ?></div>
                <div class="stat-label">Aktif Kategori</div>
            </div>
            
            <div class="stat-card promotions">
                <div class="stat-icon">
                    <i class="bi bi-megaphone"></i>
                </div>
                <div class="stat-value"><?php echo number_format($totalPromotions); ?></div>
                <div class="stat-label">Toplam Promosyon</div>
            </div>
            
            <div class="stat-card active-promotions">
                <div class="stat-icon">
                    <i class="bi bi-star"></i>
                </div>
                <div class="stat-value"><?php echo number_format($activePromotions); ?></div>
                <div class="stat-label">Aktif Promosyon</div>
            </div>
            
            <div class="stat-card most-used">
                <div class="stat-icon">
                    <i class="bi bi-trophy"></i>
                </div>
                <div class="stat-value"><?php echo number_format($mostUsedCategory['count']); ?></div>
                <div class="stat-label">En Çok Kullanılan</div>
                <div class="stat-subtitle"><?php echo htmlspecialchars($mostUsedCategory['name']); ?></div>
            </div>
            
            <div class="stat-card today-usage">
                <div class="stat-icon">
                    <i class="bi bi-calendar-day"></i>
                </div>
                <div class="stat-value"><?php echo number_format($todayCategoryUsage); ?></div>
                <div class="stat-label">Bugünkü Kullanım</div>
            </div>
        </div>

        <!-- Promotion Menu Management -->
        <div class="promotion-menu-card">
            <div class="promotion-menu-header">
                <div class="promotion-menu-title">
                    <i class="bi bi-gear"></i>
                    Kategori Yönetimi
                </div>
            </div>
            <div class="promotion-menu-body">

        <div class="row">
            <!-- Kategori Ekleme/Düzenleme Formu -->
            <div class="col-md-4">
                <div class="form-section">
                    <h5 class="mb-4">Kategori Ekle/Düzenle</h5>
                    <form id="categoryForm">
                        <input type="hidden" id="category_id" name="id">
                        
                        <div class="mb-3">
                            <label class="form-label">Kategori Adı</label>
                            <input type="text" class="form-control" id="category_name" name="name" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Durum</label>
                            <select class="form-control" id="category_status" name="status">
                                <option value="1">Aktif</option>
                                <option value="0">Pasif</option>
                            </select>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Kaydet
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                <i class="fas fa-undo me-1"></i> Formu Temizle
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Kategori Listesi -->
            <div class="col-md-8">
                <div class="table-responsive">
                    <table class="table table-bordered" id="categoriesTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Kategori Adı</th>
                                <th>Durum</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                            <tr>
                                <td><?php echo $category['id']; ?></td>
                                <td><?php echo htmlspecialchars($category['name']); ?></td>
                                <td>
                                    <span class="badge <?php echo $category['status'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $category['status'] ? 'Aktif' : 'Pasif'; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteCategory(<?php echo $category['id']; ?>)">
                                        <i class="fas fa-trash"></i>
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

    <!-- Add required scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

    <script>
    let editMode = false;

    function resetForm() {
        editMode = false;
        $('#categoryForm')[0].reset();
        $('#category_id').val('');
    }

    function editCategory(category) {
        editMode = true;
        
        $('#category_id').val(category.id);
        $('#category_name').val(category.name);
        $('#category_status').val(category.status);
        
        $('html, body').animate({
            scrollTop: $("#categoryForm").offset().top - 100
        }, 500);
    }

    function deleteCategory(id) {
        Swal.fire({
            title: 'Emin misiniz?',
            text: "Bu kategori silinecek. Bu işlem geri alınamaz!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Evet, sil!',
            cancelButtonText: 'İptal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'promotion_menu.php',
                    type: 'POST',
                    data: {
                        action: 'delete_category',
                        id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Başarılı',
                                text: response.message,
                                showConfirmButton: false,
                                timer: 1500
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Hata',
                                text: response.message
                            });
                        }
                    }
                });
            }
        });
    }

    // Form submit handler
    $(document).ready(function() {
        $('#categoryForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = {
                action: editMode ? 'update_category' : 'add_category',
                id: $('#category_id').val(),
                name: $('#category_name').val(),
                status: $('#category_status').val()
            };
            
            console.log('Gönderilen veriler:', formData); // Debug için
            
            Swal.fire({
                title: 'İşleniyor...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: 'promotion_menu.php',
                type: 'POST',
                data: formData,
                success: function(response) {
                    console.log('Sunucu yanıtı:', response); // Debug için
                    
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Başarılı',
                            text: response.message,
                            showConfirmButton: false,
                            timer: 1500
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Hata',
                            text: response.message
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX hatası:', error); // Debug için
                    Swal.fire({
                        icon: 'error',
                        title: 'Hata',
                        text: 'İşlem sırasında bir hata oluştu: ' + error
                    });
                }
            });
        });

        // DataTable initialization
        $('#categoriesTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Turkish.json"
            },
            "order": [[0, "desc"]],
            "pageLength": 25,
            "responsive": true
        });
    });
    </script>

    <?php
    $pageContent = ob_get_clean();
    include 'includes/layout.php';
    ?> 