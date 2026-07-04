<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    $_SESSION['error_message'] = 'Access denied. Donor login required.';
    header('Location: ../login.php');
    exit();
}

if (time() - $_SESSION['last_activity'] > 1800) {
    session_unset();
    session_destroy();
    header('Location: ../login.php?timeout=1');
    exit();
}

$_SESSION['last_activity'] = time();

/** @var PDO $pdo */
$pdo = require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../components/sidebar.php';
require_once __DIR__ . '/../../components/notification.php';

$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Donor');

try {
    $activeCount = (int) $pdo->query("
        SELECT COUNT(*) FROM campaigns
        WHERE status IN ('active', 'approved')
          AND ends_at > NOW()
          AND funds_raised < goal_amount
    ")->fetchColumn();
} catch (PDOException $e) {
    $activeCount = 0;
}

$impactStories = [
    [
        'name' => 'Grace Wanjiku',
        'project' => 'Solar-Powered Water Pump',
        'quote' => 'JengaFund helped me build a prototype that now supplies clean water to 200 families in my village. I went from a classroom idea to real community impact.',
        'avatar' => 'Grace+Wanjiku',
    ],
    [
        'name' => 'Brian Ochieng',
        'project' => 'Smart Farm Monitoring',
        'quote' => 'The donations covered my sensors and membership fees. Today I mentor three other students who want to build agri-tech solutions.',
        'avatar' => 'Brian+Ochieng',
    ],
    [
        'name' => 'Amina Hassan',
        'project' => 'Portable Health Kit',
        'quote' => 'I could not afford lab materials on my own. Donors through JengaFund believed in me — now our clinic triage tool is being tested locally.',
        'avatar' => 'Amina+Hassan',
    ],
    [
        'name' => 'Kevin Mutua',
        'project' => 'Recycled Plastic Bricks',
        'quote' => 'Every milestone payout kept our team moving. We turned waste into affordable building blocks and created jobs for five classmates.',
        'avatar' => 'Kevin+Mutua',
    ],
    [
        'name' => 'Faith Njeri',
        'project' => 'EdTech for Rural Schools',
        'quote' => 'Before JengaFund I was stuck at the proposal stage. Funding let me ship tablets loaded with offline curriculum to two primary schools.',
        'avatar' => 'Faith+Njeri',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Dashboard - JengaFund</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link rel="stylesheet" href="../../assets/css/app.css">
    <style>
        .donor-hero {
            background: linear-gradient(135deg, #fff 0%, #fff5f5 100%);
            border: 1px solid var(--jf-border);
            border-radius: var(--jf-radius);
            padding: 36px 32px;
            margin-bottom: 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 24px;
            box-shadow: var(--jf-shadow-sm);
        }
        .donor-hero h1 { margin: 0 0 8px; font-size: 1.75rem; }
        .donor-hero p { margin: 0; color: var(--jf-text-muted); max-width: 520px; line-height: 1.6; }
        .donor-hero-stat {
            font-size: 0.88rem;
            color: var(--jf-text-muted);
            margin-top: 12px;
        }
        .donor-hero-stat strong { color: var(--jf-success); }

        .impact-section { margin-top: 8px; }
        .impact-title {
            text-align: center;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--jf-text);
            margin-bottom: 8px;
        }
        .impact-subtitle {
            text-align: center;
            color: var(--jf-text-muted);
            font-size: 0.92rem;
            margin: 0 0 28px;
        }

        .impact-slider { position: relative; padding: 0 48px; }
        .impactSwiper { width: 100%; height: auto !important; overflow: hidden; }
        .impactSwiper .swiper-wrapper { height: auto !important; align-items: stretch; }
        .impactSwiper .swiper-slide { height: auto; display: flex; }

        .impact-card {
            background: var(--jf-surface);
            border: 1px solid var(--jf-border);
            border-radius: var(--jf-radius);
            padding: 28px 24px;
            box-shadow: var(--jf-shadow-sm);
            width: 100%;
            text-align: center;
            transition: transform var(--jf-transition), box-shadow var(--jf-transition);
        }
        .impact-card:hover { transform: translateY(-4px); box-shadow: var(--jf-shadow); }

        .impact-card img {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--jf-brand);
            margin-bottom: 14px;
        }
        .impact-card .project-tag {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--jf-brand);
            background: var(--jf-brand-soft);
            padding: 4px 10px;
            border-radius: 20px;
            margin-bottom: 10px;
        }
        .impact-card h3 { margin: 0 0 12px; font-size: 1rem; color: var(--jf-text); }
        .impact-card blockquote {
            margin: 0;
            font-size: 0.9rem;
            color: var(--jf-text-muted);
            line-height: 1.65;
            font-style: italic;
        }

        .impact-pagination {
            position: relative !important;
            bottom: auto !important;
            margin-top: 20px;
            text-align: center;
        }
        .impact-pagination .swiper-pagination-bullet-active { background: var(--jf-brand) !important; }

        .impact-prev, .impact-next {
            color: var(--jf-brand) !important;
            background: var(--jf-surface);
            width: 36px !important;
            height: 36px !important;
            border-radius: 50%;
            border: 1px solid var(--jf-border);
            box-shadow: var(--jf-shadow-sm);
            top: 42%;
            margin-top: -18px;
        }
        .impact-prev::after, .impact-next::after { font-size: 14px !important; font-weight: bold; }

        @media (max-width: 640px) {
            .impact-slider { padding: 0 36px; }
            .donor-hero { padding: 24px 20px; }
        }
    </style>
</head>
<body class="jf-app">
    <div class="jf-page jf-page-wide">

        <div class="donor-hero">
            <div>
                <h1>Welcome, <?php echo $userName; ?>!</h1>
                <p>Your support turns student ideas into real projects. Browse live campaigns and help the next generation of innovators succeed.</p>
                <?php if ($activeCount > 0): ?>
                    <p class="donor-hero-stat"><strong><?php echo $activeCount; ?></strong> campaign<?php echo $activeCount === 1 ? '' : 's'; ?> accepting donations right now</p>
                <?php endif; ?>
            </div>
            <a href="campaigns.php" class="jf-btn jf-btn-brand" style="padding: 14px 28px; font-size: 1rem;">
                <i class="fa-solid fa-compass"></i> Checkout Campaigns
            </a>
        </div>

        <section class="impact-section">
            <h2 class="impact-title">Students You Help Empower</h2>
            <p class="impact-subtitle">Real stories from students whose lives changed when donors believed in their projects.</p>

            <div class="impact-slider">
                <div class="swiper impactSwiper">
                    <div class="swiper-wrapper">
                        <?php foreach ($impactStories as $story): ?>
                        <div class="swiper-slide">
                            <article class="impact-card">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($story['avatar']); ?>&background=random&size=128" alt="">
                                <span class="project-tag"><?php echo htmlspecialchars($story['project']); ?></span>
                                <h3><?php echo htmlspecialchars($story['name']); ?></h3>
                                <blockquote>&ldquo;<?php echo htmlspecialchars($story['quote']); ?>&rdquo;</blockquote>
                            </article>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="swiper-pagination impact-pagination"></div>
                <div class="swiper-button-prev impact-prev"></div>
                <div class="swiper-button-next impact-next"></div>
            </div>
        </section>

        <div style="text-align: center; margin-top: 40px;">
            <a href="campaigns.php" class="jf-btn jf-btn-outline"><i class="fa-solid fa-arrow-right"></i> Explore all campaigns</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        new Swiper('.impactSwiper', {
            slidesPerView: 1,
            spaceBetween: 24,
            loop: true,
            autoHeight: true,
            autoplay: { delay: 6000, disableOnInteraction: false },
            pagination: { el: '.impact-pagination', clickable: true },
            navigation: { nextEl: '.impact-next', prevEl: '.impact-prev' },
            breakpoints: {
                640: { slidesPerView: 1, autoHeight: true },
                768: { slidesPerView: 2, autoHeight: false },
                1024: { slidesPerView: 3, autoHeight: false }
            }
        });
    });
    </script>
</body>
</html>
