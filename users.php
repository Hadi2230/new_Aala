<?php
session_start();
require_once __DIR__ . '/config.php';
checkPermission('ادمین');

$success = null;
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    verifyCsrfToken();

    if (isset($_POST['add_user'])) {
        $username   = trim($_POST['username'] ?? '');
        $password   = (string)($_POST['password'] ?? '');
        $role       = (string)($_POST['role'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $full_name  = trim($_POST['full_name'] ?? '');
        $errors     = [];

        if ($username === '' || $password === '' || $role === '') $errors[] = 'فیلدهای ضروری را پر کنید.';
        if (mb_strlen($password) < 6) $errors[] = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'فرمت ایمیل نامعتبر است.';

        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        if ($stmt->fetch()) $errors[] = 'نام کاربری تکراری است.';

        if (empty($errors)) {
            try {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (username, password, role, email, full_name, is_active) VALUES (?, ?, ?, ?, ?, 1)');
                $stmt->execute([$username, $password_hash, $role, $email, $full_name]);
                $_SESSION['success'] = 'کاربر با موفقیت افزوده شد!';
                header('Location: users.php'); exit();
            } catch (Throwable $e) { $error = 'خطا در افزودن کاربر: ' . $e->getMessage(); }
        } else { $error = implode(' | ', $errors); }
    }

    if (isset($_POST['edit_user'])) {
        $user_id   = (int)($_POST['user_id'] ?? 0);
        $username  = trim($_POST['username'] ?? '');
        $role      = (string)($_POST['role'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $password  = (string)($_POST['password'] ?? '');
        $errors    = [];

        if ($username === '' || $role === '') $errors[] = 'نام کاربری و نقش الزامی است.';
        if ($password !== '' && mb_strlen($password) < 6) $errors[] = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'فرمت ایمیل نامعتبر است.';

        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
        $stmt->execute([$username, $user_id]);
        if ($stmt->fetch()) $errors[] = 'نام کاربری تکراری است.';

        if (empty($errors)) {
            try {
                if ($password !== '') {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('UPDATE users SET username=?, password=?, role=?, email=?, full_name=? WHERE id=?');
                    $stmt->execute([$username, $password_hash, $role, $email, $full_name, $user_id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET username=?, role=?, email=?, full_name=? WHERE id=?');
                    $stmt->execute([$username, $role, $email, $full_name, $user_id]);
                }
                $_SESSION['success'] = 'کاربر با موفقیت ویرایش شد!';
                header('Location: users.php'); exit();
            } catch (Throwable $e) { $error = 'خطا در ویرایش کاربر: ' . $e->getMessage(); }
        } else { $error = implode(' | ', $errors); }
    }

    if (isset($_POST['delete_user'])) {
        $delete_id = (int)($_POST['delete_id'] ?? 0);
        if ($delete_id > 0 && $delete_id !== (int)($_SESSION['user_id'] ?? 0)) {
            try {
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
                if ($stmt->execute([$delete_id])) $_SESSION['success'] = 'کاربر با موفقیت حذف شد!';
                else $_SESSION['error'] = 'خطا در حذف کاربر.';
            } catch (Throwable $e) { $_SESSION['error'] = 'خطا در حذف کاربر: ' . $e->getMessage(); }
        } else { $_SESSION['error'] = 'شما نمی‌توانید حساب خودتان را حذف کنید.'; }
        header('Location: users.php'); exit();
    }
}

$search   = isset($_GET['search']) ? trim($_GET['search']) : '';
$page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;
$offset   = ($page - 1) * $per_page;

if ($search !== '') {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE username LIKE ? OR full_name LIKE ? OR email LIKE ? ORDER BY id DESC LIMIT :limit OFFSET :offset');
    $term = "%{$search}%";
    $stmt->bindValue(':limit',  $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,   PDO::PARAM_INT);
    $stmt->execute([$term, $term, $term]);

    $count_stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username LIKE ? OR full_name LIKE ? OR email LIKE ?');
    $count_stmt->execute([$term, $term, $term]);
    $total_users = (int)$count_stmt->fetchColumn();
} else {
    $stmt = $pdo->prepare('SELECT * FROM users ORDER BY id DESC LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':limit',  $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,   PDO::PARAM_INT);
    $stmt->execute();
    $total_users = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
}

$users       = $stmt->fetchAll();
$total_pages = max(1, (int)ceil($total_users / $per_page));

if (isset($_SESSION['success'])) { $success = $_SESSION['success']; unset($_SESSION['success']); }
if (isset($_SESSION['error']))   { $error   = $_SESSION['error'];   unset($_SESSION['error']); }
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>مدیریت کاربران - اعلا نیرو</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.table-hover tbody tr:hover{background-color:rgba(52,152,219,0.1);}
.badge-admin{background-color:#dc3545;}
.badge-user{background-color:#28a745;}
.search-box{max-width:300px;}
</style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container mt-4">
  <h2 class="text-center mb-4">مدیریت کاربران سیستم</h2>

  <?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>جستجو و فیلتر</span>
      <span class="badge bg-info">تعداد کاربران: <?php echo (int)$total_users; ?></span>
    </div>
    <div class="card-body">
      <form method="GET" class="row g-3">
        <div class="col-md-8">
          <div class="input-group">
            <input type="text" class="form-control" name="search" placeholder="جستجو بر اساس نام کاربری، نام کامل یا ایمیل" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
            <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> جستجو</button>
          </div>
        </div>
        <div class="col-md-4">
          <?php if ($search !== ''): ?>
            <a href="users.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i> پاک کردن فیلتر</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header">افزودن کاربر جدید</div>
    <div class="card-body">
      <form method="POST" id="addUserForm">
        <?php csrf_field(); ?>
        <div class="row">
          <div class="col-md-6"><div class="mb-3"><label class="form-label">نام کاربری *</label><input type="text" class="form-control" name="username" required></div></div>
          <div class="col-md-6"><div class="mb-3"><label class="form-label">رمز عبور *</label><input type="password" class="form-control" name="password" required><small class="form-text text-muted">حداقل ۶ کاراکتر</small></div></div>
        </div>
        <div class="row">
          <div class="col-md-6"><div class="mb-3"><label class="form-label">نام کامل</label><input type="text" class="form-control" name="full_name"></div></div>
          <div class="col-md-6"><div class="mb-3"><label class="form-label">ایمیل</label><input type="email" class="form-control" name="email"></div></div>
        </div>
        <div class="row">
          <div class="col-md-6"><div class="mb-3"><label class="form-label">نقش *</label><select class="form-select" name="role" required><option value="کاربر عادی">کاربر عادی</option><option value="ادمین">ادمین</option></select></div></div>
        </div>
        <button type="submit" name="add_user" class="btn btn-primary"><i class="fas fa-plus"></i> افزودن کاربر</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>لیست کاربران</span>
      <div><span class="badge bg-secondary">صفحه <?php echo (int)$page; ?> از <?php echo (int)$total_pages; ?></span></div>
    </div>
    <div class="card-body">
      <?php if (!empty($users)): ?>
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead><tr><th>#</th><th>نام کاربری</th><th>نام کامل</th><th>ایمیل</th><th>نقش</th><th>تاریخ ایجاد</th><th>عملیات</th></tr></thead>
          <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
              <td><?php echo (int)$user['id']; ?></td>
              <td><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($user['full_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($user['email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><span class="badge <?php echo ($user['role'] === 'ادمین') ? 'badge-admin' : 'badge-user'; ?>"><?php echo htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?></span></td>
              <td><?php echo htmlspecialchars($user['created_at'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <div class="btn-group btn-group-sm">
                  <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editUserModal"
                          data-user-id="<?php echo (int)$user['id']; ?>"
                          data-username="<?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?>"
                          data-full-name="<?php echo htmlspecialchars($user['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                          data-email="<?php echo htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                          data-role="<?php echo htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-edit"></i>
                  </button>
                  <?php if ((int)$user['id'] !== (int)($_SESSION['user_id'] ?? 0)): ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('آیا از حذف کاربر &quot;<?php echo addslashes($user['username']); ?>&quot; مطمئن هستید؟');">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="delete_id" value="<?php echo (int)$user['id']; ?>">
                    <button type="submit" name="delete_user" class="btn btn-danger"><i class="fas fa-trash"></i></button>
                  </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($total_pages > 1): ?>
      <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center">
          <?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">&laquo;</a></li><?php endif; ?>
          <?php for ($i=1;$i<=$total_pages;$i++): ?><li class="page-item <?php echo $i===$page?'active':''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a></li><?php endfor; ?>
          <?php if ($page < $total_pages): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">&raquo;</a></li><?php endif; ?>
        </ul>
      </nav>
      <?php endif; ?>
      <?php else: ?>
        <div class="text-center py-4">
          <i class="fas fa-users fa-3x text-muted mb-3"></i>
          <p class="text-muted">هیچ کاربری یافت نشد.</p>
          <?php if ($search !== ''): ?><a href="users.php" class="btn btn-primary">مشاهده همه کاربران</a><?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">ویرایش کاربر</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="POST" id="editUserForm">
      <div class="modal-body">
        <?php csrf_field(); ?>
        <input type="hidden" name="user_id" id="edit_user_id">
        <input type="hidden" name="edit_user" value="1">
        <div class="mb-3"><label class="form-label">نام کاربری *</label><input type="text" class="form-control" id="edit_username" name="username" required></div>
        <div class="mb-3"><label class="form-label">نام کامل</label><input type="text" class="form-control" id="edit_full_name" name="full_name"></div>
        <div class="mb-3"><label class="form-label">ایمیل</label><input type="email" class="form-control" id="edit_email" name="email"></div>
        <div class="mb-3"><label class="form-label">نقش *</label>
          <select class="form-select" id="edit_role" name="role" required>
            <option value="کاربر عادی">کاربر عادی</option>
            <option value="ادمین">ادمین</option>
          </select>
        </div>
        <div class="mb-3"><label class="form-label">رمز عبور جدید (اختیاری)</label><input type="password" class="form-control" id="edit_password" name="password"><small class="form-text text-muted">حداقل ۶ کاراکتر</small></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button><button type="submit" class="btn btn-primary">ذخیره تغییرات</button></div>
    </form>
  </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('addUserForm').addEventListener('submit',function(e){const p=this.querySelector('input[name="password"]').value;if(p.length<6){e.preventDefault();alert('رمز عبور باید حداقل ۶ کاراکتر باشد.')}});
document.getElementById('editUserForm').addEventListener('submit',function(e){const p=document.getElementById('edit_password').value;if(p && p.length<6){e.preventDefault();alert('رمز عبور باید حداقل ۶ کاراکتر باشد.')}});
const editUserModal=document.getElementById('editUserModal');
if(editUserModal){editUserModal.addEventListener('show.bs.modal',function(event){const btn=event.relatedTarget;if(!btn)return;document.getElementById('edit_user_id').value=btn.getAttribute('data-user-id')||'';document.getElementById('edit_username').value=btn.getAttribute('data-username')||'';document.getElementById('edit_full_name').value=btn.getAttribute('data-full-name')||'';document.getElementById('edit_email').value=btn.getAttribute('data-email')||'';document.getElementById('edit_role').value=btn.getAttribute('data-role')||'کاربر عادی';});}
</script>
</body>
</html>