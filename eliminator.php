<!DOCTYPE html>
<?php
$username = '';
$error = '';

$ts_pw = posix_getpwuid(posix_getuid());
$ts_mycnf = parse_ini_file($ts_pw['dir'] . "/replica.my.cnf");
$nfi = new \NumberFormatter('fa-IR', \NumberFormatter::IGNORE);
$nfd = new \NumberFormatter('fa-IR', \NumberFormatter::DECIMAL);

if ( isset( $_POST['username'] ) || isset ( $_POST['ts'] ) ) {
  $username = str_replace('"', '', $_POST['username']);
  $prefix = '/^(User:|کاربر:)/';
  $username = preg_replace($prefix, '', $username);
  $username = ucfirst(trim($username));

  $ts = trim($_POST['ts']);
  $ts_valid = preg_match('/^\d{14}$/', $ts);
  
  $mysqli = new mysqli('fawiki.analytics.db.svc.eqiad.wmflabs', $ts_mycnf['user'], $ts_mycnf['password'], 'fawiki_p');
  $q = $mysqli->prepare('SELECT actor_id, actor_name FROM actor WHERE actor_name=?');
  $q->bind_param("s", $username);
  $q->execute();
  $q->bind_result($actor_id, $actor_name);
  $q->fetch();
  if($username === '') {
    $error = 'نام کاربری وارد نشده';
  } elseif($ts === '') {
    $error = 'برچسب زمان وارد نشده';
  } elseif(!isset($actor_id)) {
    $error = 'چنین کاربری وجود ندارد!';
  } elseif($ts_valid === 0) {
    $error = 'برچسب زمان نامعتبر';
  } else {
    $year = substr($ts, 0, 4);
    $month = substr($ts, 4, 2);
    $day = substr($ts, 6, 2);
    $time = substr($ts, 8, 6);
    try {
      $date = new DateTime("{$year}-{$month}-{$day}");
    } catch (Exception $e) {
      $error = 'تاریخ نامعتبر وارد شده';
    }
    if($error === '') {
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
      $date = date_sub($date, new DateInterval('P180D'));
      $new_ts = $date->format('Ymd') . $time;
      
      unset($q);
      $q = $mysqli->prepare("SELECT COUNT(*) FROM revision_userindex JOIN page ON page_id = rev_page AND page_namespace = 0 WHERE rev_timestamp > ? AND rev_timestamp < ? AND rev_actor=?");
      $q->bind_param("ssi", $new_ts, $ts, $actor_id);
      $q->execute();
      $q->bind_result($edits);
      $q->fetch();print_r($edits);
      $edits = $nfd->format($edits);
    }
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
  <title>ارزیابی رأی‌مندی انتخابات ویکی‌بانی</title>
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
                <a class="nav-link disabled" href="#">&larr; ویکی‌بانی</a>
              </li>
            </ul>
          </div>
        </div>
      </nav>
      <div class="container">
        <h1 class="mt-4">ارزیابی شرایط رأی‌مندی انتخابات ویکی‌بانی</h1>
        <p>این فرم به شما کمک می‌کند که وضعیت یک کاربر ویکی‌پدیای فارسی را از نظر برخورداری شرایط رأی‌مندی در انتخابات ویکی‌بانی بررسی کنید.</p>
        <p>شرایط لازم برای برخورداری از حق رأی (رأی‌مندی) در
        <a href="https://fa.wikipedia.org/wiki/%D9%88%DB%8C%DA%A9%DB%8C%E2%80%8C%D9%BE%D8%AF%DB%8C%D8%A7:%D9%88%DB%8C%DA%A9%DB%8C%E2%80%8C%D8%A8%D8%A7%D9%86">سیاست وپ:بان</a>
         در دسترس هستند. این ابزار صرفاً برای کمک به ارزیابی آن شرایط ساخته شده‌است و نباید به عنوان منبع اصلی تعریف شرایط رأی‌مندی در نظر گرفته شود. این
        ابزار با راندن پرسمان‌هایی بر روی نسخهٔ کپی پایگاه دادهٔ ویکی‌مدیا (موسوم به Wikireplica Databases) اطلاعاتی را
        که برای ارزیابی شرایط رأی‌مندی می‌تواند مفید باشد استخراج می‌کند. توجه کنید که هر پرسمان بین چند ثانیه تا چند
        دقیقه طول می‌کشد و مدت آن برای کاربران قدیمی‌تر طولانی‌تر است.</p>
        <form action="./eliminator.php" method="post">
          <div class="form-group">
          <label for="username">نام کاربری</label> <input aria-describedby="usernameHelp" class="form-control" id=
            "username" name="username" placeholder="نام کاربری را وارد کنید" type="text" value=
            "<?php echo $username; ?>"> <small class="form-text text-muted" id="usernameHelp">نیازی به پیشوند «کاربر:»
            نیست.</small>
            <label for="ts" style="margin-top: 10pt">شروع انتخابات</label> <input aria-describedby="tsHelp" class="form-control" id=
            "ts" name="ts" placeholder="یک برچسب زمان وارد کنید" type="text" value=
            "<?php echo $ts; ?>"> <small class="form-text text-muted" id="tsHelp">
              به صورت یک برچسب زمان وارد کنید، مثل 20210306195432 به معنی سال ۲۰۲۱ ماه مارس (۳) روز ششم ساعت ۱۹ و ۵۴ دقیقه و ۳۲ ثانیهٔ جهانی
            </small>
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
                    <td>سه ماه پیش از شروع رأی‌گیری</td>
                  </tr>
                  <tr>
                    <td>ویرایش در مقاله‌ها در ۶ ماه منتهی به انتخابات</td>
                    <td><?php echo $edits; ?></td>
                    <td>دست کم ۱۰۰</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div><?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
