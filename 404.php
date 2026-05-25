<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Forbidden · Muyovozi High School</title>
    <!-- Bootstrap 5 CSS (same as header) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 (same as header) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Font for a sweet touch -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: linear-gradient(145deg, #f9fbfd 0%, #f0f4f8 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            margin: 0;
            color: #1e293b;
        }

        .forbidden-card {
            max-width: 620px;
            width: 100%;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(99, 224, 126, 0.25);
            border-radius: 48px;
            box-shadow: 0 30px 60px -20px rgba(0,40,20,0.25), 
                        0 0 0 1px rgba(99,224,126,0.1) inset,
                        0 10px 30px -10px rgba(0,0,0,0.1);
            padding: 3rem 2.5rem;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .forbidden-card:hover {
            transform: scale(1.01);
        }

        /* forbidden emblem */
        .emblem {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #fff5f5 0%, #ffeaea 100%);
            border-radius: 50%;
            margin: 0 auto 1.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid rgba(220, 53, 69, 0.2);
            box-shadow: 0 15px 30px -12px rgba(220,53,69,0.3);
        }

        .emblem i {
            font-size: 30px;
            color: #dc3545;
            filter: drop-shadow(0 6px 8px rgba(200,40,50,0.3));
        }

        h1 {
            font-size: 6rem;
            font-weight: 700;
            line-height: 1;
            background: linear-gradient(135deg, #2d3e50, #1b2a3a);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -2px;
            margin-bottom: 0.3rem;
            text-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }

        .grade-403 {
            font-size: 1.4rem;
            font-weight: 600;
            color: #dc3545;
            background: rgba(220,53,69,0.08);
            display: inline-block;
            padding: 0.3rem 1.8rem;
            border-radius: 60px;
            letter-spacing: 0.3px;
            border: 1px solid rgba(220,53,69,0.2);
            margin-bottom: 1.2rem;
            backdrop-filter: blur(4px);
        }

        .shield-message {
            font-size: 1.2rem;
            font-weight: 500;
            color: #334155;
            background: #f8fafc;
            padding: 0.9rem 1.6rem;
            border-radius: 100px;
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            border: 1px solid #e9edf2;
            box-shadow: 0 6px 14px rgba(0,20,10,0.04);
            margin: 1.5rem 0 1rem;
        }

        .shield-message i {
            color: #63E07E;
            font-size: 1.4rem;
        }

        .description {
            color: #475569;
            font-size: 1.05rem;
            margin: 1rem 0 2rem 0;
            padding: 0 0.8rem;
            font-weight: 400;
            line-height: 1.6;
        }

        .countdown-badge {
            background: white;
            border-radius: 80px;
            padding: 0.8rem 2rem;
            display: inline-flex;
            align-items: center;
            gap: 1rem;
            border: 1px solid rgba(99,224,126,0.5);
            box-shadow: 0 8px 18px -8px rgba(99,224,126,0.3);
            margin: 0.8rem 0 2.2rem;
        }

        .countdown-timer {
            font-size: 2.3rem;
            font-weight: 700;
            background: linear-gradient(135deg, #63E07E, #2e9e4a);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            min-width: 70px;
            text-align: center;
        }

        .countdown-label {
            font-size: 1rem;
            font-weight: 500;
            color: #1e3a3a;
            opacity: 0.8;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .btn-ghost {
            background: transparent;
            border: 1.5px solid #cbd5e1;
            color: #334155;
            padding: 0.8rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-ghost:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
            color: #0f172a;
        }

        .btn-primary-custom {
            background: linear-gradient(115deg, #63E07E, #43b05e);
            border: none;
            padding: 0.8rem 2.3rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            color: white;
            box-shadow: 0 10px 20px -7px #3bac57;
            transition: 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(255,255,255,0.3);
        }

        .btn-primary-custom:hover {
            background: linear-gradient(115deg, #4fb568, #3a954f);
            transform: translateY(-3px);
            box-shadow: 0 18px 25px -10px #2f8746;
            color: white;
        }

        /* small footer-like credit (subtle) */
        .sweet-credit {
            margin-top: 2.5rem;
            font-size: 0.8rem;
            color: #8a9fb0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border-top: 1px dashed #d0dbe8;
            padding-top: 1.8rem;
        }

        .sweet-credit b {
            font-weight: 600;
            color: #4f6f8f;
        }

        .sweet-credit b span {
            color: #63E07E;
            font-weight: 700;
        }

        @media (max-width: 480px) {
            .forbidden-card {
                padding: 2.5rem 1.5rem;
                border-radius: 36px;
            }
            h1 {
                font-size: 4.8rem;
            }
            .countdown-timer {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>

<div class="forbidden-card">

    <!-- 403 with attitude -->
    <h1>403</h1>
    <div class="grade-403">
        <i class="fa-regular fa-face-frown me-2"></i> Forbidden
    </div>

    <!-- sweet guardian message -->
    <div class="shield-message">
        <i class="fa-solid fa-shield-halved fa-beat-fade" style="--fa-beat-fade-opacity: 0.7; --fa-beat-fade-scale: 0.9;"></i>
        <span>Access denied · protected area</span>
        <i class="fa-solid fa-shield-halved fa-beat-fade" style="--fa-beat-fade-opacity: 0.7; --fa-beat-fade-scale: 0.9;"></i>
    </div>

    <p class="description">
        You don’t have permission to access this page.<br> 
        Don't worry — we’ll walk you back to safety.
    </p>

    <!-- 5 second timer display (auto redirect) -->
    <div class="countdown-badge">
        <i class="fa-regular fa-clock fa-xl" style="color:#43b05e;"></i>
        <span class="countdown-label">redirecting in</span>
        <span class="countdown-timer" id="countdown">10</span>
        <span class="countdown-label">s</span>
    </div>

    <!-- manual options -->
    <div class="action-buttons">
        <a href="javascript:history.back()" class="btn-ghost">
            <i class="fa-regular fa-hand-point-left"></i> Go back
        </a>
        <a href="mhs/login.php" class="btn-primary-custom" id="directLoginBtn">
            <i class="fa-solid fa-arrow-right-to-bracket"></i> Login now
        </a>
    </div>
</div>

<!-- Bootstrap JS (for alert close etc, not needed but we include for consistency) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery (optional) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
(function() {
    'use strict';

    // ----- 10 seconds countdown & redirect -----
    const countdownEl = document.getElementById('countdown');
    let remaining = 10;                // 5 seconds as requested
    const loginUrl = 'mhs/login.php';   // exact relative path from forbidden page (as in your spec)

    // update timer each second
    const timer = setInterval(function() {
        remaining -= 1;
        if (countdownEl) {
            countdownEl.textContent = remaining;
        }

        if (remaining <= 0) {
            clearInterval(timer);
            // redirect to login page
            window.location.href = loginUrl;
        }
    }, 1000);

    // optional: if user clicks "Login now", clear timer to avoid double redirect
    const loginBtn = document.getElementById('directLoginBtn');
    if (loginBtn) {
        loginBtn.addEventListener('click', function(e) {
            // don't prevent default – we want the link to work
            // but we clear the timer so it doesn't also redirect
            clearInterval(timer);
        });
    }

    // also if user clicks "Go back", clear timer (they leave page anyway, but tidy)
    const backBtn = document.querySelector('.btn-ghost');
    if (backBtn) {
        backBtn.addEventListener('click', function() {
            clearInterval(timer);
        });
    }

    // Pause timer if page visibility changes? not needed, but we can keep it simple.

    // add a tiny sweet effect: countdown color pulse (optional)
    if (countdownEl) {
        setInterval(function() {
            if (countdownEl.style.transform) {
                countdownEl.style.transform = '';
            } else {
                countdownEl.style.transform = 'scale(1.1)';
            }
        }, 500);
    }
})();
</script>

<!-- note: no header/footer include because this is a standalone sweet 403 page,
     but we kept the design language and developer credit from footer.php (TzoneTech) 
     and the color scheme #63E07E matches primary. -->
</body>
</html>