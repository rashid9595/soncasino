<?php
$pageTitle = "Tema Test Sayfası";
$pageContent = '
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-palette"></i> Tema Sistemi Test Sayfası</h5>
                </div>
                <div class="card-body">
                    <p>Bu sayfa yeni tema sistemini test etmek için oluşturulmuştur. Sidebar\'daki tema seçiciyi kullanarak farklı temaları deneyebilirsiniz.</p>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6>Test Kartı 1</h6>
                                </div>
                                <div class="card-body">
                                    <p>Bu bir test kartıdır. Tema değişikliklerini görmek için kullanılır.</p>
                                    <button class="btn btn-primary">Test Butonu</button>
                                    <button class="btn btn-success">Başarı</button>
                                    <button class="btn btn-danger">Hata</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6>Test Kartı 2</h6>
                                </div>
                                <div class="card-body">
                                    <p>Bu da bir test kartıdır. Farklı renkler ve efektler test edilir.</p>
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle"></i>
                                        Bu bir bilgi mesajıdır.
                                    </div>
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        Bu bir uyarı mesajıdır.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h6>Test Tablosu</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Ad</th>
                                                <th>Durum</th>
                                                <th>İşlemler</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>1</td>
                                                <td>Test Kullanıcı 1</td>
                                                <td><span class="badge bg-success">Aktif</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary">Düzenle</button>
                                                    <button class="btn btn-sm btn-danger">Sil</button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>2</td>
                                                <td>Test Kullanıcı 2</td>
                                                <td><span class="badge bg-warning">Beklemede</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary">Düzenle</button>
                                                    <button class="btn btn-sm btn-danger">Sil</button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>3</td>
                                                <td>Test Kullanıcı 3</td>
                                                <td><span class="badge bg-danger">Pasif</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary">Düzenle</button>
                                                    <button class="btn btn-sm btn-danger">Sil</button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h6>Form Testi</h6>
                                </div>
                                <div class="card-body">
                                    <form>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="testInput1" class="form-label">Test Input 1</label>
                                                    <input type="text" class="form-control" id="testInput1" placeholder="Test değeri girin">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="testSelect1" class="form-label">Test Select</label>
                                                    <select class="form-select" id="testSelect1">
                                                        <option>Seçenek 1</option>
                                                        <option>Seçenek 2</option>
                                                        <option>Seçenek 3</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="testTextarea" class="form-label">Test Textarea</label>
                                            <textarea class="form-control" id="testTextarea" rows="3" placeholder="Test metni girin"></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Gönder</button>
                                        <button type="reset" class="btn btn-secondary">Sıfırla</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
';

include 'includes/layout.php';
?>

