<?php
session_start();

// Redirect if not logged in or not a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    $_SESSION['error_message'] = "Unauthorized access. Please log in as a student.";
    header("Location: ../login.php");
    exit();
}

// Check for session timeout
if (time() - $_SESSION['last_activity'] > 1800) {
    session_unset();
    session_destroy();
    header("Location: ../login.php?timeout=1");
    exit();
}

$_SESSION['last_activity'] = time(); // Update activity timestamp

/** @var PDO $pdo */
$pdo = require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../components/sidebar.php'; // Include the new sidebar component
require_once __DIR__ . '/../../components/notification.php'; // Notification after nav

$userName = htmlspecialchars($_SESSION['user_name'] ?? 'User');
$userId = $_SESSION['user_id'];

// Fetch summary statistics
try {
    $stmtStats = $pdo->prepare("
        SELECT 
            COUNT(*) as total_campaigns,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_campaigns,
            SUM(funds_raised) as total_raised
        FROM campaigns 
        WHERE student_id = ?
    ");
    $stmtStats->execute([$userId]);
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    // Fetch 5 most recent campaigns
    $stmtRecent = $pdo->prepare("
        SELECT id, title, goal_amount, funds_raised, status, created_at 
        FROM campaigns 
        WHERE student_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmtRecent->execute([$userId]);
    $recentCampaigns = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Dashboard Data Error: " . $e->getMessage());
    $stats = ['total_campaigns' => 0, 'active_campaigns' => 0, 'total_raised' => 0];
    $recentCampaigns = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php require_once __DIR__ . '/../../components/favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - JengaFund</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <link rel="stylesheet" href="../../assets/css/app.css">
    <style>
        .reviews-section { margin-top: 48px; padding-bottom: 24px; }
        .reviews-title { text-align: center; font-size: 1.25rem; color: var(--jf-text); margin-bottom: 24px; font-weight: 700; }

        .reviews-slider {
            position: relative;
            padding: 0 48px;
        }

        .testimonialSwiper {
            width: 100%;
            height: auto !important;
            overflow: hidden;
        }

        .testimonialSwiper .swiper-wrapper {
            height: auto !important;
            align-items: stretch;
        }

        .testimonialSwiper .swiper-slide {
            height: auto;
            display: flex;
        }

        .testimonial-card {
            background: var(--jf-surface);
            padding: 22px;
            border-radius: var(--jf-radius);
            box-shadow: var(--jf-shadow-sm);
            text-align: center;
            border: 1px solid var(--jf-border);
            transition: transform var(--jf-transition), box-shadow var(--jf-transition);
            width: 100%;
        }
        .testimonial-card:hover { transform: translateY(-4px); box-shadow: var(--jf-shadow); }
        .testimonial-img { width: 52px; height: 52px; border-radius: 50%; margin: 0 auto 12px; object-fit: cover; border: 2px solid var(--jf-brand); }
        .testimonial-name { font-size: 0.95rem; font-weight: 700; color: var(--jf-text); margin: 5px 0; }
        .testimonial-rating { color: #ffc107; margin-bottom: 8px; font-size: 0.85rem; }
        .testimonial-text { font-size: 0.88rem; color: var(--jf-text-muted); line-height: 1.55; font-style: italic; margin: 0; }

        .testimonial-pagination {
            position: relative !important;
            bottom: auto !important;
            margin-top: 18px;
            text-align: center;
        }
        .testimonial-pagination .swiper-pagination-bullet-active { background: var(--jf-brand) !important; }

        .testimonial-prev,
        .testimonial-next {
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
        .testimonial-prev::after,
        .testimonial-next::after { font-size: 14px !important; font-weight: bold; }

        @media (max-width: 640px) {
            .reviews-slider { padding: 0 36px; }
        }
    </style>
</head>
<body class="jf-app">
    <div class="jf-page jf-page-wide">

        <div class="jf-page-header-row jf-page-header">
            <div>
                <h1>Welcome back, <?php echo $userName; ?>!</h1>
                <p>Track your project progress and funding goals here.</p>
            </div>
            <a href="create_campaign.php" class="jf-btn jf-btn-brand"><i class="fa-solid fa-plus"></i> New Campaign</a>
        </div>

        <div class="jf-stat-grid">
            <div class="jf-stat-card">
                <h3>Total Campaigns</h3>
                <p><?php echo number_format($stats['total_campaigns']); ?></p>
            </div>
            <div class="jf-stat-card">
                <h3>Active Now</h3>
                <p><?php echo number_format($stats['active_campaigns']); ?></p>
            </div>
            <div class="jf-stat-card">
                <h3>Total Raised</h3>
                <p>KES <?php echo number_format($stats['total_raised'] ?? 0, 2); ?></p>
            </div>
        </div>

        <div class="jf-panel">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:8px;">
                <h2 style="margin:0;">Recent Campaigns</h2>
                <?php if (($stats['total_campaigns'] ?? 0) > 0): ?>
                    <a href="campaigns.php" class="jf-btn jf-btn-outline jf-btn-sm">View all campaigns</a>
                <?php endif; ?>
            </div>
            <p class="jf-hint" style="margin:0 0 16px;">Your latest five projects. Open <a href="campaigns.php" style="color:var(--jf-brand);font-weight:600;">My Campaigns</a> for the full list.</p>
            <?php if (empty($recentCampaigns)): ?>
                <div class="jf-empty" style="box-shadow: none; border: none; padding: 32px;">
                    <i class="fa-solid fa-folder-open" style="color: var(--jf-text-light);"></i>
                    <p>You haven't launched any campaigns yet.</p>
                    <a href="create_campaign.php" class="jf-btn jf-btn-brand" style="margin-top: 16px; display: inline-flex;">Start your first project</a>
                </div>
            <?php else: ?>
                <div class="jf-table-wrap" style="border: none; box-shadow: none;">
                    <table class="jf-table">
                    <thead>
                        <tr>
                            <th>Project Title</th>
                            <th>Goal (KES)</th>
                            <th>Raised</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentCampaigns as $project): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($project['title']); ?></strong></td>
                                <td><?php echo number_format($project['goal_amount'], 2); ?></td>
                                <td><?php echo number_format($project['funds_raised'], 2); ?></td>
                                <td>
                                    <span class="jf-badge <?php echo ($project['status'] === 'active') ? 'jf-badge-approved' : 'jf-badge-pending'; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="campaign_view.php?id=<?= (int) $project['id'] ?>" class="jf-btn jf-btn-outline jf-btn-sm">
                                        <i class="fa-solid fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <section class="reviews-section">
            <h2 class="reviews-title">What Our Community Says</h2>
            <div class="reviews-slider">
                <div class="swiper testimonialSwiper">
                    <div class="swiper-wrapper">
                        <div class="swiper-slide">
                            <div class="testimonial-card">
                                <img src="https://ui-avatars.com/api/?name=Kell+Dawx&background=random" class="testimonial-img" alt="">
                                <h3 class="testimonial-name">Kell Dawx</h3>
                                <div class="testimonial-rating">⭐⭐⭐⭐⭐</div>
                                <p class="testimonial-text">"I absolutely loved the service. This is definitely the best informative website so far 💯"</p>
                            </div>
                        </div>
                        <div class="swiper-slide">
                            <div class="testimonial-card">
                                <img src="https://ui-avatars.com/api/?name=Lotw+Fox&background=random" class="testimonial-img" alt="">
                                <h3 class="testimonial-name">Lotw Fox</h3>
                                <div class="testimonial-rating">⭐⭐⭐⭐⭐</div>
                                <p class="testimonial-text">"Definitely would recommend this website to anyone I meet. Without it, I would be lost 😁"</p>
                            </div>
                        </div>
                        <div class="swiper-slide">
                            <div class="testimonial-card">
                                <img src="https://ui-avatars.com/api/?name=Sara+Mit&background=random" class="testimonial-img" alt="">
                                <h3 class="testimonial-name">Sara Mit</h3>
                                <div class="testimonial-rating">⭐⭐⭐⭐⭐</div>
                                <p class="testimonial-text">"The funding process is transparent and fast. Absolutely love the milestone system!"</p>
                            </div>
                        </div>
                        <div class="swiper-slide">
                            <div class="testimonial-card">
                                <img src="https://ui-avatars.com/api/?name=Jenny+Wert&background=random" class="testimonial-img" alt="">
                                <h3 class="testimonial-name">Jenny Wert</h3>
                                <div class="testimonial-rating">⭐⭐⭐⭐⭐</div>
                                <p class="testimonial-text">"My friend suggested this website and my project has never been the same since 😊"</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="swiper-pagination testimonial-pagination"></div>
                <div class="swiper-button-prev testimonial-prev"></div>
                <div class="swiper-button-next testimonial-next"></div>
            </div>
        </section>
    </div>

    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            new Swiper(".testimonialSwiper", {
                slidesPerView: 1,
                spaceBetween: 25,
                loop: true,
                autoHeight: true,
                autoplay: { delay: 5000, disableOnInteraction: false },
                pagination: { el: ".testimonial-pagination", clickable: true },
                navigation: { nextEl: ".testimonial-next", prevEl: ".testimonial-prev" },
                breakpoints: {
                    768: { slidesPerView: 2, autoHeight: false },
                    1024: { slidesPerView: 3, autoHeight: false }
                }
            });
        });
    </script>
</body>
</html>