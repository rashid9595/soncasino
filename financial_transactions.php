<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

// Set page title
$pageTitle = "Finansal İşlemler Yönetimi";

// Check if user is logged in and 2FA is verified
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['2fa_verified'])) {
    header("Location: 2fa.php");
    exit();
}

// Check if user has permission to view this page
if (!isset($_SESSION['role_id'])) {
    $stmt = $db->prepare("SELECT role_id FROM administrators WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $userData = $stmt->fetch();
    $_SESSION['role_id'] = $userData['role_id'];
}

// If user is admin (role_id = 1), grant full access
$isAdmin = ($_SESSION['role_id'] == 1);

if (!$isAdmin) {
    $stmt = $db->prepare("
        SELECT ap.* 
        FROM admin_permissions ap 
        WHERE ap.role_id = ? AND ap.menu_item = 'financial_transactions' AND ap.can_view = 1
    ");
    $stmt->execute([$_SESSION['role_id']]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = 'Bu sayfayı görüntüleme izniniz yok.';
        header("Location: index.php");
        exit();
    }
}

// Get statistics for dashboard
try {
    // Total withdrawals count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM paracek");
    $stmt->execute();
    $totalWithdrawals = $stmt->fetch()['total'];
    
    // Pending withdrawals count
    $stmt = $db->prepare("SELECT COUNT(*) as pending FROM paracek WHERE durum = 0");
    $stmt->execute();
    $pendingWithdrawals = $stmt->fetch()['pending'];
    
    // Approved withdrawals count
    $stmt = $db->prepare("SELECT COUNT(*) as approved FROM paracek WHERE durum = 1");
    $stmt->execute();
    $approvedWithdrawals = $stmt->fetch()['approved'];
    
    // Rejected withdrawals count
    $stmt = $db->prepare("SELECT COUNT(*) as rejected FROM paracek WHERE durum = 2");
    $stmt->execute();
    $rejectedWithdrawals = $stmt->fetch()['rejected'];
    
    // Total withdrawals amount
    $stmt = $db->prepare("SELECT SUM(miktar) as total_amount FROM paracek WHERE durum = 1");
    $stmt->execute();
    $totalWithdrawalAmount = $stmt->fetch()['total_amount'] ?? 0;
    
    // Today's withdrawals count
    $stmt = $db->prepare("SELECT COUNT(*) as today FROM paracek WHERE DATE(tarih) = CURDATE()");
    $stmt->execute();
    $todayWithdrawals = $stmt->fetch()['today'];
    
} catch (PDOException $e) {
    $error = "İstatistikler yüklenirken bir hata oluştu: " . $e->getMessage();
    $totalWithdrawals = $pendingWithdrawals = $approvedWithdrawals = $rejectedWithdrawals = $totalWithdrawalAmount = $todayWithdrawals = 0;
}

// Handle form submissions for withdrawal approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $transactionId = (int)$_POST['id'];
        $action = $_POST['action'];
        
        try {
            // Get transaction details
            $stmt = $db->prepare("SELECT * FROM paracek WHERE id = ?");
            $stmt->execute([$transactionId]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$transaction) {
                throw new Exception("İşlem bulunamadı.");
            }
            
            if ($action === 'approve') {
                // Update status to approved
                $stmt = $db->prepare("UPDATE paracek SET durum = 1 WHERE id = ?");
                $stmt->execute([$transactionId]);
                $successMessage = "İşlem başarıyla onaylandı!";
            } elseif ($action === 'reject') {
                // Update status to rejected
                $stmt = $db->prepare("UPDATE paracek SET durum = 2 WHERE id = ?");
                $stmt->execute([$transactionId]);
                $successMessage = "İşlem başarıyla reddedildi!";
            }
        } catch (Exception $e) {
            $errorMessage = "İşlem sırasında bir hata oluştu: " . $e->getMessage();
        }
    }
}

// Get all transactions
try {
    $stmt = $db->prepare("
        SELECT p.*, k.firstName, k.surname 
        FROM paracek p 
        LEFT JOIN kullanicilar k ON p.user_id = k.id 
        ORDER BY p.id DESC
    ");
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add user full name to each transaction
    foreach ($transactions as &$transaction) {
        $transaction['uye'] = $transaction['firstName'] . ' ' . $transaction['surname'];
    }
} catch (PDOException $e) {
    $errorMessage = "İşlemler yüklenirken bir hata oluştu: " . $e->getMessage();
    $transactions = [];
}

ob_start();
?>

<style>
    :root {
        /* Corporate Blue Color Palette */
        --primary-blue: #1e40af;
        --primary-blue-light: #3b82f6;
        --primary-blue-dark: #1e3a8a;
        --secondary-blue: #60a5fa;
        --accent-blue: #93c5fd;
        --light-blue: #dbeafe;
        --ultra-light-blue: #eff6ff;
        
        /* Corporate Whites and Grays */
        --white: #ffffff;
        --light-gray: #f8fafc;
        --medium-gray: #e2e8f0;
        --dark-gray: #64748b;
        --text-gray: #475569;
        
        /* Status Colors */
        --success-green: #059669;
        --error-red: #dc2626;
        --warning-orange: #d97706;
        --info-blue: var(--primary-blue-light);
        
        /* Corporate Gradients - Dark to Light Blue Theme */
        --primary-gradient: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
        --secondary-gradient: linear-gradient(135deg, #1e40af 0%, #60a5fa 100%);
        --tertiary-gradient: linear-gradient(135deg, #1e3a8a 0%, #93c5fd 100%);
        --quaternary-gradient: linear-gradient(135deg, #1e40af 0%, #dbeafe 100%);
        --light-gradient: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
        --corporate-gradient: linear-gradient(135deg, #1e3a8a 0%, #60a5fa 50%, #dbeafe 100%);
        
        /* Corporate Theme */
        --bg-primary: var(--light-gray);
        --bg-secondary: var(--ultra-light-blue);
        --card-bg: var(--white);
        --card-border: var(--medium-gray);
        --text-primary: var(--text-gray);
        --text-secondary: var(--dark-gray);
        --text-heading: var(--primary-blue-dark);
        
        /* Corporate Shadows */
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
        --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
        --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);
        --shadow-xl: 0 20px 25px rgba(0, 0, 0, 0.1);
        --shadow-blue: 0 0 20px rgba(30, 64, 175, 0.2);
        --shadow-success: 0 0 20px rgba(5, 150, 105, 0.2);
        --shadow-warning: 0 0 20px rgba(217, 119, 6, 0.2);
        --shadow-danger: 0 0 20px rgba(220, 38, 38, 0.2);
        
        /* Layout */
        --border-radius: 8px;
        --border-radius-lg: 12px;
        --border-radius-sm: 6px;
    }

    body {
        background: var(--bg-primary);
        min-height: 100vh;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        color: var(--text-primary);
        line-height: 1.6;
    }

    @keyframes fadeIn {
        from { 
            opacity: 0; 
            transform: translateY(20px);
        }
        to { 
            opacity: 1; 
            transform: translateY(0);
        }
    }

    .dashboard-header {
        margin-bottom: 2rem;
        position: relative;
        padding: 2rem;
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        border: 1px solid var(--card-border);
        box-shadow: var(--shadow-md);
        border-top: 4px solid var(--primary-blue-dark);
    }
    
    .greeting {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.8rem;
        color: var(--text-heading);
        position: relative;
        font-family: 'Inter', sans-serif;
        letter-spacing: -0.5px;
    }
    
    .dashboard-subtitle {
        color: var(--text-secondary);
        font-size: 1rem;
        margin-bottom: 0;
        font-weight: 400;
    }

    .stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: var(--card-bg);
        border-radius: var(--border-radius-lg);
        padding: 1.5rem;
        box-shadow: var(--shadow-md);
        position: relative;
        border: 1px solid var(--card-border);
    }
    
    .stat-card::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 3px;
        border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
    }
    
    .stat-card.total::after {
        background: var(--primary-gradient);
    }
    
    .stat-card.pending::after {
        background: var(--secondary-gradient);
    }
    
    .stat-card.approved::after {
        background: var(--tertiary-gradient);
    }

    .stat-card.rejected::after {
        background: var(--quaternary-gradient);
    }

    .stat-card.amount::after {
        background: var(--primary-gradient);
    }

    .stat-card.today::after {
        background: var(--secondary-gradient);
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
        position: relative;
        color: white;
    }
    
    .stat-card.total .stat-icon {
        background: var(--primary-gradient);
        box-shadow: var(--shadow-sm);
    }
    
    .stat-card.pending .stat-icon {
        background: var(--secondary-gradient);
        box-shadow: var(--shadow-sm);
    }
    
    .stat-card.approved .stat-icon {
        background: var(--tertiary-gradient);
        box-shadow: var(--shadow-sm);
    }

    .stat-card.rejected .stat-icon {
        background: var(--quaternary-gradient);
        box-shadow: var(--shadow-sm);
    }

    .stat-card.amount .stat-icon {
        background: var(--primary-gradient);
        box-shadow: var(--shadow-sm);
    }

    .stat-card.today .stat-icon {
        background: var(--secondary-gradient);
        box-shadow: var(--shadow-sm);
    }
    
    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: var(--text-heading);
        font-family: 'Inter', monospace;
    }
    
    .stat-title {
        font-size: 0.9rem;
        color: var(--text-secondary);
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .transactions-card {
        background: var(--card-bg);
        border: 1px solid var(--card-border);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-md);
        overflow: hidden;
        margin-bottom: 2rem;
        position: relative;
    }

    .transactions-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--primary-gradient);
        border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
    }

    .transactions-header {
        background: var(--ultra-light-blue);
        padding: 1.5rem;
        border-bottom: 1px solid var(--card-border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .transactions-header h6 {
        margin: 0;
        color: var(--text-heading);
        font-weight: 600;
        display: flex;
        align-items: center;
    }

    .transactions-header h6 i {
        color: var(--primary-blue-light);
        margin-right: 0.5rem;
    }

    .transactions-body {
        padding: 2rem;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 0;
    }

    .table th {
        background: var(--bg-secondary);
        color: var(--text-heading);
        font-weight: 600;
        padding: 1rem;
        border-bottom: 2px solid var(--card-border);
        text-align: left;
    }

    .table td {
        padding: 1rem;
        border-bottom: 1px solid var(--card-border);
        vertical-align: middle;
    }

    .table tbody tr:hover {
        background: var(--bg-secondary);
    }

    .status-badge {
        padding: 0.35em 0.65em;
        font-weight: 600;
        border-radius: 0.5rem;
        font-size: 0.85em;
    }
    
    .status-pending {
        background-color: var(--warning-orange);
        color: var(--white);
    }
    
    .status-approved {
        background-color: var(--success-green);
        color: var(--white);
    }
    
    .status-rejected {
        background-color: var(--error-red);
        color: var(--white);
    }

    .btn {
        padding: 0.5rem 1rem;
        border-radius: var(--border-radius);
        font-weight: 600;
        font-size: 0.85rem;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        margin: 0.25rem;
    }

    .btn-approve {
        background: var(--success-green);
        color: var(--white);
    }

    .btn-approve:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-success);
        color: var(--white);
    }

    .btn-reject {
        background: var(--error-red);
        color: var(--white);
    }

    .btn-reject:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-danger);
        color: var(--white);
    }

    .alert {
        border-radius: var(--border-radius);
        border: none;
        padding: 1rem 1.5rem;
        margin-bottom: 1rem;
        position: relative;
        animation: fadeIn 0.5s ease;
    }

    .alert-success {
        background: var(--light-blue);
        border-left: 4px solid var(--success-green);
        color: var(--primary-blue-dark);
    }

    .alert-error {
        background: var(--light-blue);
        border-left: 4px solid var(--error-red);
        color: var(--primary-blue-dark);
    }

    .text-muted {
        color: var(--text-secondary) !important;
    }

    .animate-fade-in {
        animation: fadeIn 0.8s ease forwards;
    }

    /* Container responsive design */
    @media (max-width: 768px) {
        .dashboard-header {
            padding: 1.5rem;
        }
        
        .greeting {
            font-size: 1.5rem;
        }
        
        .stat-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .transactions-body {
            padding: 1rem;
        }
        
        .table {
            font-size: 0.9rem;
        }
        
        .table th,
        .table td {
            padding: 0.75rem 0.5rem;
        }
    }
</style>

<div class="dashboard-header animate-fade-in">
    <h1 class="greeting">
        <i class="bi bi-cash-stack me-2"></i>
        Finansal İşlemler Yönetimi
    </h1>
    <p class="dashboard-subtitle">Para çekme işlemlerini yönetin ve onaylayın.</p>
</div>

<div class="stat-grid animate-fade-in" style="animation-delay: 0.1s">
    <div class="stat-card total">
        <div class="stat-icon">
            <i class="bi bi-list-ul"></i>
        </div>
        <div class="stat-number"><?php echo number_format($totalWithdrawals); ?></div>
        <div class="stat-title">Toplam İşlem</div>
    </div>
    
    <div class="stat-card pending">
        <div class="stat-icon">
            <i class="bi bi-clock"></i>
        </div>
        <div class="stat-number"><?php echo number_format($pendingWithdrawals); ?></div>
        <div class="stat-title">Bekleyen İşlem</div>
    </div>
    
    <div class="stat-card approved">
        <div class="stat-icon">
            <i class="bi bi-check-circle"></i>
        </div>
        <div class="stat-number"><?php echo number_format($approvedWithdrawals); ?></div>
        <div class="stat-title">Onaylanan İşlem</div>
    </div>
    
    <div class="stat-card rejected">
        <div class="stat-icon">
            <i class="bi bi-x-circle"></i>
        </div>
        <div class="stat-number"><?php echo number_format($rejectedWithdrawals); ?></div>
        <div class="stat-title">Reddedilen İşlem</div>
    </div>
    
    <div class="stat-card amount">
        <div class="stat-icon">
            <i class="bi bi-currency-exchange"></i>
        </div>
        <div class="stat-number"><?php echo number_format($totalWithdrawalAmount, 2); ?> ₺</div>
        <div class="stat-title">Toplam Onaylanan Tutar</div>
    </div>
</div>

<div class="transactions-card animate-fade-in" style="animation-delay: 0.2s">
    <div class="transactions-header">
        <h6><i class="bi bi-cash-stack"></i>Para Çekme İşlemleri</h6>
        <div class="text-muted">
            <i class="bi bi-info-circle me-1"></i>
          Yapılan işlem geri alınamaz.
        </div>
    </div>
    <div class="transactions-body">
        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i>
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-error">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th><i class="bi bi-hash me-1"></i>ID</th>
                        <th><i class="bi bi-person me-1"></i>Kullanıcı</th>
                        <th><i class="bi bi-bank me-1"></i>Banka/Yöntem</th>
                        <th><i class="bi bi-currency-exchange me-1"></i>Miktar</th>
                        <th><i class="bi bi-credit-card me-1"></i>IBAN</th>
                        <th><i class="bi bi-calendar me-1"></i>Tarih</th>
                        <th><i class="bi bi-info-circle me-1"></i>Durum</th>
                        <th><i class="bi bi-gear me-1"></i>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($transactions)): ?>
                        <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td><strong>#<?php echo htmlspecialchars($transaction['id']); ?></strong></td>
                            <td>
                                <div><strong>ID: <?php echo htmlspecialchars($transaction['user_id']); ?></strong></div>
                                <div class="text-muted"><?php echo htmlspecialchars($transaction['uye']); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($transaction['banka']); ?></td>
                            <td><strong><?php echo number_format($transaction['miktar'], 2); ?> ₺</strong></td>
                            <td><code><?php echo htmlspecialchars($transaction['iban'] ?? 'Belirtilmemiş'); ?></code></td>
                            <td><?php echo htmlspecialchars($transaction['tarih'] ?? 'Belirtilmemiş'); ?></td>
                            <td>
                                <?php 
                                switch($transaction['durum']) {
                                    case 0: 
                                        echo '<span class="status-badge status-pending">Beklemede</span>'; 
                                        break;
                                    case 1: 
                                        echo '<span class="status-badge status-approved">Onaylandı</span>'; 
                                        break;
                                    case 2: 
                                        echo '<span class="status-badge status-rejected">Reddedildi</span>'; 
                                        break;
                                    default: 
                                        echo '<span class="status-badge">Bilinmiyor</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($transaction['durum'] == 0): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="id" value="<?php echo $transaction['id']; ?>">
                                        <button type="submit" name="action" value="approve" class="btn btn-approve" 
                                                onclick="return confirm('Bu işlemi onaylamak istediğinizden emin misiniz?')">
                                            <i class="bi bi-check"></i>Onayla
                                        </button>
                                        <button type="submit" name="action" value="reject" class="btn btn-reject"
                                                onclick="return confirm('Bu işlemi reddetmek istediğinizden emin misiniz?')">
                                            <i class="bi bi-x"></i>Reddet
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted"><i class="bi bi-check-circle"></i> işlem sonuçlandırıldı</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                <i class="bi bi-inbox display-4"></i>
                                <div class="mt-2">Henüz para çekme talebi bulunmuyor.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>
