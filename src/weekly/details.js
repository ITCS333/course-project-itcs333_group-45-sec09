// API Configuration
const API_URL = '../api/index.php';

// --- Global Data Store ---
let currentWeekId = null;
let currentComments = [];

// --- Element Selections ---
const weekTitle = document.querySelector('#week-title');
const weekStartDate = document.querySelector('#week-start-date');
const weekDescription = document.querySelector('#week-description');
const weekLinksList = document.querySelector('#week-links-list');
const commentList = document.querySelector('#comment-list');
const commentForm = document.querySelector('#comment-form');
const newCommentText = document.querySelector('#new-comment-text');

// --- API Functions ---

async function fetchWeekById(weekId) {
  try {
    const response = await fetch(`${API_URL}?resource=weeks&week_id=${weekId}`);
    const result = await response.json();
    
    if (result.success) {
      return result.data;
    } else {
      throw new Error(result.error || 'Week not found');
    }
  } catch (error) {
    console.error('Error fetching week:', error);
    return null;
  }
}

async function fetchComments(weekId) {
  try {
    const response = await fetch(`${API_URL}?resource=comments&week_id=${weekId}`);
    const result = await response.json();
    
    if (result.success) {
      return result.data;
    } else {
      throw new Error(result.error || 'Failed to fetch comments');
    }
  } catch (error) {
    console.error('Error fetching comments:', error);
    return [];
  }
}

async function createCommentAPI(commentData) {
  try {
    const response = await fetch(`${API_URL}?resource=comments`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(commentData)
    });
    
    const result = await response.json();
    
    if (result.success) {
      return result.data;
    } else {
      throw new Error(result.error || 'Failed to create comment');
    }
  } catch (error) {
    console.error('Error creating comment:', error);
    alert('Error posting comment: ' + error.message);
    return null;
  }
}

// --- Functions ---

function getWeekIdFromURL() {
  const queryString = window.location.search;
  const urlParams = new URLSearchParams(queryString);
  return urlParams.get('id');
}

function renderWeekDetails(week) {
  weekTitle.textContent = week.title;
  weekStartDate.textContent = `Starts on: ${week.start_date}`;
  weekDescription.textContent = week.description;
  
  weekLinksList.innerHTML = '';
  
  if (week.links && Array.isArray(week.links) && week.links.length > 0) {
    week.links.forEach(link => {
      const li = document.createElement('li');
      const a = document.createElement('a');
      a.href = link;
      a.textContent = link;
      a.target = '_blank';
      a.rel = 'noopener noreferrer';
      li.appendChild(a);
      weekLinksList.appendChild(li);
    });
  } else {
    const li = document.createElement('li');
    li.textContent = 'No resources available for this week.';
    li.style.color = '#666';
    weekLinksList.appendChild(li);
  }
}

function createCommentArticle(comment) {
  const article = document.createElement('article');
  article.className = 'comment-article';
  
  const p = document.createElement('p');
  p.textContent = comment.text;
  
  const footer = document.createElement('footer');
  const date = new Date(comment.created_at);
  footer.textContent = `Posted by: ${comment.author} on ${date.toLocaleDateString()}`;
  
  article.appendChild(p);
  article.appendChild(footer);
  
  return article;
}

function renderComments() {
  commentList.innerHTML = '';
  
  if (currentComments.length === 0) {
    const noComments = document.createElement('p');
    noComments.textContent = 'No comments yet. Be the first to ask a question!';
    noComments.style.color = '#666';
    noComments.style.fontStyle = 'italic';
    commentList.appendChild(noComments);
    return;
  }
  
  currentComments.forEach(comment => {
    const article = createCommentArticle(comment);
    commentList.appendChild(article);
  });
}

async function handleAddComment(event) {
  event.preventDefault();
  
  const commentText = newCommentText.value.trim();
  
  if (commentText === '') {
    alert('Please enter a comment.');
    return;
  }
  
  const commentData = {
    week_id: currentWeekId,
    author: 'Student', // This should be replaced with actual user name from session
    text: commentText
  };
  
  const newComment = await createCommentAPI(commentData);
  
  if (newComment) {
    currentComments = await fetchComments(currentWeekId);
    renderComments();
    newCommentText.value = '';
    alert('Comment posted successfully!');
  }
}

async function initializePage() {
  currentWeekId = getWeekIdFromURL();
  
  if (!currentWeekId) {
    weekTitle.textContent = 'Week not found.';
    weekDescription.textContent = 'Invalid week ID in URL.';
    return;
  }
  
  try {
    // Fetch week details
    const week = await fetchWeekById(currentWeekId);
    
    if (!week) {
      weekTitle.textContent = 'Week not found.';
      weekDescription.textContent = 'The requested week does not exist.';
      return;
    }
    
    // Fetch comments
    currentComments = await fetchComments(currentWeekId);
    
    // Render everything
    renderWeekDetails(week);
    renderComments();
    
    // Add event listener
    commentForm.addEventListener('submit', handleAddComment);
    
    console.log('Page initialized successfully');
    console.log('Current week:', week);
    console.log('Current comments:', currentComments);
    
  } catch (error) {
    console.error('Error initializing page:', error);
    weekTitle.textContent = 'Error loading week details';
    weekDescription.textContent = 'Please try again later.';
  }
}

// --- Initial Page Load ---
initializePage();