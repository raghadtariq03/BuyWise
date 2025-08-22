<?php
require_once 'config.php'; // handles session, DB, lang, $dir, CSRF, etc.

/** Translate English month to Arabic */
function translateMonth($englishMonth)
{
    $months = [
        'January' => 'يناير',
        'February' => 'فبراير',
        'March' => 'مارس',
        'April' => 'أبريل',
        'May' => 'مايو',
        'June' => 'يونيو',
        'July' => 'يوليو',
        'August' => 'أغسطس',
        'September' => 'سبتمبر',
        'October' => 'أكتوبر',
        'November' => 'نوفمبر',
        'December' => 'ديسمبر'
    ];
    return $months[$englishMonth] ?? $englishMonth;
}

/** Format date in English or Arabic */
function formatLocalizedDate(DateTime $dateObj, $lang = 'en')
{
    if ($lang === 'ar') {
        $month = translateMonth($dateObj->format('F'));
        $time = str_replace(['am', 'pm'], ['ص', 'م'], $dateObj->format('g:i a'));
        return "$month " . $dateObj->format('j, Y') . " في $time";
    }
    return $dateObj->format('F j, Y \\a\\t g:i a');
}

function renderReplies($con, $parentID, $UserID, $dir, $depth = 1)
{
    $stmt = $con->prepare("
        SELECT c.*, u.UserName, u.Avatar, u.UserGender, u.badge,
        (SELECT COUNT(*) FROM comment_likes WHERE CommentID = c.CommentID) AS LikeCount,
        (SELECT COUNT(*) FROM comment_likes WHERE CommentID = c.CommentID AND UserID = ?) AS UserLiked
        FROM comments c
        JOIN users u ON c.UserID = u.UserID
        WHERE c.ParentCommentID = ? 
        AND c.CommentStatus = 1
        ORDER BY c.CommentDate ASC
    ");
    $stmt->bind_param("ii", $UserID, $parentID);
    $stmt->execute();
    $replies = $stmt->get_result();
    $isCompanyUser = isset($_SESSION['type']) && ($_SESSION['type'] === 'company' || $_SESSION['type'] === 3);

    if ($replies->num_rows > 0) {
        echo '<div class="nested-replies-container" id="replies-' . $parentID . '" style="display:block;">';

        while ($reply = $replies->fetch_assoc()) {
            $replyID = $reply['CommentID'];

                   
            $reportCheck = $con->prepare("SELECT 1 FROM reported_comments WHERE CommentID = ? AND UserID = ?");
            $reportCheck->bind_param("ii", $replyID, $UserID);
            $reportCheck->execute();
            $isReported = $reportCheck->get_result()->num_rows > 0;
            $reportCheck->close();

            $reply['isReported'] = $isReported;

            
            $replyText = nl2br(htmlspecialchars(stripslashes($reply['CommentText'])));

            $userName = ucwords(strtolower(htmlspecialchars($reply['UserName'])));

            $replyDate = date('F j, Y \a\t g:i a', strtotime($reply['CommentDate']));
            $likeActiveClass = $reply['UserLiked'] > 0 ? 'active' : '';
            $heartIcon = $reply['UserLiked'] > 0 ? 'fas fa-heart' : 'far fa-heart';
            $badge = htmlspecialchars($reply['badge'] ?? 'Normal');

            $avatar = getAvatarPath($reply['Avatar'] ?? '', $reply['UserGender'] ?? '');

            if ($reply['UserID'] != $UserID) {
                echo '
    <div class="nested-reply" id="reply-' . $replyID . '">
        <div class="nested-reply-header d-flex align-items-center">
            <div class="avatar-container position-relative me-2" style="width: 40px; height: 40px; margin-' . ($dir === 'rtl' ? 'left' : 'right') . ': 10px;">
                <a href="PublicProfile.php?UserID=' . $reply['UserID'] . '">
                    <img src="' . htmlspecialchars($avatar) . '" 
                         alt="' . $userName . '" 
                         class="nested-reply-avatar" 
                         style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                    <span class="badge-star badge-' . $badge . '" style="width:16px; height:16px;">
                        <i class="fas fa-star"></i>
                    </span>
                </a>
            </div>

            <div class="nested-reply-author-info">
                <a href="PublicProfile.php?UserID=' . $reply['UserID'] . '" class="nested-reply-author">' . ucwords(strtolower(htmlspecialchars($userName))) . '</a>
                <span class="nested-reply-date">' . $replyDate . '</span>
            </div>
        </div>

        <div class="nested-reply-content">' . $replyText . '</div>';

                if (!$isCompanyUser) {
                    echo '
        <div class="comment-actions reply-actions">
            <button class="like-btn ' . $likeActiveClass . '" onclick="likeComment(' . $replyID . ', ' . $UserID . ', \'like\', event)">
                <i class="' . $heartIcon . '"></i> <span class="like-count">' . $reply['LikeCount'] . '</span>
            </button>
            <button class="reply-btn" onclick="toggleReplyForm(' . $replyID . ')">
                <i class="far fa-comment"></i> ' . __('reply') . '
            </button>';
            if ($isReported) {
    echo '<button class="report-btn fw-bold"  disabled>
            <i class="fas fa-check"></i> ' . __('reported') . '
          </button>';
} else {
    echo '<button class="report-btn text-danger fw-bold" onclick="reportComment(' . $replyID . ', ' . $UserID . ', this)">
            <i class="fas fa-flag"></i> ' . __('report') . '
          </button>';
}
           echo '</div>';
                }

                echo '
        <div id="reply-form-' . $replyID . '" class="reply-form">
            <textarea class="reply-textarea" id="reply-text-' . $replyID . '" placeholder="' . __('write_your_reply') . '"></textarea>
            <div class="reply-btn-group">
                <button class="accent-btn" onclick="addReply(' . $replyID . ', ' . $UserID . ', document.getElementById(\'reply-text-' . $replyID . '\').value)">' . __('post_reply') . '</button>
                <button class="probtn" onclick="toggleReplyForm(' . $replyID . ')">' . __('cancel') . '</button>
            </div>
        </div>';
            } else {
                echo '
                <div class="nested-reply" id="reply-' . $replyID . '">
                <div class="nested-reply-header d-flex align-items-center">
                    <div class="avatar-container position-relative me-2" style="width: 40px; height: 40px;margin-' . ($dir === 'rtl' ? 'left' : 'right') . ': 10px;">
            <a href="PublicProfile.php?UserID=' . $reply['UserID'] . '">
                        <a href="PublicProfile.php?UserID=' . $reply['UserID'] . '">
                            <img src="' . htmlspecialchars($avatar) . '" 
                                alt="' . $userName . '" 
                                class="nested-reply-avatar" 
                                style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                            <span class="badge-star badge-' . $badge . '" style="width:16px; height:16px;">
                                <i class="fas fa-star"></i>
                            </span>
                        </a>
                    </div>

                    <div class="nested-reply-author-info">
                        <a href="PublicProfile.php?UserID=' . $reply['UserID'] . '" class="nested-reply-author">' . $userName . '</a>
                        <span class="nested-reply-date">' . $replyDate . '</span>
                    </div>
                </div>
                <div class="nested-reply-content">' . $replyText . '</div>
                <div class="comment-actions reply-actions">
                    <button class="like-btn ' . $likeActiveClass . '" onclick="likeComment(' . $replyID . ', ' . $UserID . ', ' . "'like'" . ', event)">
                        <i class="' . $heartIcon . '"></i> <span class="like-count">' . $reply['LikeCount'] . '</span>
                    </button>
                    <button class="reply-btn" onclick="toggleReplyForm(' . $replyID . ')">
                        <i class="far fa-comment"></i> ' . __('reply') . '
                    </button>
                    
                </div>

                <div id="reply-form-' . $replyID . '" class="reply-form">
                    <textarea class="reply-textarea" id="reply-text-' . $replyID . '" placeholder="' . __('write_your_reply') . '"></textarea>
                    <div class="reply-btn-group">
                        <button class="accent-btn" onclick="addReply(' . $replyID . ', ' . $UserID . ', document.getElementById(\'reply-text-' . $replyID . '\').value)">' . __('post_reply') . '</button>
                        <button class="probtn" onclick="toggleReplyForm(' . $replyID . ')">' . __('cancel') . '</button>
                    </div>
                </div>';
            }



            // Recursive call for nested replies
            renderReplies($con, $replyID, $UserID, $dir, $depth + 1);

            echo '</div>';
        }
        echo '</div>';
    }
}


$isCompanyUser = isset($_SESSION['type']) && ($_SESSION['type'] === 'company' || $_SESSION['type'] === 3);

$Comments = 0;
$sqlQuery_comments = $con->query("
    SELECT COUNT(CommentID) AS Count_Coumments 
    FROM comments
    WHERE " . ($isCompanyProduct ? "CproductID" : "ProductID") . " = $ProductID 
      AND CommentStatus = 1  
      AND (IsFake = 0 OR IsFake IS NULL)
");

if ($Result_comments = mysqli_fetch_assoc($sqlQuery_comments)) {
    $Comments = $Result_comments['Count_Coumments'];
}

if (!isset($_SESSION['type']) || $_SESSION['type'] != 2) {
    $UserID = 0;
} else {
    $UserID = $_SESSION['UserID'];
}



$additionalImages = [];
$sqlImages = $con->prepare("SELECT ImageName FROM product_images WHERE ProductID = ?");
$sqlImages->bind_param("i", $ProductID);
$sqlImages->execute();
$resultImages = $sqlImages->get_result();
while ($imgRow = $resultImages->fetch_assoc()) {
    $additionalImages[] = $imgRow['ImageName'];
}






$sqlQuery_comments = $con->query("
    SELECT COUNT(CommentID) AS Count_Coumments 
    FROM comments
    WHERE " . ($isCompanyProduct ? "CproductID" : "ProductID") . " = $ProductID
      AND CommentStatus = 1  
      AND (CAST(IsFake AS UNSIGNED) = 0 OR IsFake IS NULL)
");


$Comments = 0;
if ($row = $sqlQuery_comments->fetch_assoc()) {
    $Comments = $row['Count_Coumments'];
}

$minRating = isset($_REQUEST['min_rating']) && is_numeric($_REQUEST['min_rating']) ? intval($_REQUEST['min_rating']) : null;
$sort = $_REQUEST['sort'] ?? 'latest';

$isCompanyProduct = $isCompanyProduct ?? false; 


if ($isCompanyProduct) {
    $sqlComments = "
    SELECT c.*, u.UserName, u.Avatar, u.UserGender, u.badge,
           (SELECT COUNT(*) FROM comment_likes WHERE CommentID = c.CommentID) AS LikeCount,
           (SELECT COUNT(*) FROM comment_likes WHERE CommentID = c.CommentID AND UserID = ?) AS UserLiked
    FROM comments c
    JOIN users u ON c.UserID = u.UserID
    WHERE c.CproductID = ?
      AND c.ParentCommentID IS NULL 
      AND c.CommentStatus = 1
      AND (c.IsFake = 0 OR c.IsFake IS NULL)
    ";
} else {
    $sqlComments = "
    SELECT c.*, u.UserName, u.Avatar, u.UserGender, u.badge,
           (SELECT COUNT(*) FROM comment_likes WHERE CommentID = c.CommentID) AS LikeCount,
           (SELECT COUNT(*) FROM comment_likes WHERE CommentID = c.CommentID AND UserID = ?) AS UserLiked
    FROM comments c
    JOIN users u ON c.UserID = u.UserID
    WHERE c.ProductID = ?
      AND c.ParentCommentID IS NULL 
      AND c.CommentStatus = 1
      AND (c.IsFake = 0 OR c.IsFake IS NULL)
    ";
}


// Filter by star rating
if ($minRating !== null) {
    $sqlComments .= " AND c.Rating >= " . $minRating;
}

// Sort by user selection
switch ($sort) {
    case 'oldest':
        $sqlComments .= " ORDER BY c.CommentDate ASC";
        break;
    case 'liked':
        $sqlComments .= " ORDER BY LikeCount DESC";
        break;
    default:
        $sqlComments .= " ORDER BY c.CommentDate DESC";
        break;
}


// Add LIMIT only if not showing all
if (!$showAll) {
    $sqlComments .= " LIMIT 2";
}


$stmt = $con->prepare($sqlComments);
$stmt->bind_param("ii", $UserID, $ProductID);
$stmt->execute();
$resultComments = $stmt->get_result();

?>
<html>

<body>



    <!-- Reviews Section  -->
    <div class="comments-section mt-5">
        <h2><?= __('customer_reviews') ?> (<?= $Comments; ?>)</h2>

        <form id="filterForm" class="d-flex flex-wrap align-items-center gap-2 mb-4">
            <input type="hidden" name="ProductID" value="<?= $ProductID ?>">

            <label for="rating_filter" class="form-label mb-0"><?= __('filter_by_rating') ?></label>
            <select name="min_rating" id="rating_filter" class="form-select w-auto">
                <option value=""><?= __('all_ratings') ?></option>
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <option value="<?= $i ?>" <?= (isset($_GET['min_rating']) && $_GET['min_rating'] == $i) ? 'selected' : '' ?>>
                        <?= $i . ' ' . ($i == 5 ? __('stars_only') : __('stars_up')) ?>
                    </option>
                <?php endfor; ?>
            </select>

            <label for="sort_by" class="form-label mb-0 ms-3"><?= __('sort_by') ?></label>
            <select name="sort" id="sort_by" class="form-select w-auto">
                <option value="latest" <?= ($_GET['sort'] ?? '') == 'latest' ? 'selected' : '' ?>><?= __('latest') ?></option>
                <option value="oldest" <?= ($_GET['sort'] ?? '') == 'oldest' ? 'selected' : '' ?>><?= __('oldest') ?></option>
                <option value="most_liked" <?= ($_GET['sort'] ?? '') == 'most_liked' ? 'selected' : '' ?>><?= __('most_liked') ?></option>
            </select>

            <button type="submit" id="applyFilters" class="btn btn-secondary ms-2"><?= __('apply_filters') ?></button>
            <a href="#" id="resetFilters" class="btn btn-outline-secondary"><?= __('reset_filters') ?></a>

        </form>


        <div class="post-comments-wrap">
            <div class="post-comments">
                <h6 class="comments-title"><?= __('see_what_others_say') ?></h6>
                <hr>
            </div>
        </div>

        <div id="comments-container" class="comments-container">
            <?php

            // Sub-ratings (as words)
            function getSubRatingLabel($v)
            {
                if ($v >= 5)
                    return __('excellent');
                if ($v >= 4)
                    return __('very_good');
                if ($v >= 3)
                    return __('good');
                if ($v >= 2)
                    return __('fair');
                if ($v >= 1)
                    return __('poor');
                return __('not_rated');
            }
            if ($Comments > 0) {
                while ($comment = mysqli_fetch_assoc($resultComments)) {
                    $profileUserID = $comment['UserID'];
                     

                    $badgeStmt = $con->prepare("SELECT badge, points FROM users WHERE UserID = ?");
                    $badgeStmt->bind_param("i", $profileUserID);
                    $badgeStmt->execute();
                    $badgeResult = $badgeStmt->get_result();
                    $badgeData = $badgeResult->fetch_assoc();

                    $badge = $badgeData['badge'] ?? 'Normal';
                    $points = $badgeData['points'] ?? 0;

                    $commentDate = new DateTime($comment['CommentDate']);
                    $lang = $_SESSION['lang'] ?? 'en';
                    $formattedDate = formatLocalizedDate($commentDate, $lang);

                    $avatar = getAvatarPath($comment['Avatar'] ?? '', $comment['UserGender'] ?? '');

                    $likeActiveClass = $comment['UserLiked'] > 0 ? 'active' : '';
                    $commentID = $comment['CommentID'];
                    $rating = isset($comment['Rating']) ? $comment['Rating'] : 5;
                    $commentImage = !empty($comment['CommentImage']) ? $comment['CommentImage'] : '';
                    $commentUserID = $comment['UserID'];
                    $commentUserName = ucwords(strtolower(htmlspecialchars($comment['UserName'])));
                      
            $reportCheck1 = $con->prepare("SELECT 1 FROM reported_comments WHERE CommentID = ? AND UserID = ?");
            $reportCheck1->bind_param("ii", $commentID, $UserID);
            $reportCheck1->execute();
            $isReported1 = $reportCheck1->get_result()->num_rows > 0;
            $reportCheck1->close();

            $comment['isReported1'] = $isReported1;


                    echo '<article class="comment-body" id="comment-' . $commentID . '">
                        <div class="avatar-container position-relative" style="width: 50px; height: 50px;">
                      <a href="PublicProfile.php?UserID=' . $commentUserID . '">
                             <img src="' . htmlspecialchars($avatar) . '" 
                     alt="' . $commentUserName . '"
                     class="user-avatar"
                     style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                           <span class="badge-star badge-' . $badge . '" style="width:16px; height:16px;">
                                      <i class="fas fa-star small"></i>
                         </span>
                           </a>
                     </div>

                      <div class="comment-wrap">
                        <div class="comment-author d-flex align-items-center gap-2">
                        <a href="PublicProfile.php?UserID=' . $commentUserID . '" class="comment-user-link small fw-bold">'
                        . $commentUserName . '</a>
                      <div class="comment-stars small">';
                    for ($i = 1; $i <= 5; $i++) {
                        echo $i <= $rating ? '<i class="fas fa-star text-warning small"></i>' : '<i class="far fa-star text-muted small"></i>';
                    }
                    echo '</div>
                     </div>
                     <div class="comment-meta small text-muted">' . $formattedDate . '</div>';



                    echo '<div class="subratings-text small text-muted mt-1">';
                    if (isset($comment['QualityRating']))
                        echo __('quality') . ': ' . getSubRatingLabel($comment['QualityRating']);
                    echo '</div>';


                    $cleanedText = nl2br(htmlspecialchars(stripslashes($comment['CommentText'])));
                    echo '<div class="comment-content mt-2"><p>' . $cleanedText . '</p>


                        ';
                    echo '<span id="comment-label-' . $commentID . '" class="badge"></span>';
                    if (!empty($commentImage)) {
                        echo '<div class="comment-image-container mt-2">
                   <img src="uploads/comments/' . htmlspecialchars($commentImage) . '" 
                  alt="Review Image" 
                   class="comment-image"
                  style="max-width: 200px; max-height: 200px; border-radius: 8px; object-fit: cover; cursor: pointer;"
                    onclick="openImageModal(\'uploads/comments/' . htmlspecialchars($commentImage) . '\')">
                 </div>';
                    }

                    // Check if the comment is not written by the current user
                    if ($profileUserID != $UserID) {
                        echo '</div>';

                        if (!$isCompanyUser) {
                            echo '
        <div class="comment-actions mt-2">
            <button class="like-btn ' . $likeActiveClass . '" onclick="likeComment(' . $commentID . ', ' . $UserID . ', \'like\', event)">
                <i class="' . ($comment['UserLiked'] > 0 ? 'fas' : 'far') . ' fa-heart"></i>
                <span class="like-count">' . $comment['LikeCount'] . '</span>
            </button>
            <button class="reply-btn" onclick="toggleReplyForm(' . $commentID . ')">
                <i class="far fa-comment"></i> ' . __('reply') . '
             </button>
            ';
            if ($isReported1) {
    echo '<button class="report-btn fw-bold" style="color: #e29578" disabled>
            <i class="fas fa-check"></i> ' . __('reported') . '
          </button>';
} else {
    echo '<button class="report-btn text-danger fw-bold" onclick="reportComment(' . $commentID . ', ' . $UserID . ', this)">
            <i class="fas fa-flag"></i> ' . __('report') . '
          </button>';
}
           echo '</div>';
                        }

                        echo '
    <div id="reply-form-' . $commentID . '" class="reply-form">
        <textarea class="reply-textarea" id="reply-text-' . $commentID . '" rows="3" placeholder="' . __('write_your_reply') . '"></textarea>
        <div class="reply-btn-group">
            <button class="accent-btn" onclick="addReply(' . $commentID . ', ' . $UserID . ', document.getElementById(\'reply-text-' . $commentID . '\').value)">' . __('post_reply') . '</button>
            <button class="probtn" onclick="toggleReplyForm(' . $commentID . ')">' . __('cancel') . '</button>
        </div>
    </div>';
                    } else {
                        echo '</div>

            <div class="comment-actions mt-2">
                <button class="like-btn ' . $likeActiveClass . '" onclick="likeComment(' . $commentID . ', ' . $UserID . ',' . "'like'" . ', event)">
                    <i class="' . ($comment['UserLiked'] > 0 ? 'fas' : 'far') . ' fa-heart"></i>
                    <span class="like-count">' . $comment['LikeCount'] . '</span>
                </button>
                <button class="reply-btn" onclick="toggleReplyForm(' . $commentID . ')">
                    <i class="far fa-comment"></i> ' . __('reply') . '
                </button>
                
            </div>

            <div id="reply-form-' . $commentID . '" class="reply-form">
                <textarea class="reply-textarea" id="reply-text-' . $commentID . '" rows="3" placeholder="' . __('write_your_reply') . '"></textarea>
                <div class="reply-btn-group">
                    <button class="accent-btn" onclick="addReply(' . $commentID . ', ' . $UserID . ', document.getElementById(\'reply-text-' . $commentID . '\').value)">' . __('post_reply') . '</button>
                    <button class="probtn" onclick="toggleReplyForm(' . $commentID . ')">' . __('cancel') . '</button>
                </div>
            </div>';
                    }

                    $stmtCount = $con->prepare("SELECT COUNT(*) as replyCount FROM comments WHERE ParentCommentID = ?");
                    $stmtCount->bind_param("i", $commentID);
                    $stmtCount->execute();
                    $replyData = $stmtCount->get_result()->fetch_assoc();
                    $replyCount = $replyData['replyCount'];

                    if ($replyCount > 0) {
                        echo '<div class="nested-replies-section">
                    <button class="toggle-replies-btn" onclick="toggleReplies(' . $commentID . ')">
                        <i class="fas fa-caret-down"></i>
                        <span class="reply-count-text" data-reply-count="' . $replyCount . '">' . $replyCount . ' ' . ($replyCount == 1 ? 'reply' : 'replies') . '</span>
                    </button>
                    <div id="replies-' . $commentID . '" class="nested-replies-container">';
                        renderReplies($con, $commentID, $UserID, $dir);
                        echo '</div></div>';
                    }

                    echo '</div></article>';
                }
            } else {
                echo '<div class="no-comments">' . __('no_reviews') . ' ' . __('be_first_to_review') . '</div>';
            }
            ?>
        </div>


    </div>

    <script>
        // let productID = <?= json_encode($ProductID) ?>;
        let isCompanyProduct = <?= json_encode($isCompanyProduct ?? false) ?>;


        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            const commentID = urlParams.get("comment");

            if (commentID) {
                const commentElement = document.getElementById("comment-" + commentID);
                if (commentElement) {
                    commentElement.scrollIntoView({
                        behavior: "smooth",
                        block: "center"
                    });

                   
                    commentElement.style.backgroundColor = "#fff3cd"; 
                    commentElement.style.transition = "background-color 0.5s ease";

                    setTimeout(() => {
                        commentElement.style.backgroundColor = "";
                    }, 3000);
                }
            }

            const replyID = urlParams.get("reply");

            if (replyID) {
                const replyElement = document.getElementById("reply-" + replyID);
                if (replyElement) {
                    replyElement.scrollIntoView({
                        behavior: "smooth",
                        block: "center"
                    });

                    replyElement.style.backgroundColor = "#fff3cd"; 
                    replyElement.style.transition = "background-color 0.5s ease";

                    setTimeout(() => {
                        replyElement.style.backgroundColor = "";
                    }, 3000);
                }

                
                const repliesContainer = document.getElementById("replies-" + commentID);
                if (repliesContainer) {
                    repliesContainer.style.display = "block";
                }
            }
        });
    </script>



</body>
<script>
    function previewCommentImage(input) {
        const previewContainer = document.getElementById('image-preview');
        previewContainer.innerHTML = ''; 

        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                previewContainer.appendChild(img);
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    document.addEventListener("DOMContentLoaded", () => {

        const description = <?php echo json_encode($product['ProductDescription']); ?>;
        const labelEl = document.getElementById("review-label");

       
        function isMostlyEnglish(text) {
            const words = text.trim().split(/\s+/);
            const englishWords = words.filter(word => /^[a-zA-Z]+$/.test(word));
            return words.length > 0 && (englishWords.length / words.length) >= 0.5;
        }

        if (!isMostlyEnglish(description)) {
            if (labelEl) {
                labelEl.textContent = "Unverified Review";
                labelEl.classList.remove("badge-danger", "badge-success");
                labelEl.classList.add("badge-warning");
                labelEl.style.display = "inline-block";
            }
            console.log("⏭️ Skipped non-English description:", description);
          
            evaluateComments();
            return; 
        }


        console.log("📤 Sending to AI:", description);




        if (description && description.trim() !== "" && labelEl) {
            fetch("http://127.0.0.1:5000/predict", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        text: description
                    }) // 
                })
                .then(r => r.json())
                .then(data => {
                    labelEl.textContent = data.prediction === "fake" ? "Fake Review" : "Authentic Review";
                    labelEl.classList.add(data.prediction === "fake" ? "badge-danger" : "badge-success");
                    labelEl.style.display = "inline-block";
                })
                .catch(() => {
                    labelEl.style.display = "none";
                });
        }

      



    
        function evaluateComments() {
            document.querySelectorAll("#comments-container > .comment-body").forEach(article => {
                const id = article.id.split("-")[1];
                const contentEl = article.querySelector(".comment-content p");
                const labelEl = document.getElementById(`comment-label-${id}`);

                if (!contentEl || !contentEl.textContent.trim()) {
                    if (labelEl) labelEl.style.display = "none";
                    return;
                }

                const text = contentEl.textContent.trim();

              
                if (!isMostlyEnglish(text)) {
                    if (labelEl) {
                        labelEl.textContent = "Unverified";
                        labelEl.classList.remove("badge-danger", "badge-success");
                        labelEl.classList.add("badge-warning");
                        labelEl.style.display = "inline-block";
                    }
                    console.log("⏭️ Skipped non-English comment:", text);
                    return;
                }

                console.log("📤 Sending to AI:", text);

                fetch("http://127.0.0.1:5000/predict", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify({
                            text: text
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (labelEl) {
                            labelEl.textContent = data.prediction === "fake" ? "Fake" : "Authentic";
                            labelEl.classList.remove("badge-warning");
                            labelEl.classList.add(data.prediction === "fake" ? "badge-danger" : "badge-success");
                            labelEl.style.display = "inline-block";
                        }
                    })
                    .catch(err => {
                        if (labelEl) labelEl.style.display = "none";
                        console.error("❌ AI error for text:", text, err?.message || err);
                    });
            });
        }

      
        if (labelEl) labelEl.style.display = "none"; 

        evaluateComments(); 
    });



   
    document.getElementById('filterForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const form = e.target;
        const formData = new FormData(form);
        formData.append('ajax', '1');

        fetch('Products.php?ProductID=' + productID, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newComments = doc.getElementById('comments-container');
                document.getElementById('comments-container').innerHTML = newComments.innerHTML;

                const params = new URLSearchParams(new FormData(form));
                window.history.replaceState({}, '', form.action + '?' + params.toString());

                evaluateComments(); 
            })
            .catch(error => {
                console.error("Apply AJAX Error:", error);
            });
    });


    
    document.getElementById('resetFilters').addEventListener('click', function(e) {
        e.preventDefault();

        document.getElementById('rating_filter').selectedIndex = 0;
        document.getElementById('sort_by').selectedIndex = 0;

        const formData = new FormData();
        formData.append('ProductID', productID);
        formData.append('ajax', '1');

        fetch('Products.php?ProductID=' + productID, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newComments = doc.getElementById('comments-container');
                document.getElementById('comments-container').innerHTML = newComments.innerHTML;
                window.history.replaceState({}, '', 'Products.php?ProductID=' + productID);
                evaluateComments(); // إعادة التقييم بعد تحميل جديد
            })
            .catch(error => {
                console.error("Reset AJAX Error:", error);
            });
    });

    function reportComment(commentID, userID) {
        if (userID == 0) {
            showPopup("<?= __('login_required') ?>", "<?= __('please_login_to_report') ?>", function() {
                window.location.href = 'login.php';
            });
            return;
        }

        
        const existing = document.getElementById("report-popup-overlay");
        if (existing) existing.remove();

      
        const popupHTML = `
    <div class="report-popup-overlay" id="report-popup-overlay">
        <div class="report-popup" id="report-popup">
            <button class="report-popup-close" onclick="closeReportPopup()">
                <i class="fas fa-times"></i>
            </button>
            
            <div class="report-popup-header">
                <div class="report-popup-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3 class="report-popup-title">Report Comment</h3>
                <p class="report-popup-subtitle">Help us maintain a safe community</p>
            </div>
            
            <div class="report-popup-body">
                <label class="report-reason-label" for="report-reason">
                    What's wrong with this comment?
                </label>
                <select id="report-reason" class="report-reason-select">
                    <option value="">Select a reason...</option>
                    <option value="Spam">🚫 Spam or Advertising</option>
                    <option value="Offensive">😤 Offensive Language</option>
                    <option value="Fake">🚨 Fake or Misleading Content</option>
                    <option value="Harassment">😠 Harassment or Bullying</option>
                    <option value="Inappropriate">⚠️ Inappropriate Content</option>
                    <option value="Other">🔍 Other</option>
                </select>
            </div>
            
            <div class="report-popup-actions">
                <button class="report-btn-secondary" onclick="closeReportPopup()">
                    Cancel
                </button>
                <button class="report-btn-primary" id="submit-report-btn" onclick="submitReport(${commentID}, ${userID})">
                    <span class="btn-text">Submit Report</span>
                </button>
            </div>
        </div>
    </div>
    `;

       
        document.body.insertAdjacentHTML('beforeend', popupHTML);

        // Trigger animation
        setTimeout(() => {
            document.getElementById("report-popup-overlay").classList.add('active');
        }, 10);

        
        document.addEventListener('keydown', handleEscapeKey);

       
        document.body.style.overflow = 'hidden';
    }

    function closeReportPopup() {
        const overlay = document.getElementById("report-popup-overlay");
        if (overlay) {
            overlay.classList.remove('active');

           
            setTimeout(() => {
                overlay.remove();
                document.body.style.overflow = '';
                document.removeEventListener('keydown', handleEscapeKey);
            }, 300);
        }
    }

    function handleEscapeKey(event) {
        if (event.key === 'Escape') {
            closeReportPopup();
        }
    }

    function submitReport(commentID, userID) {
        const reasonSelect = document.getElementById("report-reason");
        const submitBtn = document.getElementById("submit-report-btn");
        const reason = reasonSelect.value;

        
        if (!reason) {
            reasonSelect.style.borderColor = '#ff6b6b';
            reasonSelect.style.boxShadow = '0 0 0 3px rgba(255, 107, 107, 0.1)';

            
            setTimeout(() => {
                reasonSelect.style.borderColor = '#e0e0e0';
                reasonSelect.style.boxShadow = 'none';
            }, 3000);

          
            showErrorMessage("Please select a reason before submitting.");
            return;
        }

        
        submitBtn.classList.add('report-btn-loading');
        submitBtn.querySelector('.btn-text').textContent = 'Submitting...';
        submitBtn.disabled = true;

        // Submit the report
        fetch("ReportComment.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: `CommentID=${commentID}&Reason=${encodeURIComponent(reason)}&UserID=${userID}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                closeReportPopup();

                if (data.status === "success") {
                    showSuccessMessage("Report submitted successfully! Thank you for helping us maintain a safe community.");

                    const reportBtn = document.querySelector(`[onclick*="reportComment(${commentID}"]`);
                    if (reportBtn) {
                        reportBtn.style.opacity = '0.5';
                        reportBtn.style.pointerEvents = 'none';
                        reportBtn.innerHTML = '<i class="fas fa-check"></i> Reported';
                    }
                } else {
                    showErrorMessage(data.message || "Something went wrong. Please try again.");
                }
            })
            .catch(error => {
                console.error('Report submission error:', error);
                closeReportPopup();
                showErrorMessage("Network error. Please check your connection and try again.");
            })
            .finally(() => {
                
                if (submitBtn) {
                    submitBtn.classList.remove('report-btn-loading');
                    submitBtn.querySelector('.btn-text').textContent = 'Submit Report';
                    submitBtn.disabled = false;
                }
            });
    }

    
    function showSuccessMessage(message) {
        const successPopup = `
        <div class="report-popup-overlay active" id="success-popup">
            <div class="report-popup">
                <div class="report-popup-header">
                    <div class="report-popup-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                        <i class="fas fa-check"></i>
                    </div>
                    <h3 class="report-popup-title">Thank You!</h3>
                    <p class="report-popup-subtitle">${message}</p>
                </div>
                <div class="report-popup-actions">
                    <button class="report-btn-primary" onclick="closeSuccessPopup()" style="background: linear-gradient(135deg, #10b981, #059669);">
                        OK
                    </button>
                </div>
            </div>
        </div>
    `;

        document.body.insertAdjacentHTML('beforeend', successPopup);
        document.body.style.overflow = 'hidden';
    }

    
    function showErrorMessage(message) {
        const errorPopup = `
        <div class="report-popup-overlay active" id="error-popup">
            <div class="report-popup">
                <div class="report-popup-header">
                    <div class="report-popup-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <h3 class="report-popup-title">Oops!</h3>
                    <p class="report-popup-subtitle">${message}</p>
                </div>
                <div class="report-popup-actions">
                    <button class="report-btn-primary" onclick="closeErrorPopup()">
                        Try Again
                    </button>
                </div>
            </div>
        </div>
    `;

        document.body.insertAdjacentHTML('beforeend', errorPopup);
        document.body.style.overflow = 'hidden';
    }

    function closeSuccessPopup() {
        const popup = document.getElementById("success-popup");
        if (popup) {
            popup.classList.remove('active');
            setTimeout(() => {
                popup.remove();
                document.body.style.overflow = '';
            }, 300);
        }
    }

    function closeErrorPopup() {
        const popup = document.getElementById("error-popup");
        if (popup) {
            popup.classList.remove('active');
            setTimeout(() => {
                popup.remove();
                document.body.style.overflow = '';
            }, 300);
        }
    }

   
    document.addEventListener('click', function(event) {
        const overlay = document.getElementById("report-popup-overlay");
        if (overlay && event.target === overlay) {
            closeReportPopup();
        }

        const successPopup = document.getElementById("success-popup");
        if (successPopup && event.target === successPopup) {
            closeSuccessPopup();
        }

        const errorPopup = document.getElementById("error-popup");
        if (errorPopup && event.target === errorPopup) {
            closeErrorPopup();
        }
    });


    function toggleReplies(commentID) {
        const repliesContainer = document.getElementById('replies-' + commentID);
        const toggleButton = repliesContainer.parentElement.querySelector('.toggle-replies-btn');
        const replyCountSpan = toggleButton.querySelector('.reply-count-text');
        const replyCount = replyCountSpan.dataset.replyCount;

        if (repliesContainer.style.display === 'block') {
            repliesContainer.style.display = 'none';
            toggleButton.classList.remove('active');
            replyCountSpan.innerText = replyCount + ' ' + (replyCount == 1 ? 'reply' : 'replies');
        } else {
            repliesContainer.style.display = 'block';
            toggleButton.classList.add('active');
            replyCountSpan.innerText = 'Hide replies';
        }
    }


    // Function to toggle reply form visibility
    function toggleReplyForm(commentID) {
        const replyForm = document.getElementById('reply-form-' + commentID);

        // Hide all other reply forms first
        document.querySelectorAll('.reply-form').forEach(form => {
            if (form.id !== 'reply-form-' + commentID) {
                form.style.display = 'none';
            }
        });

        if (replyForm.style.display === 'block') {
            replyForm.style.display = 'none';
        } else {
            replyForm.style.display = 'block';
            document.getElementById('reply-text-' + commentID).focus();
        }
    }
    const translations = {
        reply: <?= json_encode(__('reply')) ?>,
        report: <?= json_encode(__('report')) ?>,
        post_reply: <?= json_encode(__('post_reply')) ?>,
        cancel: <?= json_encode(__('cancel')) ?>,
        write_your_reply: <?= json_encode(__('write_your_reply')) ?>
    };

    function addReply(commentID, userID, replyText) {
        if (userID == 0) {
            window.location.href = 'login.php';
            return;
        }

        if (replyText.trim() === "") return;

        fetch('AddReply.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: new URLSearchParams({
                    CommentID: commentID,
                    UserID: userID,
                    ProductID: productID,
                    isCompanyProduct: isCompanyProduct ? '1' : '0',
                    CommentText: replyText
                })
            })
            .then(res => {
                if (!res.ok) throw new Error("Server returned status " + res.status);
                return res.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    const reply = data.reply;

                    let repliesContainer = document.getElementById('replies-' + commentID);
                    if (!repliesContainer) {
                        const parentEl = document.getElementById('comment-' + commentID) || document.getElementById('reply-' + commentID);
                        repliesContainer = document.createElement('div');
                        repliesContainer.id = 'replies-' + commentID;
                        repliesContainer.classList.add('nested-replies-container');
                        repliesContainer.style.display = 'block';

                        if (document.documentElement.dir === 'rtl') {
                            repliesContainer.style.marginRight = '40px';
                            repliesContainer.style.marginLeft = '0';
                            repliesContainer.style.borderRight = '2px solid var(--accent-light)';
                            repliesContainer.style.borderLeft = 'none';
                        }



                        let wrapper = document.createElement('div');
                        wrapper.classList.add('nested-replies-section');
                        wrapper.appendChild(repliesContainer);

                        const actionsContainer = parentEl.querySelector('.comment-actions');
                        if (actionsContainer) {
                            actionsContainer.insertAdjacentElement('afterend', wrapper);
                        } else {
                            parentEl.appendChild(wrapper); 
                        }


                    } else {
                        repliesContainer.style.display = 'block';
                    }

                    const replyHTML = `
<div class="nested-reply animate-reply" id="reply-${reply.CommentID}">
    <div class="nested-reply-header d-flex align-items-center">
        <div class="avatar-container position-relative" style="width: 40px; height: 40px; margin-' . ($dir === 'rtl' ? 'left' : 'right') . ': 10px;">
            <a href="PublicProfile.php?UserID=${reply.UserID}">
                <img src="${reply.Avatar}" alt="${reply.UserName}"
                     class="nested-reply-avatar"
                     style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                <span class="badge-star badge-${reply.badge}" style="width:16px; height:16px;">
                    <i class="fas fa-star"></i>
                </span>
            </a>
        </div>
        <div class="nested-reply-author-info">
            <a href="PublicProfile.php?UserID=${reply.UserID}" class="nested-reply-author">${reply.UserName}</a>
            <span class="nested-reply-date">${reply.CommentDate}</span>
        </div>
    </div>
    <div class="nested-reply-content">${reply.CommentText.replace(/\r?\n/g, "<br>")}</div>

    <div class="comment-actions reply-actions">
        <button class="like-btn" onclick="likeComment(${reply.CommentID}, ${userID}, 'like', event)">
            <i class="far fa-heart"></i> <span class="like-count">0</span>
        </button>
        <button class="reply-btn" onclick="toggleReplyForm(${reply.CommentID})">
            <i class="far fa-comment"></i> ${translations.reply}
        </button>
       <!-- Show the report button only if the userID is different from the reply's UserID -->
        ${userID !== reply.UserID ? `
            <button class="report-btn" onclick="reportComment(${reply.CommentID}, ${userID})">
                <i class="fas fa-flag"></i> ${translations.report}
            </button>
        ` : ''}
    </div>
    <div id="reply-form-${reply.CommentID}" class="reply-form" >
        <textarea class="reply-textarea" id="reply-text-${reply.CommentID}" placeholder="${translations.write_your_reply}"></textarea>
        <div class="reply-btn-group">
            <button class="accent-btn" onclick="addReply(${reply.CommentID}, ${userID}, document.getElementById('reply-text-${reply.CommentID}').value)">
                ${translations.post_reply}
            </button>
            <button class="probtn" onclick="toggleReplyForm(${reply.CommentID})">
                ${translations.cancel}
            </button>
        </div>
    </div>
</div>`;

                    repliesContainer.insertAdjacentHTML('beforeend', replyHTML);

                    const replyForm = document.getElementById('reply-form-' + commentID);
                    const replyTextarea = document.getElementById('reply-text-' + commentID);
                    if (replyForm) replyForm.style.display = 'none';
                    if (replyTextarea) replyTextarea.value = '';

                    const replyCountSpan = document.querySelector(`#comment-${commentID} .reply-count-text`);
                    if (replyCountSpan) {
                        let current = parseInt(replyCountSpan.dataset.replyCount || "0");
                        current += 1;
                        replyCountSpan.dataset.replyCount = current;
                        replyCountSpan.textContent = current + (current === 1 ? " reply" : " replies");
                    }

                    const newReplyEl = document.getElementById('reply-' + reply.CommentID);
                    if (newReplyEl) {
                        newReplyEl.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                        newReplyEl.style.backgroundColor = '#fff3cd';
                        setTimeout(() => {
                            newReplyEl.style.backgroundColor = '';
                        }, 2000);
                    }
                } else {
                    showErrorMessage("Reply failed: " + (data.message || "Unknown error"));
                }
            })
            .catch(err => {
                console.error("Reply error:", err);
                showErrorMessage("Reply failed due to server error.");
            });
    }
</script>

</html>