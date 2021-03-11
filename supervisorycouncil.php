<!DOCTYPE html>
<?php
$username = '';
$error = '';

$ts_pw = posix_getpwuid(posix_getuid());
$ts_mycnf = parse_ini_file($ts_pw['dir'] . "/replica.my.cnf");
$nfi = new \NumberFormatter('fa-IR', \NumberFormatter::IGNORE);
$nfd = new \NumberFormatter('fa-IR', \NumberFormatter::DECIMAL);

if ( isset( $_POST['username'] ) ) {
  $username = str_replace('"', '', $_POST['username']);
  $prefix = '/^(User:|کاربر:)/';
  $username = preg_replace($prefix, '', $username);
  $username = ucfirst(trim($username));
  
  $mysqli = new mysqli('fawiki.analytics.db.svc.eqiad.wmflabs', $ts_mycnf['user'], $ts_mycnf['password'], 'fawiki_p');
  $q = $mysqli->prepare('SELECT actor_id, actor_name FROM actor WHERE actor_name=?');
  $q->bind_param("s", $username);
  $q->execute();
  $q->bind_result($actor_id, $actor_name);
  $q->fetch();
  if($username === '') {
    $error = 'No username provided';
  }
  elseif(!isset($actor_id)) {
    $error = 'User does not exist!';
  } else {
    // Criterion 1
    unset($q);
    $q = $mysqli->prepare("SELECT MIN(log_timestamp) FROM logging_userindex WHERE log_type = 'newusers' AND log_action IN ('create', 'autocreate') AND log_actor=?");
    $q->bind_param("i", $actor_id);
    $q->execute();
    $q->bind_result($mints);
    $q->fetch();
    if(!isset($mints)) {
      // Try estimating account creation time using edits instead
      unset($q);
      $q = $mysqli->prepare('SELECT MIN(rev_timestamp) FROM revision_userindex WHERE rev_actor=?');
      $q->bind_param("i", $actor_id);
      $q->execute();
      $q->bind_result($mints);
      $q->fetch();
    }
    if(isset($mints) && strlen($mints) == 14){
      $year = $nfi->format(substr($mints, 0, 4));
      $month = $nfi->format(intval(substr($mints, 4, 2)));
      $day = $nfi->format(intval(substr($mints, 6, 2)));

      $mints =  $year . '٫' . $month . '٫' . $day;
    }
    // Criterion 2
    unset($q);
    $q = $mysqli->prepare("SELECT COUNT(*) FROM revision_userindex JOIN page ON page_id = rev_page AND page_namespace = 0 WHERE rev_timestamp < 20201012000000 AND rev_actor=?");
    $q->bind_param("i", $actor_id);
    $q->execute();
    $q->bind_result($edits);
    $q->fetch();
    $edits = $nfd->format($edits);
    // Criterion 3
    unset($q);
    $q = $mysqli->prepare("SELECT COUNT(*) FROM revision_userindex JOIN page ON page_id = rev_page AND page_namespace = 0 WHERE rev_timestamp < 20201012000000 AND rev_timestamp > 20191012000000 AND rev_actor=?");
    $q->bind_param("i", $actor_id);
    $q->execute();
    $q->bind_result($recentedits);
    $q->fetch();
    $recentedits = $nfd->format($recentedits);
  }
}
?>
<html lang="fa">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport">
  <meta content="" name="description">
  <meta content="" name="author">
  <link href="" rel="icon">
  <title>ارزیابی رأی‌مندی انتخابات هیئت نظارت</title>
  <link href="https://cdn.rtlcss.com/bootstrap/v4.2.1/css/bootstrap.min.css" rel="stylesheet">
</head>
<body dir="rtl" style="direction:rtl">
  <div id="wrapper">
    <div id="page-content-wrapper">
      <nav class="navbar navbar-expand-lg navbar-dark bg-secondary border-bottom">
        <div class="container">
          <a class="navbar-brand" href="./">ابزارهای حجت</a>
          <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
              <li class="nav-item active">
                <a class="nav-link disabled" href="#">&larr; هیئت نظارت</a>
              </li>
            </ul>
          </div>
        </div>
      </nav>
      <div class="container">
        <h1 class="mt-4">ارزیابی شرایط رأی‌مندی انتخابات هیئت نظارت</h1>
        <p>این فرم به شما کمک می‌کند که وضعیت یک کاربر ویکی‌پدیای فارسی را از نظر برخورداری شرایط رأی‌مندی در <a href=
        "https://fa.wikipedia.org/wiki/%D9%88%D9%BE:%D9%86%D8%A7%D8%B8%D8%B1%DB%B1%DB%B1">انتخابات دور یازدهم هیئت
        نظارت</a> بررسی کنید.</p>
        <p>شرایط لازم برای برخورداری از حق رأی (رأی‌مندی) در <a href=
        "https://fa.wikipedia.org/wiki/%D9%88%D9%BE:%D8%B1%DA%AF%D9%87">اینجا</a> در دسترس هستند. این ابزار صرفاً برای
        کمک به ارزیابی آن شرایط ساخته شده‌است و نباید به عنوان منبع اصلی تعریف شرایط رأی‌مندی در نظر گرفته شود. این
        ابزار با راندن پرسمان‌هایی بر روی نسخهٔ کپی پایگاه دادهٔ ویکی‌مدیا (موسوم به Wikireplica Databases) اطلاعاتی را
        که برای ارزیابی شرایط رأی‌مندی می‌تواند مفید باشد استخراج می‌کند. توجه کنید که هر پرسمان بین چند ثانیه تا چند
        دقیقه طول می‌کشد و مدت آن برای کاربران قدیمی‌تر طولانی‌تر است.</p>
        <form action="./supervisorycouncil.php" method="post">
          <div class="form-group">
            <label for="username">نام کاربری</label> <input aria-describedby="usernameHelp" class="form-control" id=
            "username" name="username" placeholder="نام کاربری را وارد کنید" type="text" value=
            "<?php echo $username; ?>"> <small class="form-text text-muted" id="usernameHelp">نیازی به پیشوند «کاربر:»
            نیست.</small>
          </div><button class="btn btn-primary" type="submit">ارسال</button>
        </form>
        <hr>
        <?php if($error !== ''): ?>
        <div class="card mb-12 bg-danger">
          <div class="card-header text-white">
            خطا
          </div>
          <div class="card-body bg-white">
            <h5 class="card-title">درخواست شما شکست خورد</h5>
            <div class="card-text">
              <?php echo $error; ?>
            </div>
          </div>
        </div><?php elseif(isset($mints)): ?>
        <div class="card mb-12 bg-success">
          <div class="card-header text-white">
            نتایج
          </div>
          <div class="card-body bg-white">
            <h5 class="card-title">ارزیابی شرایط رأی‌مندی</h5>
            <div class="card-text">
              <p>این اطلاعات برای حساب <bdi><?php echo $username; ?></bdi> به دست آمد:</p>
              <table class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th>پرسمان</th>
                    <th>نتیجه</th>
                    <th>شرط لازم برای رأی‌مندان</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>تاریخ ایجاد حساب</td>
                    <td><?php echo $mints; ?></td>
                    <td>پیش از ۲۰۲۰٫۷٫۱۲</td>
                  </tr>
                  <tr>
                    <td>ویرایش در مقاله‌ها پیش از شروع انتخابات</td>
                    <td><?php echo $edits; ?></td>
                    <td>دست کم ۵۰۰</td>
                  </tr>
                  <tr>
                    <td>ویرایش در مقاله‌ها در یک سال منتهی به انتخابات</td>
                    <td><?php echo $recentedits; ?></td>
                    <td>دست کم ۱۰۰</td>
                  </tr>
                </tbody>
              </table>
              <p>برای دیدن سیاههٔ قطع دسترسی این کاربر <a href=
              "https://fa.wikipedia.org/w/index.php?title=Special:Logs&page=User:<?php echo urlencode($username); ?>&type=block">
              اینجا</a> کلیک کنید.</p>
            </div>
          </div>
        </div><?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
