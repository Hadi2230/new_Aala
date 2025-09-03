<?php
// navbar.php - نسخه نهایی و پایدار

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8') : 'کاربر';
$rawRole  = isset($_SESSION['role']) ? trim((string)$_SESSION['role']) : '';
$is_admin = ($rawRole === 'ادمین' || strcasecmp($rawRole, 'admin') === 0 || strcasecmp($rawRole, 'administrator') === 0);
$is_logged_in = !empty($_SESSION['user_id']);

$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
$active  = function ($files) use ($current): string {
    if (is_string($files)) $files = [$files];
    return in_array($current, $files, true) ? ' active' : '';
};
$surveyActive = $active(['survey.php','survey_list.php','survey_admin.php']);
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
<style>
    :root { --primary-color:#2c3e50; --secondary-color:#34495e; --hover-bg:rgba(255,255,255,.15); }
    .navbar-custom{background:linear-gradient(135deg,var(--primary-color) 0%,var(--secondary-color) 100%);box-shadow:0 2px 20px rgba(0,0,0,.1);padding:.8rem 1rem;transition:all .3s ease;}
    .navbar-brand{font-weight:700;display:flex;align-items-center}
    .nav-link{color:rgba(255,255,255,.9)!important;border-radius:8px;margin:0 2px;transition:all .2s ease;display:flex;align-items:center;position:relative;padding:.5rem .8rem!important}
    .nav-link:hover,.nav-link.active{background-color:var(--hover-bg);color:#fff!important;transform:translateY(-2px)}
    .dropdown-menu{border:none;box-shadow:0 5px 15px rgba(0,0,0,.15);border-radius:10px;overflow:hidden}
    .dropdown-item{transition:all .2s ease;padding:.5rem 1rem}
    .dropdown-item:hover{background-color:#f8f9fa;padding-right:1.2rem}
    .search-container{position:relative}
    .search-input{background:rgba(255,255,255,.1);border:none;border-radius:20px;color:#fff;padding:.4rem 1rem .4rem 2.5rem;width:220px;transition:all .3s ease}
    .search-input:focus{outline:none;background:rgba(255,255,255,.15);width:260px;box-shadow:0 0 0 2px rgba(255,255,255,.2)}
    .search-icon{position:absolute;right:12px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,.7)}
    .notification-badge{position:absolute;top:-5px;right:-5px;background:#e74c3c;color:#fff;border-radius:50%;width:18px;height:18px;font-size:.7rem;display:flex;align-items:center;justify-content:center}
    .theme-switcher{background:none;border:none;color:rgba(255,255,255,.8);font-size:1.2rem;cursor:pointer;transition:all .3s ease}
    .theme-switcher:hover{color:#fff;transform:rotate(30deg)}
    .clock-chip{background:rgba(255,255,255,.1);color:#fff;border-radius:20px;padding:.35rem .75rem;font-variant-numeric:tabular-nums}
    .online-status{width:10px;height:10px;background:#2ecc71;border-radius:50%;margin-left:8px;position:relative}
    .online-status::after{content:'';position:absolute;width:100%;height:100%;border-radius:50%;background:#2ecc71;animation:pulse 1.5s infinite;opacity:.6}
    @keyframes pulse{0%{transform:scale(1);opacity:.6}70%{transform:scale(2.5);opacity:0}100%{transform:scale(1);opacity:0}}
    .navbar-custom.scrolled{padding:.5rem 1rem}
    @media (max-width:991.98px){.navbar-meta{gap:.5rem!important;margin-top:.75rem;width:100%;justify-content:flex-start}.search-input{width:100%}}
</style>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php"><i class="fas fa-bolt me-2"></i>اعلا نیرو</a>

        <div class="d-flex align-items-center gap-2 navbar-meta order-lg-2">
            <div class="search-container d-none d-lg-block me-2">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="search-input" placeholder="جستجو...">
            </div>

            <span class="clock-chip d-none d-lg-inline" id="liveClock">--:--:--</span>

            <button class="theme-switcher d-none d-lg-inline" id="themeSwitcher" type="button" aria-label="Switch theme">
                <i class="fas fa-moon"></i>
            </button>

            <a href="#" class="d-none d-lg-flex align-items-center text-white-50 text-decoration-none">
                <i class="fas fa-user me-2"></i>
                <span><?php echo $username; ?></span>
                <?php if ($is_admin): ?><span class="badge bg-warning ms-1">ادمین</span><?php endif; ?>
                <div class="online-status ms-2"></div>
            </a>

            <?php if ($is_logged_in): ?>
                <a class="btn btn-sm btn-outline-light d-none d-lg-inline-flex" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i>خروج</a>
            <?php else: ?>
                <a class="btn btn-sm btn-outline-light d-none d-lg-inline-flex" href="login.php"><i class="fas fa-sign-in-alt me-1"></i>ورود</a>
            <?php endif; ?>

            <button class="navbar-toggler ms-2" type="button" data-bs-toggle="collapse" data-bs-target="#navMain" aria-controls="navMain" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>

        <div id="navMain" class="collapse navbar-collapse order-lg-1 mt-2 mt-lg-0">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link<?php echo $active('dashboard.php'); ?>" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>داشبورد</a></li>
                <li class="nav-item"><a class="nav-link<?php echo $active('assets.php'); ?>" href="assets.php"><i class="fas fa-server me-2"></i>دارایی‌ها <span class="notification-badge">3</span></a></li>
                <li class="nav-item"><a class="nav-link<?php echo $active('customers.php'); ?>" href="customers.php"><i class="fas fa-users me-2"></i>مشتریان</a></li>
                <li class="nav-item"><a class="nav-link<?php echo $active('assignments.php'); ?>" href="assignments.php"><i class="fas fa-link me-2"></i>انتساب‌ها</a></li>
                <li class="nav-item"><a class="nav-link<?php echo $active('create_guaranty.php'); ?>" href="create_guaranty.php"><i class="fas fa-file-contract me-2"></i>گارانتی</a></li>
                <li class="nav-item"><a class="nav-link<?php echo $active('reports.php'); ?>" href="reports.php"><i class="fas fa-chart-bar me-2"></i>گزارشات</a></li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle<?php echo $surveyActive; ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-poll me-2"></i>نظرسنجی
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="survey.php"><i class="fas fa-clipboard-list me-2"></i>نظرسنجی فعال</a></li>
                        <li><a class="dropdown-item" href="survey_list.php"><i class="fas fa-history me-2"></i>تاریخچه نظرسنجی‌ها</a></li>
                        <?php if ($is_admin): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="survey_admin.php"><i class="fas fa-cogs me-2"></i>مدیریت نظرسنجی</a></li>
                        <?php endif; ?>
                    </ul>
                </li>

                <?php if ($is_admin): ?>
                    <li class="nav-item"><a class="nav-link<?php echo $active('system_logs.php'); ?>" href="system_logs.php"><i class="fas fa-clipboard-list me-2"></i>لاگ سیستم</a></li>
                    <li class="nav-item"><a class="nav-link<?php echo $active('users.php'); ?>" href="users.php"><i class="fas fa-user-cog me-2"></i>مدیریت کاربران</a></li>
                <?php endif; ?>

                <li class="nav-item d-lg-none mt-2">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-user me-2 text-white-50"></i>
                        <span class="text-white-50"><?php echo $username; ?><?php echo $is_admin ? ' (ادمین)' : ''; ?></span>
                    </div>
                </li>
                <li class="nav-item d-lg-none mt-2">
                    <?php if ($is_logged_in): ?>
                        <a class="btn btn-sm btn-outline-light w-100" href="logout.php"><i class="fas fa-sign-out-alt me-1"></i>خروج</a>
                    <?php else: ?>
                        <a class="btn btn-sm btn-outline-light w-100" href="login.php"><i class="fas fa-sign-in-alt me-1"></i>ورود</a>
                    <?php endif; ?>
                </li>

                <li class="nav-item d-lg-none mt-3">
                    <div class="search-container mb-2">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" class="search-input w-100" placeholder="جستجو...">
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="clock-chip" id="liveClockMobile">--:--:--</span>
                        <button class="theme-switcher btn btn-sm btn-outline-light" id="themeSwitcherMobile" type="button" aria-label="Switch theme">
                            <i class="fas fa-moon"></i>
                        </button>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
    function setClock(el){ function upd(){ try{el.textContent=new Date().toLocaleTimeString('fa-IR',{hour12:false});}catch(e){const n=new Date(),p=v=>String(v).padStart(2,'0');el.textContent=p(n.getHours())+':'+p(n.getMinutes())+':'+p(n.getSeconds());}} upd(); setInterval(upd,1000);}
    const c1=document.getElementById('liveClock'); if(c1) setClock(c1);
    const c2=document.getElementById('liveClockMobile'); if(c2) setClock(c2);

    function applyTheme(saved, iconEl){ if(saved==='dark'){document.documentElement.setAttribute('data-theme','dark'); if(iconEl?.classList.contains('fa-moon')) iconEl.classList.replace('fa-moon','fa-sun');} else {document.documentElement.removeAttribute('data-theme'); if(iconEl?.classList.contains('fa-sun')) iconEl.classList.replace('fa-sun','fa-moon');}}
    const t1=document.getElementById('themeSwitcher'), t2=document.getElementById('themeSwitcherMobile');
    const i1=t1?t1.querySelector('i'):null, i2=t2?t2.querySelector('i'):null;
    applyTheme(localStorage.getItem('theme')||'light', i1||i2);
    function toggleTheme(icon){const next=(icon&&icon.classList.contains('fa-moon'))?'dark':'light';localStorage.setItem('theme',next);applyTheme(next,i1||i2);}
    if(t1) t1.addEventListener('click', ()=>toggleTheme(i1));
    if(t2) t2.addEventListener('click', ()=>toggleTheme(i2));

    window.addEventListener('scroll', function(){
        const nav=document.querySelector('.navbar-custom');
        if (window.scrollY>10) nav.classList.add('scrolled'); else nav.classList.remove('scrolled');
    });

    document.querySelectorAll('.dropdown').forEach(d=>{
        d.addEventListener('show.bs.dropdown', function(){const m=this.querySelector('.dropdown-menu'); if(m){m.style.opacity=0;m.style.transform='translateY(10px)';}});
        d.addEventListener('shown.bs.dropdown', function(){const m=this.querySelector('.dropdown-menu'); if(m){m.style.transition='opacity .2s ease, transform .2s ease';m.style.opacity=1;m.style.transform='translateY(0)';}});
    });
    
    // رفع مشکل کلیک روی منوی نظرسنجی
    document.querySelectorAll('.nav-link.dropdown-toggle').forEach(item => {
        item.addEventListener('click', function(e) {
            // فقط اگر در حالت موبایل هستیم، منو را باز کنیم
            if (window.innerWidth < 992) {
                e.preventDefault();
                const dropdown = this.closest('.dropdown');
                const menu = dropdown.querySelector('.dropdown-menu');
                
                if (menu.classList.contains('show')) {
                    menu.classList.remove('show');
                    this.setAttribute('aria-expanded', 'false');
                } else {
                    menu.classList.add('show');
                    this.setAttribute('aria-expanded', 'true');
                }
            }
        });
    });
});
</script>