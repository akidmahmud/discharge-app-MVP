<?php namespace ProcessWire;

/**
 * home.php — Login page (application entry point)
 * All unauthenticated users are redirected here by _init.php.
 */

$_roleFlags = wire('authRoleFlags') ?: [];
$isAdminUser = !empty($_roleFlags['is_admin']);
$isConsultantUser = !empty($_roleFlags['is_consultant']);
$isMedicalOfficerUser = !empty($_roleFlags['is_medical_officer']);

$getPostLoginRedirect = function () use ($isAdminUser): string {
    return $isAdminUser ? '/admin-panel/' : '/dashboard/';
};

if ($user->isLoggedin()) {
    $session->redirect($getPostLoginRedirect());
}

$noShell    = true;
$loginError = '';

if ($input->requestMethod('POST') && $input->post->action === 'login') {
    if (!$session->CSRF->validate()) {
        $loginError = 'Security token mismatch. Please refresh and try again.';
    } else {
        $usernameInput = trim((string) $input->post->username);
        $password = $input->post->password;

        if ($usernameInput === '' || !$password) {
            $loginError = 'Please enter your username and password.';
        } else {
            $loginName = $usernameInput;
            $legacyUserMap = [
                'pa_user' => 'physician-assistant-demo',
                'mo_user' => 'medical-officer-demo',
                'consultant' => 'consultant-demo',
            ];

            $normalizedLogin = strtolower($usernameInput);
            if (isset($legacyUserMap[$normalizedLogin])) {
                $loginName = $legacyUserMap[$normalizedLogin];
            }

            if (strpos($usernameInput, '@') !== false) {
                $userByEmail = $users->get("email=" . $sanitizer->selectorValue($usernameInput));
                if ($userByEmail && $userByEmail->id) {
                    $loginName = $userByEmail->name;
                }
            }

            try {
                $loggedIn = $session->login($loginName, $password);
            } catch (\Throwable $e) {
                if (stripos($e->getMessage(), 'Login not attempted due to overflow') !== false) {
                    $loggedIn = null;
                    $loginError = 'Too many login attempts. Please wait at least 10 seconds and try again.';
                } else {
                    throw $e;
                }
            }

            if (!$loginError) {
                if ($loggedIn && $loggedIn->id) {
                    $loggedInIsAdmin = $loggedIn->isSuperuser() || $loggedIn->hasRole('admin');
                    $loggedInIsConsultant = $loggedIn->hasRole('consultant');
                    $loggedInIsPA = $loggedIn->hasRole('physician-assistant');
                    $loggedInIsMO = $loggedIn->hasRole('medical-officer');
                    $session->set('auth_user_id', (int) $loggedIn->id);
                    $session->set('auth_role', $loggedInIsAdmin ? 'admin' : ($loggedInIsConsultant ? 'consultant' : ($loggedInIsMO ? 'medical-officer' : ($loggedInIsPA ? 'physician-assistant' : 'clinical-user'))));
                    $session->set('auth_login_ts', time());
                    $session->set('auth_last_activity', time());
                    $session->redirect($loggedInIsAdmin ? '/admin-panel/' : '/dashboard/');
                } else {
                    $loginError = 'Invalid credentials. Please try again.';
                }
            }
        }
    }
}

// Load login settings from DB
$loginSettings = [];
try {
    $rows = $database->query("SELECT setting_key, setting_value FROM admin_login_settings")->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($rows as $row) $loginSettings[$row['setting_key']] = $row['setting_value'];
} catch (\Exception $e) {}
$quoteText = !empty($loginSettings['quote_text'])
    ? $loginSettings['quote_text']
    : 'Precision in documentation is the first step toward precision in care.';
$backgroundImage = !empty($loginSettings['bg_image']) ? $loginSettings['bg_image'] : '';

$isExpired      = $input->get->int('expired') === 1;
$isUnauthorized = $input->get->int('unauthorized') === 1;
$csrfName       = $session->CSRF->getTokenName();
$csrfVal        = $session->CSRF->getTokenValue();

?>
<div id="content">
<div class="login-page"<?= $backgroundImage ? ' style="--login-bg-image:url(\'' . $sanitizer->entities($backgroundImage) . '\')"' : '' ?>>

  <!-- ── Background: waves + medical elements ────────────────── -->
  <div class="login-bg" aria-hidden="true">
    <svg viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
      <!-- Wave 1 — large sweep bottom-left -->
      <path d="M-100 600 Q200 400 500 620 Q800 840 1100 600 Q1300 460 1600 700 L1600 1000 L-100 1000 Z"
            fill="rgba(147,197,253,0.35)" />
      <!-- Wave 2 — mid sweep -->
      <path d="M-100 700 Q300 550 600 720 Q900 890 1200 680 Q1380 590 1600 780 L1600 1000 L-100 1000 Z"
            fill="rgba(191,219,254,0.30)" />
      <!-- Wave 3 — upper decorative sweep -->
      <path d="M-200 200 Q100 50 400 250 Q700 450 1000 200 Q1200 50 1500 300 L1500 -100 L-200 -100 Z"
            fill="rgba(147,197,253,0.20)" />

      <!-- Medical cross — bottom left -->
      <g fill="rgba(147,197,253,0.45)" transform="translate(80,680)">
        <rect x="-8" y="-26" width="16" height="52" rx="4"/>
        <rect x="-26" y="-8" width="52" height="16" rx="4"/>
      </g>
      <!-- Medical cross — top left smaller -->
      <g fill="rgba(147,197,253,0.30)" transform="translate(160,130) scale(0.6)">
        <rect x="-8" y="-26" width="16" height="52" rx="4"/>
        <rect x="-26" y="-8" width="52" height="16" rx="4"/>
      </g>
      <!-- Medical cross — right mid -->
      <g fill="rgba(147,197,253,0.28)" transform="translate(1340,400) scale(0.75)">
        <rect x="-8" y="-26" width="16" height="52" rx="4"/>
        <rect x="-26" y="-8" width="52" height="16" rx="4"/>
      </g>

      <!-- Dot grid — upper right -->
      <g fill="rgba(96,165,250,0.25)">
        <?php
        for ($row = 0; $row < 7; $row++) {
          for ($col = 0; $col < 7; $col++) {
            $cx = 1180 + $col * 28;
            $cy = 60  + $row * 28;
            echo "<circle cx=\"$cx\" cy=\"$cy\" r=\"2.5\"/>";
          }
        }
        ?>
      </g>

      <!-- Floating circles -->
      <circle cx="1320" cy="480" r="40" fill="rgba(147,197,253,0.22)" />
      <circle cx="1360" cy="520" r="22" fill="rgba(147,197,253,0.18)" />
      <circle cx="90"  cy="320" r="55" fill="rgba(147,197,253,0.15)" />
    </svg>
  </div>

  <!-- ── Login card ───────────────────────────────────────────── -->
  <div class="login-card" role="main">

    <!-- Header -->
    <div class="login-card__header">
      <div class="login-card__icon-wrap" aria-hidden="true">
        <!-- Shield with medical cross -->
        <svg viewBox="0 0 24 24">
          <path d="M12 2L3 7v6c0 5.25 3.75 10.15 9 11.25C17.25 23.15 21 18.25 21 13V7L12 2z"/>
          <line x1="12" y1="9" x2="12" y2="15"/>
          <line x1="9"  y1="12" x2="15" y2="12"/>
        </svg>
      </div>

      <div class="login-card__title-group">
        <p class="login-card__signin">Sign in to</p>
        <h1 class="login-card__registry">Dr. Tawfiq's Clinical Registry</h1>
      </div>
      <div class="login-card__divider"></div>
    </div>

    <!-- Notices -->
    <?php if ($isExpired): ?>
    <div class="login-alert login-alert--info" role="alert">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      Your session has expired. Please sign in again.
    </div>
    <?php elseif ($isUnauthorized): ?>
    <div class="login-alert login-alert--info" role="alert">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      Please sign in to access this page.
    </div>
    <?php endif; ?>

    <?php if ($loginError): ?>
    <div class="login-alert login-alert--error" role="alert">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
      <?php echo $sanitizer->entities($loginError); ?>
    </div>
    <?php endif; ?>

    <!-- Form -->
    <form class="login-form" method="post" action="/" id="login-form" novalidate>
      <input type="hidden" name="action" value="login">
      <input type="hidden" name="<?php echo $csrfName; ?>" value="<?php echo $csrfVal; ?>">

      <!-- Username / Email -->
      <div class="login-field">
        <label class="login-field__label" for="login-username">Email or Username</label>
        <div class="login-field__wrap">
          <span class="login-field__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
          </span>
          <input
            class="login-field__input"
            type="text"
            id="login-username"
            name="username"
            placeholder="Enter your email or username"
            autocomplete="username"
            value="<?php echo $sanitizer->entities(trim((string) ($input->post->username ?? ''))); ?>"
            required
            autofocus
          >
        </div>
      </div>

      <!-- Password -->
      <div class="login-field">
        <label class="login-field__label" for="login-password">Password</label>
        <div class="login-field__wrap">
          <span class="login-field__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          </span>
          <input
            class="login-field__input login-field__input--password"
            type="password"
            id="login-password"
            name="password"
            placeholder="Enter your password"
            autocomplete="current-password"
            required
          >
          <button type="button" class="login-field__eye" id="toggle-password" aria-label="Show password">
            <svg id="eye-show" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg id="eye-hide" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
          </button>
        </div>
      </div>

      <!-- Submit -->
      <button type="submit" class="login-btn" id="login-submit">
        <span class="login-btn__spinner" aria-hidden="true"></span>
        <span class="login-btn__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
        </span>
        <span class="login-btn__label">Sign In</span>
      </button>
    </form>

    <!-- Quote -->
    <blockquote class="login-quote" aria-label="Motivational quote">
      <span class="login-quote__mark" aria-hidden="true">&ldquo;</span>
      <p class="login-quote__text"><?= htmlspecialchars($quoteText) ?><span class="login-quote__mark login-quote__mark--end" aria-hidden="true">&rdquo;</span></p>
    </blockquote>

    <!-- Footer -->
    <footer class="login-footer">
      <span class="login-footer__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="M12 2L3 7v6c0 5.25 3.75 10.15 9 11.25C17.25 23.15 21 18.25 21 13V7L12 2z"/></svg>
      </span>
      <p class="login-footer__text">Authorized users only</p>
    </footer>

  </div><!-- /.login-card -->

</div><!-- /.login-page -->

  <script>
  (function () {
    var pwInput   = document.getElementById('login-password');
    var toggleBtn = document.getElementById('toggle-password');
    var eyeShow   = document.getElementById('eye-show');
    var eyeHide   = document.getElementById('eye-hide');

    if (toggleBtn) {
      toggleBtn.addEventListener('click', function () {
        var isPassword = pwInput.type === 'password';
        pwInput.type       = isPassword ? 'text'     : 'password';
        eyeShow.style.display = isPassword ? 'none'  : '';
        eyeHide.style.display = isPassword ? ''      : 'none';
        toggleBtn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
      });
    }

    var form = document.getElementById('login-form');
    var btn  = document.getElementById('login-submit');
    if (form) {
      form.addEventListener('submit', function () {
        btn.disabled = true;
        btn.classList.add('is-loading');
      });
    }
  })();
  </script>
</div><!-- #content -->
