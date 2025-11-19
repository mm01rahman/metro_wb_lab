<?php
use App\Core\Session;
$title = 'Posts | AuthBoard';
ob_start();
?>

<style>
.post-form {
    background: #f9fafb;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 24px;
    border: 1px solid #e5e7eb;
}
.post-form textarea {
    width: 100%;
    min-height: 100px;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-family: inherit;
    font-size: 14px;
    resize: vertical;
    box-sizing: border-box;
}
.post-form button {
    margin-top: 12px;
}
.post-form .file-input {
    margin-top: 12px;
}
.posts-container {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.post-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 16px;
    transition: box-shadow 0.2s;
}
.post-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.post-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}
.post-meta {
    display: flex;
    align-items: center;
    gap: 12px;
}
.post-author {
    font-weight: 600;
    color: #374151;
}
.post-time {
    font-size: 12px;
    color: #9ca3af;
}
.post-actions {
    display: flex;
    gap: 8px;
}
.post-actions button {
    background: #2563eb;
    color: #fff;
    border: none;
    padding: 6px 10px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
}
.post-actions button:hover {
    background: #1e40af;
}
.post-content {
    color: #1f2937;
    line-height: 1.6;
    white-space: pre-wrap;
    word-wrap: break-word;
}
.post-image {
    margin-top: 12px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
    background: #f8fafc;
}
.post-image img {
    display: block;
    width: 100%;
    height: auto;
}
.loading {
    text-align: center;
    padding: 20px;
    color: #6b7280;
}
.no-more {
    text-align: center;
    padding: 20px;
    color: #9ca3af;
    font-size: 14px;
}
.message {
    padding: 12px 16px;
    border-radius: 6px;
    margin-bottom: 16px;
}
.message.success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #6ee7b7;
}
.message.error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}
.modal {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    padding: 16px;
}
.modal.open {
    display: flex;
}
.modal-content {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    width: min(480px, 100%);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}
.modal-content h3 {
    margin-top: 0;
    margin-bottom: 12px;
}
.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 12px;
}
.modal-actions .secondary {
    background: #e5e7eb;
    color: #374151;
}
.modal .message {
    margin-bottom: 12px;
}
.current-image {
    margin-top: 8px;
    color: #6b7280;
    font-size: 13px;
}
</style>

<?php if (Session::get('success')): ?>
    <div class="message success">
        <?= htmlspecialchars(Session::get('success')) ?>
        <?php Session::remove('success'); ?>
    </div>
<?php endif; ?>

<?php if (Session::get('error')): ?>
    <div class="message error">
        <?= htmlspecialchars(Session::get('error')) ?>
        <?php Session::remove('error'); ?>
    </div>
<?php endif; ?>

<h2>Posts</h2>

<div class="post-form">
    <form method="POST" action="/posts" id="postForm" enctype="multipart/form-data">
        <textarea name="content" placeholder="What's on your mind, <?= htmlspecialchars($user['name']) ?>?" required></textarea>
        <input type="file" name="image" accept="image/*">
        <button type="submit">Post</button>
    </form>
</div>

<div class="posts-container" id="postsContainer">
    <div class="loading">Loading posts...</div>
</div>
<div class="modal" id="editModal">
    <div class="modal-content">
        <h3>Edit post</h3>
        <div class="message error" id="editError" style="display: none;"></div>
        <form id="editForm">
            <input type="hidden" name="id" id="editPostId">
            <textarea name="content" id="editContent" required></textarea>
            <input type="file" name="image" accept="image/*">
            <div class="current-image" id="currentImageInfo"></div>
            <div class="modal-actions">
                <button type="button" class="secondary" id="cancelEdit">Cancel</button>
                <button type="submit">Save changes</button>
            </div>
        </form>
    </div>
</div>

<script>
const currentUserId = <?= (int)$user['id'] ?>;
let currentPage = 1;
let isLoading = false;
let hasMore = true;
const postsById = new Map();

const editModal = document.getElementById('editModal');
const editForm = document.getElementById('editForm');
const editContent = document.getElementById('editContent');
const editPostId = document.getElementById('editPostId');
const editError = document.getElementById('editError');
const currentImageInfo = document.getElementById('currentImageInfo');
const cancelEditBtn = document.getElementById('cancelEdit');

function generatePostHtml(post, timeAgo, imageHtml, actionsHtml) {
    return `
        <div class="post-header">
            <span class="post-author">${escapeHtml(post.user_name)}</span>
            <div style="display: flex; align-items: center; gap: 8px;">
                <span class="post-time">${timeAgo}</span>
                ${actionsHtml}
            </div>
        </div>
        <div class="post-content">${escapeHtml(post.content)}</div>
        ${imageHtml}
    `;
}

function attachEditHandler(card, post) {
    const editBtn = card.querySelector('.edit-btn');
    if (editBtn) {
        editBtn.addEventListener('click', () => openEditDialog(post.id));
    }
}

function buildPostCard(post) {
    postsById.set(post.id, post);
    const postCard = document.createElement('div');
    postCard.className = 'post-card';
    postCard.dataset.postId = post.id;

    const postDate = new Date(post.created_at);
    const timeAgo = getTimeAgo(postDate);

    const rawImagePath = (post.image_url || post.image_path || '').trim();
    const imageHtml = rawImagePath !== ''
        ? `<div class="post-image"><img src="${escapeHtml(rawImagePath)}" alt="Post image" loading="lazy"></div>`
        : '';

    const actionsHtml = post.user_id === currentUserId
        ? `<div class="post-actions"><button type="button" class="edit-btn" data-post-id="${post.id}">Edit</button></div>`
        : '';

    postCard.innerHTML = generatePostHtml(post, timeAgo, imageHtml, actionsHtml);
    attachEditHandler(postCard, post);
    return postCard;
}

function updatePostCard(post) {
    const card = document.querySelector(`[data-post-id="${post.id}"]`);
    if (!card) return;

    postsById.set(post.id, post);

    const postDate = new Date(post.created_at);
    const timeAgo = getTimeAgo(postDate);
    const rawImagePath = (post.image_url || post.image_path || '').trim();
    const imageHtml = rawImagePath !== ''
        ? `<div class="post-image"><img src="${escapeHtml(rawImagePath)}" alt="Post image" loading="lazy"></div>`
        : '';
    const actionsHtml = post.user_id === currentUserId
        ? `<div class="post-actions"><button type="button" class="edit-btn" data-post-id="${post.id}">Edit</button></div>`
        : '';

    card.innerHTML = generatePostHtml(post, timeAgo, imageHtml, actionsHtml);
    attachEditHandler(card, post);
}


async function loadPosts() {
    if (isLoading || !hasMore) return;

    isLoading = true;

    try {
        const response = await fetch(`/api/posts?page=${currentPage}`);
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Failed to load posts');
        }

        if (data.posts.length > 0) {
            const container = document.getElementById('postsContainer');

            if (currentPage === 1) {
                container.innerHTML = '';
            }
            

            data.posts.forEach(post => {
                const postCard = buildPostCard(post);
                container.appendChild(postCard);
            });
            

            hasMore = data.hasMore;
            currentPage++;
            

            if (!hasMore) {
                const noMore = document.createElement('div');
                noMore.className = 'no-more';
                noMore.textContent = 'No more posts';
                container.appendChild(noMore);
            }
        } else if (currentPage === 1) {
            document.getElementById('postsContainer').innerHTML = '<div class="no-more">No posts yet. Be the first to post!</div>';
        }
    } catch (error) {
        console.error('Error loading posts:', error);
        if (currentPage === 1) {
            document.getElementById('postsContainer').innerHTML = '<div class="message error">Failed to load posts: ' + error.message + '</div>';
        }
    }
    

    isLoading = false;
}

function showEditError(message) {
    editError.textContent = message;
    editError.style.display = 'block';
}

function clearEditError() {
    editError.textContent = '';
    editError.style.display = 'none';
}

function openEditDialog(postId) {
    const post = postsById.get(postId);
    if (!post) return;

    clearEditError();
    editForm.reset();
    editPostId.value = post.id;
    editContent.value = post.content;

    const rawImagePath = (post.image_url || post.image_path || '').trim();
    currentImageInfo.textContent = rawImagePath !== ''
        ? `Current image will be kept unless you upload a new one.`
        : '';

    editModal.classList.add('open');
    editContent.focus();
}

function closeEditDialog() {
    editModal.classList.remove('open');
    editForm.reset();
    clearEditError();
}

editForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearEditError();

    const formData = new FormData(editForm);

    try {
        const response = await fetch('/posts/update', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        if (!data.success) {
            throw new Error(data.error || 'Failed to update post');
        }

        if (data.post) {
            updatePostCard(data.post);
        }

        closeEditDialog();
    } catch (error) {
        showEditError(error.message);
    }
});

cancelEditBtn.addEventListener('click', () => closeEditDialog());
editModal.addEventListener('click', (event) => {
    if (event.target === editModal) {
        closeEditDialog();
    }
});


function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function getTimeAgo(date) {
    const seconds = Math.floor((new Date() - date) / 1000);
    

    if (seconds < 60) return 'just now';
    if (seconds < 3600) return Math.floor(seconds / 60) + ' minutes ago';
    if (seconds < 86400) return Math.floor(seconds / 3600) + ' hours ago';
    if (seconds < 604800) return Math.floor(seconds / 86400) + ' days ago';
    

    return date.toLocaleDateString();
}

// Infinite scroll
window.addEventListener('scroll', () => {
    if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 500) {
        loadPosts();
    }
});

// Load initial posts
loadPosts();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';

