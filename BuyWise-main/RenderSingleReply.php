<?php
function generateReplyHTML($replyID, $UserID) {
    global $con;

    $stmt = $con->prepare("
        SELECT c.*, u.UserName, u.Avatar, u.UserGender, u.badge,
            (SELECT COUNT(*) FROM comment_likes WHERE CommentID = c.CommentID) AS LikeCount,
            (SELECT COUNT(*) FROM comment_likes WHERE CommentID = c.CommentID AND UserID = ?) AS UserLiked
        FROM comments c
        JOIN users u ON c.UserID = u.UserID
        WHERE c.CommentID = ?
    ");
    $stmt->bind_param("ii", $UserID, $replyID);
    $stmt->execute();
    $reply = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$reply) return '';

    // Sanitize and prepare data
    $replyText = nl2br(htmlspecialchars($reply['CommentText']));
    $userName = htmlspecialchars($reply['UserName']);
    $replyDate = date('F j, Y \a\t g:i a', strtotime($reply['CommentDate']));
    $badge = htmlspecialchars($reply['badge'] ?? 'Normal');
    $gender = strtolower($reply['UserGender'] ?? '');
    $replyID = (int)$reply['CommentID'];
    $userID = (int)$reply['UserID'];
    $likeClass = $reply['UserLiked'] > 0 ? 'active' : '';
    $heartIcon = $reply['UserLiked'] > 0 ? 'fas fa-heart' : 'far fa-heart';

    // Avatar fallback
    $avatar = !empty($reply['Avatar']) && file_exists("uploads/avatars/{$reply['Avatar']}")
        ? "uploads/avatars/" . htmlspecialchars($reply['Avatar'])
        : ($gender === 'female' ? 'img/FemDef.png' : 'img/MaleDef.png');

    ob_start(); ?>
    <div class="nested-reply" id="reply-<?= $replyID ?>">
        <div class="nested-reply-header d-flex align-items-center">
            <div class="avatar-container position-relative me-2" style="width: 40px; height: 40px;">
                <a href="PublicProfile.php?UserID=<?= $userID ?>">
                    <img src="<?= $avatar ?>" alt="<?= $userName ?>" class="nested-reply-avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                    <span class="badge-star badge-<?= $badge ?>"><i class="fas fa-star"></i></span>
                </a>
            </div>
            <div class="nested-reply-author-info">
                <a href="PublicProfile.php?UserID=<?= $userID ?>" class="nested-reply-author"><?= $userName ?></a>
                <span class="nested-reply-date"><?= $replyDate ?></span>
            </div>
        </div>
        <div class="nested-reply-content"><?= $replyText ?></div>
        <div class="comment-actions reply-actions">
            <button class="like-btn <?= $likeClass ?>" onclick="likeComment(<?= $replyID ?>, <?= $UserID ?>, 'like', event)">
                <i class="<?= $heartIcon ?>"></i> <span class="like-count"><?= $reply['LikeCount'] ?></span>
            </button>
            <button class="reply-btn" onclick="toggleReplyForm(<?= $replyID ?>)">
                <i class="far fa-comment"></i> <?= __('reply') ?>
            </button>
            <button class="report-btn" onclick="reportComment(<?= $replyID ?>, <?= $UserID ?>)">
                <i class="fas fa-flag"></i> <?= __('report') ?>
            </button>
        </div>
        <div id="reply-form-<?= $replyID ?>" class="reply-form" style="display:none;">
            <textarea class="reply-textarea" id="reply-text-<?= $replyID ?>" placeholder="<?= __('write_your_reply') ?>"></textarea>
            <div class="reply-btn-group">
                <button class="accent-btn" onclick="addReply(<?= $replyID ?>, <?= $UserID ?>, document.getElementById('reply-text-<?= $replyID ?>').value)"><?= __('post') ?></button>
                <button class="probtn" onclick="toggleReplyForm(<?= $replyID ?>)"><?= __('cancel') ?></button>
            </div>
        </div>
    </div>
    <?php return ob_get_clean();
}
?>
